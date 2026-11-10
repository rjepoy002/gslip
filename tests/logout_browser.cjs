/* Isolated authentication_lifecycle.py server only; no live accounts. */
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {chromium}=require(process.env.GSLIP_UI_PLAYWRIGHT || 'playwright');
const base=process.env.GSLIP_AUTH_TEST_URL;
if(!base)throw Error('Disposable fixture URL required');
(async()=>{
 for(const channel of (process.env.GSLIP_UI_CHANNELS || 'chrome').split(',')){
  const browser=await chromium.launch({channel,headless:true});let checks=0;
  const check=(value,label)=>{assert.ok(value,channel+': '+label);checks++;};
  async function login(remember, mobile=false){
   const context=await browser.newContext({viewport:{width:mobile?375:1280,height:900},hasTouch:mobile,isMobile:mobile});
   const page=await context.newPage();await page.goto(base+'index.php');
   await page.locator('#username').fill('auth-user');await page.locator('#password').fill('Auth-test-123!');
   if(remember)await page.locator('#rememberMe').check();
   await Promise.all([page.waitForURL('**/dashboard.php'),page.locator('form[novalidate] button[type=submit]').click()]);
   await page.evaluate(()=>window.Swal?.close());
   return {page,context};
  }
  try{
   for(const [remember,mobile,sidebar] of [[false,false,'sidebar.php'],[true,true,'admin_sidebar.php']]){
    const {page,context}=await login(remember,mobile);
    const before=(await context.cookies()).find(c=>c.name==='gslip_remember');
    const oldSession=(await context.cookies()).find(c=>c.name==='PHPSESSID')?.value;
    const markup=fs.readFileSync(path.join(__dirname,'../includes',sidebar),'utf8').match(/<button type="button" id="logoutBtn"[\s\S]*?<\/button>/)[0];
    await page.evaluate(html=>{document.querySelector('.app-content')?.remove();document.querySelectorAll('#logoutBtn').forEach(el=>el.remove()); const wrapper=document.createElement('div');wrapper.style='position:fixed!important;top:8px!important;right:8px!important;z-index:2147483647!important';wrapper.innerHTML=html;wrapper.querySelector('button').style='position:fixed!important;top:8px!important;right:8px!important;z-index:2147483647!important';document.body.append(wrapper);window.logoutDialogs=0;window.legacyLogoutCalls=0;
     const fire=Swal.fire.bind(Swal);Swal.fire=options=>{if(options.title==='Logout Confirmation')window.logoutDialogs++;return fire(options);};
     document.getElementById('logoutBtn').addEventListener('click',()=>window.legacyLogoutCalls++);
    },markup);
    await page.addScriptTag({url:base+'assets/js/logout.js?v=fixture'});
    await page.addScriptTag({url:base+'assets/js/logout.js?v=duplicate-fixture'});
    const requests=[];
    page.on('request',r=>{if(new URL(r.url()).pathname.endsWith('/logout.php'))requests.push({method:r.method(),document:r.isNavigationRequest(),body:r.postData()});});
    check(await page.locator('#logoutBtn').count()===1,'unique fixture logout button');
    await page.locator('#logoutBtn').click({position:{x:5,y:5},timeout:5000});await page.locator('.swal2-cancel').click();
    check(requests.length===0,'Cancel makes no logout request');
    check(await page.evaluate(()=>window.logoutDialogs===1 && window.legacyLogoutCalls===0),'one SweetAlert handler suppresses cached legacy listeners and duplicate helper loads');
    check((await (await context.request.get(base+'probe.php')).json()).valid,'Cancel preserves valid session');
    check((await context.cookies()).find(c=>c.name==='gslip_remember')?.value===before?.value,'Cancel preserves remembered credential');
    await page.locator('#logoutBtn').click();
    check(await page.evaluate(()=>window.logoutDialogs===2),'one dialog per logout attempt');
    await Promise.all([page.waitForURL('**/index.php'),page.locator('.swal2-confirm').click()]);
    check(requests.filter(r=>r.method==='GET'&&!r.document).length===1,'CSRF form fetched in background once');
    check(requests.filter(r=>r.method==='POST'&&r.document).length===1,'one secure POST navigates logout endpoint');
    check(!requests.some(r=>r.method==='GET'&&r.document),'normal flow never opens intermediate HTML confirmation');
    check(new URLSearchParams(requests.find(r=>r.method==='POST').body).get('csrf')?.length===64,'POST includes CSRF token');
    check(!(await context.cookies()).some(c=>c.name==='gslip_remember') && (await context.cookies()).find(c=>c.name==='PHPSESSID')?.value!==oldSession,'logout removes remember credential and replaces authenticated session with anonymous Login session');
    check(!(await (await context.request.get(base+'probe.php')).json()).valid,'old authenticated session is terminated');
    if(before){
     const replay=await browser.newContext();await replay.addCookies([before]);const replayPage=await replay.newPage();await replayPage.goto(base+'index.php');
     check(replayPage.url().endsWith('/index.php'),'explicitly revoked remember token cannot authenticate');await replay.close();
    }
    await context.close();
   }
   {
    const {page,context}=await login(true);
    const cookieBefore=(await context.cookies()).find(c=>c.name==='gslip_remember').value;
    await page.evaluate(()=>{const b=document.createElement('button');b.id='logoutBtn';b.textContent='Logout';b.style='position:fixed;top:8px;right:8px;z-index:1090';document.body.append(b);});
    await page.addScriptTag({url:base+'assets/js/logout.js'});
    await page.route('**/logout.php',route=>route.abort());
    await page.locator('#logoutBtn').click();await page.locator('.swal2-confirm').click();
    await page.waitForFunction(()=>document.querySelector('.swal2-title')?.textContent==='Unable to log out');
    check(page.url().endsWith('/dashboard.php'),'failed CSRF fetch does not navigate to a second HTML confirmation');
    check((await (await context.request.get(base+'probe.php')).json()).valid,'failed CSRF fetch preserves session');
    check((await context.cookies()).find(c=>c.name==='gslip_remember').value===cookieBefore,'failed CSRF fetch preserves remember token');
    await context.close();
   }
   for(const route of ['logout.php','main.php?logout=1']){
    const {page,context}=await login(true);
    await page.goto(base+route);
    check(await page.locator('h4').textContent()==='Logout Confirmation','direct/legacy navigation retains safe HTML fallback');
    check((await (await context.request.get(base+'probe.php')).json()).valid,'GET fallback does not terminate session');
    await Promise.all([page.waitForURL('**/index.php'),page.locator('button[type=submit]').click()]);
    check(!(await context.cookies()).some(c=>c.name==='gslip_remember'),'fallback POST revokes and clears remembered credential');await context.close();
   }
   console.log(`${channel}: ${checks} logout browser checks passed (sidebar/mobile, Cancel, secure POST, fallback, legacy and replay).`);
  }finally{await browser.close();}
 }
})().catch(error=>{console.error(error);process.exitCode=1;});
