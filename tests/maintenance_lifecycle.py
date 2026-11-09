"""Isolated real-HTTP lifecycle tests. Creates/drops only a uniquely named test DB."""
import http.cookiejar
import json
import os
from pathlib import Path
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
DB = 'gslip_maintenance_test_' + uuid.uuid4().hex[:12]

def sql(query):
    script = "<?php $c=new mysqli('localhost','root',''); $c->query(" + json.dumps(query) + ");"
    subprocess.run([PHP], input=script, text=True, check=True, capture_output=True)

def client():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

def request(c, path, data=None):
    body = urllib.parse.urlencode(data).encode() if data is not None else None
    try:
        with c.open(base + path, body, timeout=5) as r:
            return r.status, r.read().decode()
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode()

server = None
try:
    sql(f'CREATE DATABASE {DB}')
    sql(f'CREATE TABLE {DB}.system_settings(setting_key VARCHAR(100) PRIMARY KEY,setting_value VARCHAR(255))')
    sql(f"CREATE TABLE {DB}.users(id INT PRIMARY KEY,role VARCHAR(20),session_token VARCHAR(64),status VARCHAR(20))")
    sql(f"INSERT INTO {DB}.users VALUES(1,'admin','admin-token','active'),(2,'user','user-token','active')")
    with tempfile.TemporaryDirectory(prefix='gslip-maintenance-') as work:
        work = Path(work)
        (work/'includes').mkdir()
        (work/'assets/css').mkdir(parents=True)
        shutil.copy(ROOT/'assets/css/maintenance.css',work/'assets/css/maintenance.css')
        shutil.copy(ROOT/'includes/dark-mode-preload.php',work/'includes/dark-mode-preload.php')
        (work/'includes/sidebar.php').write_text('<aside class="app-sidebar">Admin sidebar fixture</aside>')
        shutil.copy(ROOT/'maintenance.php',work/'maintenance.php')
        shutil.copy(ROOT/'includes/maintenance.php',work/'includes/maintenance.php')
        (work/'includes/config.php').write_text("<?php function getDBConnection(){ $c=new mysqli('localhost','root','','"+DB+"'); require_once __DIR__.'/maintenance.php'; maintenanceGuard($c); return $c; }")
        # Test-only authentication fixture, outside application code.
        (work/'fixture.php').write_text("<?php session_start(); $_SESSION['user_id']=(int)($_GET['admin']??0)?1:2; $_SESSION['session_token']=$_SESSION['user_id']===1?'admin-token':'user-token'; echo 'ok';")
        (work/'protected.php').write_text("<?php session_start(); require 'includes/config.php'; getDBConnection(); echo 'normal dashboard';")
        with socket.socket() as s:
            s.bind(('127.0.0.1',0)); port=s.getsockname()[1]
        base=f'http://127.0.0.1:{port}/'
        server=subprocess.Popen([PHP,'-S',f'127.0.0.1:{port}','-t',str(work)],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
        admin,user,offline=client(),client(),client()
        for attempt in range(40):
            try: request(admin,'fixture.php?admin=1'); break
            except OSError: time.sleep(.1)
        request(user,'fixture.php')
        def state(c=user): return json.loads(request(c,'maintenance.php?state=1')[1])
        def action(name,event=None,c=admin):
            st=state(c)
            return request(c,'maintenance.php',{'action':name,'csrf':st['csrf'],'event_id':event or st['state']['id'],'reason':'Test window'})
        admin_html=request(admin,'maintenance.php')[1]
        user_html=request(user,'maintenance.php')[1]
        public_html=request(offline,'maintenance.php')[1]
        assert 'id="maintenance-controls"' in admin_html and 'Admin sidebar fixture' in admin_html
        for body in [user_html,public_html]:
            assert 'id="maintenance-controls"' not in body
            assert 'Admin sidebar fixture' not in body
            assert 'href="dashboard.php"' not in body
        assert state()['state']['phase']=='ended'
        assert action('enable')[0]==200
        first=state()['state']['id']
        assert state()['state']['phase']=='warning'
        assert 'normal dashboard' in request(user,'protected.php')[1]
        assert action('disable')[0]==200
        assert json.loads(action('logout',first,user)[1])['logout'] is False
        assert state()['authenticated'] # canceled countdown preserves session
        request(offline,'fixture.php')
        assert state(offline)['state']['phase']=='ended' # offline throughout event
        assert 'normal dashboard' in request(offline,'protected.php')[1]
        assert action('enable')[0]==200
        second=state()['state']['id']
        assert second!=first
        assert json.loads(action('logout',first,user)[1])['logout'] is False
        assert state()['authenticated'] # stale tab cannot log out second event
        assert action('disable',c=user)[0]==403
        assert request(user,'maintenance.php',{'action':'logout','csrf':'forged','event_id':second})[0]==403
        # Advance only the isolated fixture event, avoiding a 60-second sleep.
        event=state()['state']; event['effective_at']=int(time.time())-1
        event.pop('phase'); event.pop('server_time')
        value=json.dumps(event).replace("'","''")
        sql(f"UPDATE {DB}.system_settings SET setting_value='{value}' WHERE setting_key='maintenance_event'")
        assert state()['state']['phase']=='active'
        assert 'normal dashboard' not in request(user,'protected.php')[1]
        assert 'normal dashboard' in request(admin,'protected.php')[1]
        assert json.loads(action('logout',second,user)[1])['logout'] is True
        assert not state()['authenticated']
        assert action('disable')[0]==200
        request(user,'fixture.php')
        assert state()['state']['phase']=='ended'
        assert json.loads(action('logout',second,user)[1])['logout'] is False
        assert state()['authenticated'] # old tab cannot invalidate fresh login
        assert 'normal dashboard' in request(user,'protected.php')[1]
        # Explicit acknowledgment during warning.
        action('enable')
        assert json.loads(action('logout',c=user)[1])['logout'] is True
        action('disable')
        print('PASS: lifecycle, offline return, cancellation, active restrictions, admin access, CSRF, event identity, stale tabs and acknowledgment')
finally:
    if server:
        server.terminate(); server.wait(timeout=5)
    sql(f'DROP DATABASE IF EXISTS {DB}')
