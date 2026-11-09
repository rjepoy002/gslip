// Real browser layout checks; HTTP is intercepted and no maintenance settings are written.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const {execFileSync} = require('node:child_process');
const {chromium} = require(process.env.GSLIP_UI_PLAYWRIGHT || 'playwright');
const root = path.resolve(__dirname, '..');
const php = process.env.GSLIP_TEST_PHP || 'C:/xampp/php/php.exe';
const screenshotDir = fs.mkdtempSync(path.join(os.tmpdir(), 'gslip-maintenance-layout-'));
function html(admin) {
    const script = `require 'includes/config.php'; $conn=getDBConnection();
        $admin=${admin ? 'true' : 'false'}; $_SERVER['PHP_SELF']='/gslip/maintenance.php';
        $row=$conn->query("SELECT * FROM users WHERE role='admin' AND status='active' LIMIT 1")->fetch_assoc();
        if (!$row) throw new RuntimeException('A local admin is required for read-only sidebar rendering.');
        $_SESSION=['user_id'=>$row['id'],'role'=>'admin','department_id'=>$row['department_id'],'area'=>'Main Office','fullname'=>'System Administrator'];
        $state=['enabled'=>false,'reason'=>''];
        $source=file_get_contents('maintenance.php');
        eval('?>'.substr($source,strpos($source,'?>')+2));`;
    return execFileSync(php, ['-r', script], {cwd:root,encoding:'utf8'});
}
(async()=>{
    const browser = await chromium.launch({headless:true,channel:'msedge'});
    let page;
    try {
        page = await browser.newPage();
        let admin=true, phase='ended', enabled=false, posts=[], faults=[];
        const rendered = {admin:html(true),guest:html(false)};
        page.on('pageerror', e=>faults.push(e.message));
        await page.route('https://maintenance.test/**', async route=>{
            const url = new URL(route.request().url());
            if (url.pathname.endsWith('/maintenance.php')) {
                if (route.request().method()==='POST') {
                    const body = Object.fromEntries(new URLSearchParams(route.request().postData()));
                    posts.push(body);
                    enabled=body.action==='enable'; phase=enabled?'warning':'ended';
                    return route.fulfill({json:{success:true,logout:false}});
                }
                if(url.searchParams.has('state')) return route.fulfill({json:{state:{id:'test',enabled,phase,reason:'Scheduled system update',effective_at:1794206820,server_time:1794206760},admin,authenticated:admin,csrf:'test-csrf'}});
                return route.fulfill({contentType:'text/html',body:rendered[admin?'admin':'guest']});
            }
            const filename=path.join(root,decodeURIComponent(url.pathname.replace('/gslip/','')));
            if (filename.startsWith(root+path.sep) && fs.existsSync(filename) && fs.statSync(filename).isFile()) {
                const type=filename.endsWith('.css')?'text/css':filename.endsWith('.js')?'application/javascript':undefined;
                return route.fulfill({path:filename,contentType:type});
            }
            return route.fulfill({json:{success:true,notifications:[],unread_count:0}});
        });
        await page.goto('https://maintenance.test/gslip/maintenance.php');
        await page.waitForFunction(()=>document.getElementById('maintenance-badge').textContent==='Operational');
        assert.equal(await page.locator('.app-sidebar').count(),1);
        assert.equal(await page.locator('.nav-link.active[href="maintenance.php"]').count(),1);
        assert.equal(await page.locator('script[src*="maintenance.js"]').count(),1);
        const enable=page.locator('button[value="enable"]');
        await enable.click();
        await page.locator('.swal2-cancel').click();
        assert.equal(posts.length,0,'Cancel does not write settings');
        await page.locator('#maintenance-reason-input').fill('Scheduled system update');
        await enable.click(); await page.locator('.swal2-confirm').click();
        await page.waitForFunction(()=>!document.getElementById('maintenance-warning').hidden);
        assert.equal(posts.length,1);
        assert.equal(posts[0].reason,'Scheduled system update');
        assert.equal(await enable.isDisabled(),true);
        assert.match(await page.locator('#maintenance-countdown').innerText(),/\d+\s*s/);
        await page.waitForFunction(()=>!document.querySelector('.swal2-container'));
        await page.waitForTimeout(350);
        await page.screenshot({path:path.join(screenshotDir,'admin-light.png'),fullPage:true});
        await page.evaluate(()=>document.body.classList.add('dark-mode'));
        await page.screenshot({path:path.join(screenshotDir,'admin-dark.png'),fullPage:true});
        await page.setViewportSize({width:390,height:844});
        await page.waitForTimeout(400);
        await page.screenshot({path:path.join(screenshotDir,'admin-mobile.png'),fullPage:true});
        assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Mobile layout must not overflow');
        await page.locator('button[value="disable"]').click(); await page.locator('.swal2-confirm').click();
        await page.waitForFunction(()=>document.getElementById('maintenance-badge').textContent==='Operational');
        assert.equal(posts.length,2);
        assert.equal(await page.locator('#maintenance-summary.is-completed').count(),1);
        assert.equal(await page.locator('#maintenance-summary-icon.fa-circle-check').count(),1);
        assert.equal(await page.locator('#maintenance-completion-heading').innerText(),'System Maintenance Completed.');
        assert.equal(await page.locator('#maintenance-status').innerText(),'GSLIP is now available. You may log in to continue using the system.');
        assert.equal(await page.locator('#maintenance-warning').isVisible(),false);
        await page.waitForFunction(()=>!document.querySelector('.swal2-container'));
        await page.screenshot({path:path.join(screenshotDir,'completed-dark.png'),fullPage:true});
        await page.evaluate(()=>document.body.classList.remove('dark-mode'));
        await page.screenshot({path:path.join(screenshotDir,'completed-light.png'),fullPage:true});

        admin=false; enabled=true; phase='active';
        await page.goto('https://maintenance.test/gslip/maintenance.php');
        await page.waitForFunction(()=>document.getElementById('maintenance-badge').textContent==='Active');
        assert.equal(await page.locator('.app-sidebar,#maintenance-controls,a[href="dashboard.php"]').count(),0);
        assert.equal(await page.locator('#maintenance-acknowledge').isVisible(),false);
        await page.screenshot({path:path.join(screenshotDir,'guest-mobile.png'),fullPage:true});
        assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));

        enabled=false; phase='ended';
        await page.evaluate(()=>window.dispatchEvent(new Event('focus')));
        await page.waitForFunction(()=>document.getElementById('maintenance-badge').textContent==='Operational');
        assert.equal(await page.locator('a[href="index.php"]').innerText(),'Return to Login');
        assert.equal(await page.locator('#maintenance-summary.is-completed').count(),1);
        assert.equal(await page.locator('.maintenance-notice-modal').count(),0);
        assert.deepEqual(faults,[]);
        console.log('PASS: actual sidebar/highlight, single polling script, confirmations/cancel, reason, countdown, buttons, light/dark/mobile, safe guest layout, no runtime errors');
        console.log('Screenshots: '+screenshotDir);
    } finally { await browser.close(); }
})().catch(e=>{console.error(e);process.exitCode=1;});
