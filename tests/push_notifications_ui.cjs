const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('assets/js/push-notifications.js','utf8');
let checks = 0;
const check = (value,label) => { assert.ok(value,label); checks++; };
const settle = async () => { for(let i=0;i<8;i++) await new Promise(resolve=>setImmediate(resolve)); };

async function fixture(options={}) {
  const calls=[],errors=[],handlers={};
  const button={dataset:{pushCsrf:'fixture-csrf'},disabled:false,textContent:'Enable Notifications',addEventListener:(name,fn)=>handlers[name]=fn};
  const status={textContent:''};
  let current=null;
  const subscription={endpoint:'https://push.example.invalid/device',toJSON:()=>({endpoint:subscription.endpoint,keys:{p256dh:'a'.repeat(87),auth:'b'.repeat(22)}}),unsubscribe:async()=>{current=null;calls.push('unsubscribe');return true;}};
  const registration={pushManager:{getSubscription:async()=>current,subscribe:async()=>{calls.push('subscribe');return current=subscription;}}};
  const Notification={permission:options.permission||'default',requestPermission:async()=>{calls.push('permission');return Notification.permission=options.permissionResult||'granted';}};
  const navigator={serviceWorker:{register:async(url,opts)=>{calls.push({worker:url,scope:opts.scope});if(options.workerFailure)throw Error('worker unavailable');return registration;},ready:Promise.resolve(registration)}};
  const window={location:{href:'https://example.test/gslip/dashboard.php'},isSecureContext:options.secure!==false,Notification,PushManager:class {},atob:s=>Buffer.from(s,'base64').toString('binary')};
  if(options.unsupported)delete window.PushManager;
  const document={readyState:'complete',currentScript:{src:'https://example.test/gslip/assets/js/push-notifications.js'},getElementById:id=>id==='pushNotificationButton'?button:status};
  const fetch=async(url,init)=>{
    calls.push({url,init});
    if(options.networkFailure)throw new TypeError('Failed to fetch');
    const failure=options.failure && url.endsWith(options.failure.route);
    const httpStatus=failure?options.failure.status:200;
    return {ok:httpStatus===200,status:httpStatus,json:async()=>{
      if(options.invalidJson)throw new SyntaxError('invalid JSON');
      return failure ? {success:false,message:options.failure.message} : {success:true,public_key:Buffer.alloc(65,1).toString('base64url')};
    }};
  };
  vm.runInNewContext(source,{window,document,navigator,Notification,fetch,URL,Uint8Array,
    sessionStorage:{getItem:()=>null,setItem:()=>{}},console:{error:(...args)=>errors.push(args)}});
  await settle();
  return {window,button,status,calls,errors,click:async()=>{await handlers.click();await settle();}};
}

(async()=>{
  let f=await fixture();
  check(f.status.textContent==='Not enabled','initial preference not enabled');
  await f.click();
  check(f.status.textContent==='Enabled' && f.button.textContent==='Disable Notifications','enable saves preference');
  const save=f.calls.find(c=>c.url?.endsWith('/api/save_push_subscription.php'));
  check(save.init.credentials==='same-origin','authenticated same-origin fetch retained');
  check(save.init.headers['X-CSRF-Token']==='fixture-csrf' && save.init.headers.Accept==='application/json','save carries CSRF and JSON Accept');
  check(JSON.parse(save.init.body).endpoint==='https://push.example.invalid/device','subscription payload preserved');
  check(f.calls.find(c=>c.worker)?.scope==='/gslip/','worker scope and path preserved');
  await f.click();
  check(f.status.textContent==='Not enabled' && f.button.textContent==='Enable Notifications','disable updates UI');
  check(f.calls.some(c=>c.url?.endsWith('/api/remove_push_subscription.php') && c.init.headers['X-CSRF-Token']==='fixture-csrf'),'disable carries CSRF');
  f=await fixture({permissionResult:'denied'});await f.click();
  check(f.status.textContent.includes('blocked') && f.button.disabled,'denied permission has actionable message');
  check(!f.calls.some(c=>c.url),'permission denial makes no server writes');
  f=await fixture({permissionResult:'default'});await f.click();
  check(f.status.textContent.includes('not granted') && !f.button.disabled,'dismissed prompt can be retried');
  f=await fixture({unsupported:true});
  check(f.status.textContent.includes('supported browser') && f.button.disabled,'unsupported browser message');
  f=await fixture({secure:false});
  check(f.status.textContent.includes('HTTPS') && f.button.disabled,'insecure context message');
  f=await fixture({networkFailure:true});await f.click();
  check(f.status.textContent.includes('Check your connection') && !f.button.disabled,'network failure message and retry');
  f=await fixture({invalidJson:true});await f.click();
  check(f.status.textContent.includes('unexpected response'),'invalid HTML/non-JSON server response is explained');
  f=await fixture({workerFailure:true});await f.click();
  check(f.status.textContent.includes('service worker could not start'),'worker registration error explained');
  for(const [status,message] of [[503,'Server Web Push configuration is missing.'],[401,'Your session has expired. Sign in again.'],[403,'This page has expired. Reload it.'],[500,'The server could not update notifications. Please try again.']]){
    f=await fixture({failure:{route:'push_public_key.php',status,message}});await f.click();
    check(f.status.textContent===message,`HTTP ${status} actionable message retained`);
    check(!f.calls.includes('subscribe'),`HTTP ${status} does not create orphan browser subscription`);
    check(!JSON.stringify(f.errors).includes('push.example.invalid'),'console diagnostics omit device endpoint');
  }
  f=await fixture({failure:{route:'save_push_subscription.php',status:500,message:'The server could not save notification settings. Please try again.'}});await f.click();
  check(f.status.textContent.includes('server could not save'),'subscription write failure message retained');
  console.log(`${checks} push JavaScript checks passed (permission, worker, CSRF, enable/disable and failure messages).`);
})().catch(error=>{console.error(error);process.exitCode=1;});
