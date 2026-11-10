"""Run via GSLIP_TEST_PUSH=1 python tests/authentication_lifecycle.py.

Uses that suite's disposable database/server, never application subscriptions.
"""
import json
import urllib.error
import urllib.parse
import urllib.request

if 'h' not in globals():
    raise SystemExit('Run through authentication_lifecycle.py with GSLIP_TEST_PUSH=1')

count = 0

def verify(condition, label):
    global count
    assert condition, label
    count += 1

def api(actor, route, data=None, csrf=None, raw=None):
    headers = {'Accept':'application/json', 'Content-Type':'application/json'}
    if csrf is not None:
        headers['X-CSRF-Token'] = csrf
    body = raw if raw is not None else (json.dumps(data).encode() if data is not None else None)
    request = urllib.request.Request(h['base']+'api/'+route, data=body, headers=headers)
    try:
        response = actor.open(request,timeout=10)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        content = response.read().decode()
        verify(response.headers.get_content_type()=='application/json','JSON response for '+route)
        return response.code, json.loads(content)

subscription = {'endpoint':'https://push.example.invalid/fixture-device',
                'keys':{'p256dh':'a'*87,'auth':'b'*22}}
normal, _ = h['login']()
status, data = api(normal,'push_public_key.php')
verify(status==503 and 'configuration is missing' in data['message'], 'reproduced original missing-config HTTP 503')
verify(api(h['client'](),'push_public_key.php')[0]==401,'anonymous request denied')

# Generate test-only keys into the isolated fixture, never into the repository.
config = h['work']/'includes/web_push_config.php'
import subprocess
subprocess.run(['node','-e',
    """const fs=require('fs'),c=require('crypto').createECDH('prime256v1'); c.generateKeys(); fs.writeFileSync(process.argv[1],"<?php return ['subject'=>'mailto:fixture@example.invalid','public_key'=>'"+c.getPublicKey().toString('base64url')+"','private_key'=>'"+c.getPrivateKey().toString('base64url')+"'];");""",
    str(config)],check=True)


for remember in [False, True]:
    actor, _ = h['login'](remember=remember)
    if remember:
        actor = h['client'](h['cookie'](actor))
        verify(h['request'](actor,'dashboard.php')[0]==200,'remembered navigation restores real session')
    status, data = api(actor,'push_public_key.php')
    verify(status==200 and data['public_key'] and len(data['csrf'])==64,'key and session-bound CSRF available')
    csrf = data['csrf']
    verify(api(actor,'save_push_subscription.php',subscription)[0]==403,'save requires CSRF')
    verify(api(actor,'save_push_subscription.php',subscription,'forged')[0]==403,'save rejects forged CSRF')
    verify(api(actor,'save_push_subscription.php',subscription,csrf)[0]==200,'normal/restored session saves subscription')
    verify(h['sql']("SELECT COUNT(*) AS n FROM push_subscriptions WHERE user_id=900001")[0]['n']=='1','preference persisted without duplicate rows')
    verify(api(actor,'save_push_subscription.php',subscription,csrf)[0]==200,'existing subscription synchronization retained')
    verify(api(actor,'remove_push_subscription.php',{'endpoint':subscription['endpoint']},'forged')[0]==403,'remove requires valid CSRF')
    verify(api(actor,'remove_push_subscription.php',{'endpoint':subscription['endpoint']},csrf)[0]==200,'normal/restored session disables notifications')
    verify(h['sql']("SELECT COUNT(*) AS n FROM push_subscriptions WHERE user_id=900001")[0]['n']=='0','disable removes only fixture account preference')
    verify(api(actor,'save_push_subscription.php',{'endpoint':'http://insecure.example'},csrf)[0]==422,'invalid subscription rejected')
    verify(api(actor,'save_push_subscription.php',csrf=csrf,raw=b'not json')[0]==400,'malformed JSON rejected')

stale, _ = h['login']()
current, _ = h['login']()
verify(api(stale,'push_public_key.php')[0]==401,'stale session token denied even with user_id')
csrf = api(current,'push_public_key.php')[1]['csrf']
h['sql']('RENAME TABLE push_subscriptions TO fixture_saved_subscriptions')
try:
    status,data=api(current,'save_push_subscription.php',subscription,csrf)
    verify(status==500 and 'server' in data['message'].lower() and 'SQL' not in data['message'],'database failure is sanitized JSON')
finally:
    h['sql']('RENAME TABLE fixture_saved_subscriptions TO push_subscriptions')

event={'id':'push-test','enabled':True,'effective_at':0,'ended_at':0,'reason':'Fixture'}
h['sql']("UPDATE system_settings SET setting_value='"+json.dumps(event)+"' WHERE setting_key='maintenance_event'")
try:
    verify(api(current,'push_public_key.php')[0]==503,'maintenance blocks regular-user push API')
    verify(api(current,'save_push_subscription.php',subscription,csrf)[0]==503,'maintenance blocks subscription writes')
finally:
    event['enabled']=False
    h['sql']("UPDATE system_settings SET setting_value='"+json.dumps(event)+"' WHERE setting_key='maintenance_event'")

h['sql']("UPDATE users SET status='inactive' WHERE id=900001")
try:
    verify(api(current,'push_public_key.php')[0]==401,'inactive account cannot access push settings')
finally:
    h['sql']("UPDATE users SET status='active' WHERE id=900001")
print(f'{count} push HTTP checks passed (normal and restored login, isolated database).')
