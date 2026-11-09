// Deterministic real-browser modal checks; no database or application writes.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const {chromium} = require(process.env.GSLIP_UI_PLAYWRIGHT || 'playwright');
const root = path.resolve(__dirname,'..');
const shots = fs.mkdtempSync(path.join(os.tmpdir(),'gslip-maintenance-notice-'));
const base='https://maintenance.test/gslip/';
const html=`<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="assets/css/bootstrap.min.css"><link rel="stylesheet" href="assets/css/sweetalert2.min.css"><link rel="stylesheet" href="assets/css/style.css"><link rel="stylesheet" href="assets/css/maintenance.css"><link rel="stylesheet" href="assets/css/mobile.css">
<script src="assets/js/sweetalert2.all.min.js"></script></head><body>
<aside class="app-sidebar"><h2>e-GSlip</h2><nav><a href="dashboard.php">Dashboard</a></nav></aside>
<div class="app-content"><main class="main-content"><h1>Dashboard</h1><p>Overview of gas slip requests</p><div class="card p-4">Pending Gas Slips</div><span id="maintenance-activated"></span></main></div>
<script src="assets/js/maintenance.js" defer></script></body></html>`;
(async()=>{
    const browser=await chromium.launch({headless:true,channel:'msedge'});
    try {
        const page=await browser.newPage({viewport:{width:1280,height:900}});
        await page.clock.install();
        let enabled=true,admin=false,id='first',phase='warning',remaining=60,posts=[],errors=[];
        page.on('pageerror',e=>errors.push(e.message));
        await page.route(base+'**',async route=>{
            const url=new URL(route.request().url());
            if(url.searchParams.has('state')) return route.fulfill({json:{state:{id,enabled,phase,reason:'Applying system updates <script>unsafe</script>',effective_at:1794206820,server_time:1794206820-remaining},admin,authenticated:true,csrf:'token'}});
            if(route.request().method()==='POST') {
                const data=Object.fromEntries(new URLSearchParams(route.request().postData())); posts.push(data);
                return route.fulfill({json:{success:true,logout:enabled && !admin && data.event_id===id}});
            }
            if(url.pathname.endsWith('.php')) return route.fulfill({contentType:'text/html',body:url.pathname.endsWith('maintenance.php')?'<h1>System Maintenance</h1>':html});
            const filename=path.join(root,decodeURIComponent(url.pathname.replace('/gslip/','')));
            if(filename.startsWith(root+path.sep)&&fs.existsSync(filename)) return route.fulfill({path:filename,contentType:filename.endsWith('.css')?'text/css':filename.endsWith('.js')?'application/javascript':undefined});
            return route.fulfill({status:404,body:''});
        });
        await page.goto(base+'dashboard.php');
        const popup=page.locator('.maintenance-notice-modal');
        await popup.waitFor({state:'visible'});
        await page.clock.runFor(350);
        const rect=await popup.boundingBox();
        assert.ok(Math.abs(rect.x+rect.width/2-640)<3);
        assert.ok(Math.abs(rect.y+rect.height/2-450)<3);
        await page.waitForFunction(()=>getComputedStyle(document.querySelector('.maintenance-notice-container')).backgroundColor==='rgba(0, 0, 0, 0.65)');
        assert.equal(await page.locator('.app-sidebar').isVisible(),true);
        assert.match(await page.locator('#maintenance-notice-reason').innerText(),/<script>unsafe<\/script>/);
        assert.equal(await popup.locator('script').count(),0,'reason must be text, not executable markup');
        await page.keyboard.press('Escape');
        await page.mouse.click(5,5);
        assert.equal(await popup.isVisible(),true);
        assert.equal(await popup.locator('.swal2-close').isVisible(),false);
        remaining=59;
        await page.clock.runFor(1100);
        await page.waitForFunction(()=>Number(document.getElementById('maintenance-notice-seconds').textContent)<60);
        assert.ok(Number(await page.locator('#maintenance-notice-seconds').innerText())<=59);
        assert.equal(await page.locator('#maintenance-activated').innerText(),'Nov 9, 2026, 2:46 PM');
        assert.equal(await page.locator('.maintenance-notice-modal').count(),1);
        await page.screenshot({path:path.join(shots,'desktop.png'),fullPage:true});
        await page.setViewportSize({width:390,height:844});
        await page.waitForTimeout(400);
        await page.screenshot({path:path.join(shots,'mobile.png'),fullPage:true});
        assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
        enabled=false;phase='ended';
        await page.evaluate(()=>window.dispatchEvent(new Event('focus')));
        await page.waitForFunction(()=>!document.querySelector('.maintenance-notice-modal'));
        assert.equal(posts.length,0,'canceled countdown must not log out');
        await page.clock.runFor(3000);
        assert.equal(await popup.count(),0,'expired event must not reappear');
        id='second';enabled=true;phase='warning';remaining=60;
        await page.evaluate(()=>window.dispatchEvent(new Event('focus')));
        await popup.waitFor({state:'visible'});
        await page.locator('.swal2-confirm').click();
        await page.waitForURL(base+'maintenance.php');
        assert.equal(posts.length,1); assert.equal(posts[0].event_id,'second');
        id='third';remaining=60;
        await page.goto(base+'dashboard.php'); await popup.waitFor({state:'visible'});
        remaining=1; await page.clock.fastForward(59000);
        await page.waitForFunction(()=>document.getElementById('maintenance-notice-seconds').textContent==='1');
        remaining=0;phase='active';await page.clock.runFor(1000);
        await page.waitForURL(base+'maintenance.php');
        assert.equal(posts.length,2); assert.equal(posts[1].event_id,'third');
        admin=true;phase='warning';remaining=60;
        await page.goto(base+'dashboard.php');await page.clock.runFor(3000);
        assert.equal(await popup.count(),0);assert.equal(posts.length,2);
        assert.deepEqual(errors,[]);
        console.log('PASS: centering/backdrop, locked dismissal, safe reason, countdown, canceled/expired events, acknowledgment/automatic logout, admin exemption, date format and mobile layout');
        console.log('Screenshots: '+shots);
    } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
