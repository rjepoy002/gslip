"""Actual auth routes; only a uniquely named disposable database is mutated."""
import contextlib
import concurrent.futures
import hashlib
import http.cookiejar
import json
import os
from pathlib import Path
import re
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid

ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get('GSLIP_TEST_PHP', r'C:\xampp\php\php.exe')
DB = 'gslip_auth_test_' + uuid.uuid4().hex[:12]
checks = 0

def php(code):
    return subprocess.check_output([PHP], input='<?php '+code, text=True, encoding='utf-8').strip()

def sql(query):
    return json.loads(php("$c=new mysqli('localhost','root','','"+DB+"'); $r=$c->query("+json.dumps(query)+"); echo json_encode($r instanceof mysqli_result ? $r->fetch_all(MYSQLI_ASSOC) : []);"))

def check(value, label):
    global checks
    assert value, label
    checks += 1

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None

def client(cookie=None):
    jar = http.cookiejar.CookieJar()
    c = urllib.request.build_opener(NoRedirect(), urllib.request.HTTPCookieProcessor(jar))
    c.jar = jar
    if cookie:
        jar.set_cookie(http.cookiejar.Cookie(0, 'gslip_remember', cookie, None, False,
            '127.0.0.1', False, False, '/gslip/', True, False, int(time.time())+2592000, False, None, None, {}, False))
    return c

def request(c, path, data=None):
    body = urllib.parse.urlencode(data).encode() if data is not None else None
    try:
        r = c.open(base+path, body, timeout=15)
    except urllib.error.HTTPError as e:
        r = e
    with r:
        return r.code, r.read().decode(), r.headers

def csrf(c, path='index.php'):
    status, body, _ = request(c, path)
    assert status == 200, (status, body[:300])
    return re.search(r'name="csrf" value="([a-f0-9]{64})"', body)[1]

def cookie(c, name='gslip_remember'):
    value = next((x.value for x in c.jar if x.name == name), None)
    return urllib.parse.unquote(value) if value else None

def login(c=None, remember=False, user='auth-user', password='Auth-test-123!'):
    c = c or client()
    token = csrf(c, 'logout.php')
    old_session = cookie(c, 'PHPSESSID')
    values = {'csrf':token, 'username':user, 'password':password}
    if remember:
        values['remember_me'] = '1'
    status, body, headers = request(c, 'index.php', values)
    check(status == 302 and headers['Location'] == 'dashboard.php', 'password login succeeds: '+body[:100])
    check(cookie(c, 'PHPSESSID') != old_session, 'password login regenerates session ID')
    return c, headers

def token_row(value):
    return sql("SELECT *,expires_at<=UTC_TIMESTAMP()+INTERVAL 30 DAY AS capped FROM remember_tokens WHERE selector='"+value.split(':')[0]+"'")[0]

server = None
try:
    php("$c=new mysqli('localhost','root',''); $c->query('CREATE DATABASE "+DB+" CHARACTER SET utf8mb4');")
    # Schema only: application users/transactions are never copied or modified.
    php("$c=new mysqli('localhost','root','','"+DB+"'); foreach($c->query('SHOW TABLES FROM gsccxzp_pal_db_test') as $r){$t=array_values($r)[0];if($t!=='remember_tokens')$c->query(\"CREATE TABLE `$t` LIKE `gsccxzp_pal_db_test`.`$t`\");} foreach(['departments','areas'] as $t)$c->query(\"INSERT INTO `$t` SELECT * FROM `gsccxzp_pal_db_test`.`$t`\");")
    migration = (ROOT/'migrations/20261110_remember_tokens.sql').read_text().replace('USE gsccxzp_pal_db_test;', 'USE '+DB+';')
    # mysql client needed for DELIMITER; migration never targets configured DB here.
    mysql = os.environ.get('GSLIP_TEST_MYSQL', r'C:\xampp\mysql\bin\mysql.exe')
    for _ in range(2):
        subprocess.run([mysql,'-u','root'], input=migration, text=True, check=True, capture_output=True)
    check(len(sql("SHOW TRIGGERS WHERE `Trigger`='gslip_revoke_remember_security'")) == 1, 'idempotent migration and trigger')
    php("$c=new mysqli('localhost','root','','"+DB+"'); $d=$c->query('SELECT id FROM departments LIMIT 1')->fetch_row()[0]; $a=$c->query('SELECT id FROM areas LIMIT 1')->fetch_row()[0]; $p=password_hash('Auth-test-123!',PASSWORD_DEFAULT); $s=$c->prepare(\"INSERT INTO users(id,first_name,middle_name,last_name,username,password,department_id,area_id,role,status) VALUES(900001,'Auth','Middle','Test','auth-user',?,?,?,NULL,'active'),(900002,'Auth','Middle','Admin','auth-admin',?,?,?,'admin','active')\");$s->bind_param('siisii',$p,$d,$a,$p,$d,$a);$s->execute();")
    with tempfile.TemporaryDirectory(prefix='gslip-auth-') as temp, contextlib.ExitStack() as cleanup:
        webroot = Path(temp)
        work = webroot/'gslip'
        work.mkdir()
        shutil.copytree(ROOT/'includes', work/'includes', ignore=shutil.ignore_patterns('web_push_config.php'))
        for name in ['index.php','index_09042026.php','logout.php','signup_handler.php','forgotpass_handler.php','reset_password.php','maintenance.php','dashboard.php']:
            shutil.copy(ROOT/name, work/name)
        for name in ['auth_security.php','login_session.php','remember_login.php','maintenance.php','dark-mode-preload.php','changelog_modal.php']:
            shutil.copy(ROOT/'includes'/name, work/'includes'/name)
        shutil.copytree(ROOT/'assets', work/'assets')
        shutil.copytree(ROOT/'api', work/'api')
        shutil.copy(ROOT/'sw.js',work/'sw.js')
        (work/'images').mkdir()
        shutil.copy(ROOT/'images/logo_paleco.png',work/'images/logo_paleco.png')
        config = (ROOT/'includes/config.php').read_text().replace('gsccxzp_pal_db_test', DB)
        (work/'includes/config.php').write_text(config)
        (work/'includes/sidebar.php').write_text('<!-- isolated admin sidebar -->')
        (work/'expire.php').write_text("<?php session_start(); $_SESSION=[]; session_destroy(); echo 'expired';")
        (work/'partial.php').write_text("<?php session_start(); $_SESSION=['user_id'=>900001]; echo 'partial';")
        (work/'guarded.php').write_text("<?php require 'includes/auth.php'; echo 'guard passed';")
        (work/'probe.php').write_text("<?php session_start(); require 'includes/config.php'; $c=getDBConnection(); require_once 'includes/maintenance.php'; echo json_encode(['user'=>$_SESSION['user_id']??null,'valid'=>(bool)maintenanceActor($c)]);")
        with socket.socket() as sock:
            sock.bind(('127.0.0.1',0)); port = sock.getsockname()[1]
        base = f'http://127.0.0.1:{port}/gslip/'
        log = open(work/'server.log','w+')
        cleanup.callback(log.close)
        server = subprocess.Popen([PHP,'-S',f'127.0.0.1:{port}','-t',str(webroot)],stdout=log,stderr=log)
        cleanup.callback(lambda: (server.terminate(), server.wait(timeout=10)))
        for _ in range(50):
            try:
                csrf(client()); break
            except OSError:
                time.sleep(.1)
        check(request(client(),'index.php',{'username':'auth-user','password':'Auth-test-123!'})[0] == 403, 'login CSRF required')
        for route in ['signup_handler.php','forgotpass_handler.php','reset_password.php','logout.php']:
            check(request(client(), route, {'csrf':'forged'})[0] == 403, route+' CSRF required')
        # Password validation on Create Account remains authoritative.
        signup = client(); signup_csrf = csrf(signup)
        for password, confirm in [('short','short'),('Auth-test-123!','Different-123!')]:
            response = request(signup,'signup_handler.php',{'csrf':signup_csrf,'first_name':'Test','last_name':'User','username':'auth-new','password':password,'confirm_password':confirm,'department_id':'1','designation':'Test'})
            check(response[0] == 302 and response[2]['Location']=='index.php?signup=error', 'signup password validation preserved')
        c, headers = login()
        check(cookie(c) is None, 'unchecked login has no persistent cookie')
        check(request(c,'index.php')[0] == 302, 'REGRESSION: reopening login with a retained valid PHP session redirects to dashboard')
        session_header = next(h for h in headers.get_all('Set-Cookie') if h.startswith('PHPSESSID='))
        check('expires=' not in session_header.lower() and 'max-age=' not in session_header.lower(), 'PHP session remains nonpersistent')
        c, headers = login(c, True)
        value = cookie(c); row = token_row(value)
        check(row['verifier_hash'] == hashlib.sha256(value.split(':')[1].encode()).hexdigest(), 'only hashed verifier stored')
        check(row['capped'] == '1', 'absolute expiry at most 30 days')
        check(request(c,'index.php')[2]['Location']=='dashboard.php' and token_row(value)['last_used_at'] is None, 'retained PHP session returns dashboard without consuming token')
        rh = next(h for h in headers.get_all('Set-Cookie') if h.startswith('gslip_remember=') and 'deleted' not in h)
        check('HttpOnly' in rh and 'SameSite=Lax' in rh and 'path=/gslip/' in rh and 'domain=' not in rh.lower(), 'remember cookie attributes')
        restarted = client(value)
        status, _, headers = request(restarted,'index.php')
        check(status == 302 and headers['Location'] == 'dashboard.php', 'persistent credential survives loss of PHP session')
        rotated = cookie(restarted)
        check(rotated != value and token_row(rotated)['expires_at'] == row['expires_at'], 'verifier rotates without extending expiry')
        check(token_row(rotated)['last_used_at'] is not None, 'rotation records last use')
        check(json.loads(request(restarted,'probe.php')[1])['valid'], 'auto login creates normal valid session')
        check(request(client(value),'index.php')[0] == 200, 'stolen old cookie rejected')
        check(token_row(value)['revoked_at'] is not None and token_row(rotated)['revoked_at'] is None, 'old token revoked while replacement remains usable')
        check(json.loads(request(restarted,'probe.php')[1])['valid'], 'old-token replay does not invalidate the legitimate replacement session')
        for malformed in ['bad', 'a'*32+':short', 'A'*32+':'+'a'*64, 'a'*32+':'+'b'*64+':extra']:
            bad = client(malformed)
            check(request(bad,'index.php')[0] == 200 and cookie(bad) is None, 'malformed cookie rejected')
        c, _ = login(remember=True); value = cookie(c)
        wrong = value.split(':')[0]+':'+'0'*64
        check(request(client(wrong),'index.php')[0] == 200 and token_row(value)['revoked_at'] is not None, 'mismatched verifier rejected and revoked')
        c, _ = login(remember=True); value = cookie(c)
        sql("UPDATE remember_tokens SET expires_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE selector='"+value.split(':')[0]+"'")
        check(request(client(value),'index.php')[0] == 200, 'expired token rejected')
        c, _ = login(remember=True); value = cookie(c)
        c, _ = login(c, False)
        check(cookie(c) is None and token_row(value)['revoked_at'] is not None, 'unchecked login clears and revokes previous credential')
        c, _ = login(remember=True); value = cookie(c)
        logout_csrf = csrf(c,'logout.php')
        check(json.loads(request(c,'probe.php')[1])['valid'], 'GET logout does not log out')
        check(request(c,'logout.php',{'csrf':'bad'})[0] == 403 and token_row(value)['revoked_at'] is None, 'forged logout preserves session and token')
        check(request(c,'logout.php',{'csrf':logout_csrf})[0] == 302, 'CSRF logout succeeds')
        check(cookie(c) is None and cookie(c,'PHPSESSID') is None and token_row(value)['revoked_at'] is not None, 'logout removes both cookies and revokes server token')
        check(request(client(value),'index.php')[0] == 200, 'logged out credential cannot replay')
        for field, expression, restore in [('status',"'inactive'","'active'"),('role',"'admin'",'NULL'),('username',"'auth-renamed'","'auth-user'"),('password',"'unusable-test-hash'",None)]:
            c, _ = login(remember=True); value = cookie(c)
            sql('UPDATE users SET '+field+'='+expression+' WHERE id=900001')
            check(token_row(value)['revoked_at'] is not None, field+' changes revoke tokens')
            check(request(client(value),'index.php')[0] == 200, field+' restricted remembered authentication')
            if restore:
                sql('UPDATE users SET '+field+'='+restore+' WHERE id=900001')
            else:
                php("$c=new mysqli('localhost','root','','"+DB+"');$p=password_hash('Auth-test-123!',PASSWORD_DEFAULT);$s=$c->prepare('UPDATE users SET password=? WHERE id=900001');$s->bind_param('s',$p);$s->execute();")
        # Existing forgot/reset flow still validates identity, token and password rules.
        recover = client(); recovery_csrf = csrf(recover)
        status, _, headers = request(recover,'forgotpass_handler.php',{'csrf':recovery_csrf,'username':'auth-user','middle_name':'Middle'})
        check(status == 302 and headers['Location'].startswith('reset_password.php?token='), 'existing recovery identity check succeeds')
        reset_url = headers['Location']; reset_token = reset_url.split('token=')[1]
        reset_csrf = csrf(recover,reset_url)
        c, _ = login(remember=True); value = cookie(c)
        check(request(recover,reset_url,{'csrf':reset_csrf,'token':reset_token,'password':'short','confirm_password':'short'})[2]['Location'].endswith('error=invalid'), 'short password still rejected')
        check(request(recover,reset_url,{'csrf':reset_csrf,'token':reset_token,'password':'Auth-test-123!','confirm_password':'Auth-test-123!'})[2]['Location'] == 'index.php?reset=success', 'existing password reset succeeds')
        check(token_row(value)['revoked_at'] is not None, 'actual reset revokes persistent authentication')
        c, _ = login(remember=True); value = cookie(c)
        admin, _ = login(remember=True,user='auth-admin'); admin_cookie = cookie(admin)
        def state(actor):
            return json.loads(request(actor,'maintenance.php?state=1')[1])
        st = state(admin)
        check(request(admin,'maintenance.php',{'csrf':st['csrf'],'action':'enable'})[0] == 200, 'admin enables maintenance')
        check(token_row(value)['revoked_at'] is not None and token_row(admin_cookie)['revoked_at'] is not None, 'maintenance start revokes online and offline devices')
        event = state(admin)['state']
        check(event['phase'] == 'warning', 'existing maintenance warning remains')
        user_state = state(c)
        check(request(c,'maintenance.php',{'csrf':'bad','action':'logout','event_id':event['id']})[0] == 403, 'maintenance logout CSRF retained')
        check(json.loads(request(c,'maintenance.php',{'csrf':user_state['csrf'],'action':'logout','event_id':event['id']})[1])['logout'], 'maintenance acknowledgement logs out')
        check(cookie(c) is None, 'maintenance logout clears persistent cookie')
        warning_user, _ = login(remember=True)
        check(cookie(warning_user) is None, 'warning-phase login issues no token that could survive maintenance')
        event['effective_at'] = int(time.time())-1
        event.pop('phase',None); event.pop('server_time',None)
        sql("UPDATE system_settings SET setting_value='"+json.dumps(event)+"' WHERE setting_key='maintenance_event'")
        check(request(client(value),'index.php')[0] == 200, 'no auto login during active maintenance')
        check(request(client(admin_cookie),'index.php')[0] == 200, 'admin is not auto logged in during maintenance')
        blocked = client(); token = csrf(blocked)
        check(request(blocked,'index.php',{'csrf':token,'username':'auth-user','password':'Auth-test-123!','remember_me':'1'})[2]['Location'] == 'maintenance.php', 'normal password login remains blocked')
        active_admin, _ = login(remember=True,user='auth-admin')
        check(cookie(active_admin) is None, 'explicit admin login during maintenance issues no persistent token')
        check(request(active_admin,'probe.php')[0] == 200, 'admin maintenance exemption retained')
        check(request(client(),'probe.php')[2]['Location'] == 'maintenance.php', 'maintenance guard remains authoritative')
        st = state(active_admin)
        check(request(active_admin,'maintenance.php',{'csrf':st['csrf'],'action':'disable'})[0] == 200, 'admin ends maintenance')
        check(request(client(value),'index.php')[0] == 200, 'ending maintenance never revives offline remembered device')
        # Expired/partial PHP sessions restore directly before protected guards.
        direct, _ = login(remember=True); before_expiry = cookie(direct)
        request(direct,'expire.php')
        response = request(direct,'dashboard.php')
        check(response[0]==200 and 'Dashboard' in response[1], 'real dashboard restores before its included guards')
        check(cookie(direct)!=before_expiry and token_row(cookie(direct))['last_used_at'] is not None, 'expired PHP session rotates token on dashboard')
        request(direct,'partial.php')
        check(request(direct,'guarded.php')[1]=='guard passed', 'partial user_id alone cannot suppress remember restoration before shared auth guard')
        check(json.loads(request(direct,'probe.php')[1])['valid'], 'partial-session restoration establishes normal validated session')
        at_root, _ = login(remember=True)
        root_cookie = cookie(at_root)
        reopened_root = client(root_cookie)
        check(request(reopened_root,'')[2]['Location']=='dashboard.php', 'application-directory entrypoint receives cookie and restores')
        check(token_row(root_cookie)['revoked_at'] is not None, 'directory entrypoint rotates the presented credential')
        # Background requests must not silently rotate a token.
        background, _ = login(remember=True); before_background = cookie(background)
        request(background,'expire.php')
        req = urllib.request.Request(base+'probe.php',headers={'Accept':'application/json','Sec-Fetch-Mode':'cors'})
        with background.open(req) as response:
            check(not json.loads(response.read())['valid'], 'background JSON request does not restore authentication')
        check(token_row(before_background)['last_used_at'] is None, 'background request leaves token unconsumed')
        # An active event inserted by an external operator blocks restoration but
        # merely checking it must not revoke a previously active credential.
        paused, _ = login(remember=True); pause_cookie = cookie(paused)
        pause_event = {'id':'external-maintenance','enabled':True,'effective_at':int(time.time())-1,'ended_at':0,'reason':'Fixture'}
        sql("UPDATE system_settings SET setting_value='"+json.dumps(pause_event)+"' WHERE setting_key='maintenance_event'")
        waiting = client(pause_cookie)
        check(request(waiting,'index.php')[0]==200, 'active maintenance blocks restoration')
        check(token_row(pause_cookie)['revoked_at'] is None and cookie(waiting)==pause_cookie, 'maintenance read does not revoke token or delete cookie')
        pause_event['enabled']=False
        sql("UPDATE system_settings SET setting_value='"+json.dumps(pause_event)+"' WHERE setting_key='maintenance_event'")
        check(request(waiting,'index.php')[0]==302, 'explicit navigation after external maintenance can restore an unrevoked device')
        # Failed response-header issuance must leave the original credential active.
        failed, _ = login(remember=True); failed_cookie = cookie(failed)
        failed_code = "chdir("+json.dumps(str(work))+"); session_start(); require 'includes/config.php'; require_once 'includes/login_session.php'; $_COOKIE[REMEMBER_COOKIE]="+json.dumps(failed_cookie)+"; $c=getDBConnection(); echo 'headers-sent;'; echo json_encode(rememberAutoLogin($c));"
        check(php(failed_code)=='headers-sent;false', 'restoration fails safely if a replacement cookie cannot be issued')
        check(token_row(failed_cookie)['revoked_at'] is None and token_row(failed_cookie)['last_used_at'] is None, 'failed replacement leaves original token unrevoked and unused')
        # Two independent PHP processes consume one credential concurrently.
        racer, _ = login(remember=True); race_cookie = cookie(racer)
        race_code = "chdir("+json.dumps(str(work))+"); session_id('race-'.bin2hex(random_bytes(12))); session_start(); require 'includes/config.php'; require_once 'includes/login_session.php'; require_once 'includes/remember_login.php'; $_COOKIE[REMEMBER_COOKIE]="+json.dumps(race_cookie)+"; $c=getDBConnection(); echo json_encode(rememberAutoLogin($c));"
        with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
            results = list(pool.map(php, [race_code, race_code]))
        check(sorted(results) == ['false','true'], 'concurrent rotation accepts the old verifier only once')
        check(token_row(race_cookie)['revoked_at'] is not None, 'concurrent replay rejects the retired token')
        option_code = "require "+json.dumps(str(ROOT/'includes/remember_login.php'))+"; $_SERVER['DOCUMENT_ROOT']="+json.dumps(str(ROOT.parent))+"; $_SERVER['HTTPS']='on'; echo json_encode(rememberCookieOptions(time()+60));"
        options = json.loads(php(option_code))
        check(options['secure'] and options['httponly'] and options['samesite']=='Lax' and options['path']=='/gslip/', 'HTTPS and application-subdirectory cookie options')
        # Missing migration fails closed for opt-in but preserves ordinary login.
        sql('RENAME TABLE remember_tokens TO auth_test_saved_tokens')
        try:
            plain, _ = login()
            opt = client(); opt_csrf = csrf(opt)
            response = request(opt,'index.php',{'csrf':opt_csrf,'username':'auth-user','password':'Auth-test-123!','remember_me':'1'})
            check(response[0] == 200 and cookie(opt) is None, 'opted-in login fails safely before migration')
        finally:
            sql('RENAME TABLE auth_test_saved_tokens TO remember_tokens')
        if os.environ.get('GSLIP_TEST_PUSH'):
            import runpy
            runpy.run_path(str(ROOT/'tests/push_notifications_http.py'),init_globals={'h':globals()})
            if os.environ.get('GSLIP_UI_PLAYWRIGHT'):
                subprocess.run(['node',str(ROOT/'tests/push_notifications_browser.cjs')],env=dict(os.environ,GSLIP_AUTH_TEST_URL=base),check=True)
        # UI suite uses this same isolated server and actual rendered pages.
        if os.environ.get('GSLIP_UI_PLAYWRIGHT'):
            sql("UPDATE users SET reset_token='ui-test-token',reset_expires=NOW()+INTERVAL 10 MINUTE WHERE id=900001")
            env = dict(os.environ, GSLIP_AUTH_TEST_URL=base)
            for channel in os.environ.get('GSLIP_UI_CHANNELS','').split(','):
                subprocess.run(['node',str(ROOT/'tests/authentication_ui.cjs')],env=dict(env,GSLIP_UI_CHANNEL=channel),check=True)
        log.flush(); log.seek(0); output = log.read()
        check('Fatal error' not in output and 'Warning:' not in output, 'HTTP routes have no PHP warnings/fatal errors: '+ '\n'.join(line for line in output.splitlines() if 'Warning' in line or 'Fatal' in line))
        check('GSLIP remember' in output and 'restore_success' in output and 'cookie_not_received' in output, 'safe lifecycle diagnostics distinguish cookie absence and success')
        check(value not in output and value.split(':')[1] not in output and row['verifier_hash'] not in output and 'Auth-test-123!' not in output, 'diagnostics exclude cookies, verifiers, hashes and passwords')
        print(f'{checks} authentication lifecycle checks passed; isolated database only.')
finally:
    if server:
        server.terminate(); server.wait(timeout=10)
    php("$c=new mysqli('localhost','root','');$c->query('DROP DATABASE IF EXISTS "+DB+"');")
