/* Isolated authentication_lifecycle.py fixture only; push transport is stubbed.
   Real installed browsers, HTTP endpoints and service worker remain in use. */
const assert=require('node:assert/strict');
const {chromium}=require(process.env.GSLIP_UI_PLAYWRIGHT || 'playwright');
const base=process.env.GSLIP_AUTH_TEST_URL;
if(!base)throw Error('Disposable fixture URL required');
(async()=>{
 for(const channel of (process.env.GSLIP_UI_CHANNELS || 'chrome').split(',')){
  const browser=await chromium.launch({channel,headless:true});let checks=0;
  const check=(value,label)=>{assert.ok(value,channel+': '+label);checks++;};
  try{
   for(const remember of [false,true]){
    const context=await browser.newContext();
    await context.grantPermissions(['notifications'],{origin:new URL(base).origin});
    const page=await context.newPage();
    await page.goto(base+'index.php');await page.locator('#username').fill('auth-user');await page.locator('#password').fill('Auth-test-123!');
    if(remember)await page.locator('#rememberMe').check();
    await Promise.all([page.waitForURL('**/dashboard.php'),page.locator('form[novalidate] button[type=submit]').click()]);
    if(remember){await context.request.get(base+'expire.php');check((await page.goto(base+'dashboard.php')).status()===200,'restored session reaches dashboard');}
    const keyResponse=await context.request.get(base+'api/push_public_key.php');const payload=await keyResponse.json();
    check(keyResponse.status()===200 && !!payload.csrf,'authenticated public-key and CSRF response');
    await page.evaluate(({csrf})=>{
      window.egslipPushPromptOnLogin=false; // Test the Profile control directly.
      window.Swal?.close();
      const button=document.createElement('button');button.id='pushNotificationButton';button.dataset.pushCsrf=csrf;
      const status=document.createElement('span');status.id='pushNotificationStatus';document.body.append(button,status);
      let current=null;
      const registration=navigator.serviceWorker.register.bind(navigator.serviceWorker);
      navigator.serviceWorker.register=async(...args)=>{
        const worker=await registration(...args);
        const fake={endpoint:'https://push.example.invalid/browser-fixture',toJSON:()=>({endpoint:fake.endpoint,keys:{p256dh:'a'.repeat(87),auth:'b'.repeat(22)}}),unsubscribe:async()=>{current=null;return true;}};
        worker.pushManager.getSubscription=async()=>current;
        worker.pushManager.subscribe=async()=>current=fake;
        return worker;
      };
    },{csrf:payload.csrf});
    await page.addScriptTag({url:base+'assets/js/push-notifications.js'});
    await page.waitForFunction(()=>document.getElementById('pushNotificationStatus').textContent==='Not enabled');
    await page.locator('#pushNotificationButton').click();
    await page.waitForFunction(()=>document.getElementById('pushNotificationStatus').textContent==='Enabled');
    check(await page.locator('#pushNotificationButton').textContent()==='Disable Notifications','browser enables through real save endpoint');
    if (await page.locator('.swal2-confirm').isVisible()) await page.locator('.swal2-confirm').click();
    const workerStatus=await page.evaluate(async()=>{const r=await navigator.serviceWorker.ready;return {state:r.active?.state,scope:r.scope};});
    check(workerStatus.state==='activated' && workerStatus.scope===base,'actual worker activated with application scope');
    const delivered=await page.evaluate(async()=>{
      const registration=await navigator.serviceWorker.ready;
      await registration.showNotification('GSLIP isolated test',{body:'Local service-worker delivery check',tag:'gslip-fixture'});
      const notices=await registration.getNotifications({tag:'gslip-fixture'});
      const present=notices.some(n=>n.title==='GSLIP isolated test');notices.forEach(n=>n.close());return present;
    });
    check(delivered,'local service-worker notification is delivered and closed');
    await page.locator('#pushNotificationButton').click();
    await page.waitForFunction(()=>document.getElementById('pushNotificationStatus').textContent==='Not enabled');
    check(await page.locator('#pushNotificationButton').textContent()==='Enable Notifications','browser disables through real remove endpoint');
    await context.close();
   }
   console.log(`${channel}: ${checks} push browser checks passed (normal/restored sessions, actual worker/local notification; mocked remote push transport).`);
  }finally{await browser.close();}
 }
})().catch(error=>{console.error(error);process.exitCode=1;});
