const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
let state = {id:'first',enabled:true,phase:'warning',effective_at:160,server_time:100,reason:'Updates'};
let online=true, admin=false, posts=[], redirects=[], closed=0, modals=[], popup=null, now=100000, acceptLogout=false;
const intervals=new Map();
const listeners={};
let intervalStarts=0;
const nodes={};
const swal={
    getPopup:()=>popup,
    close(){popup=null;closed++;},
    fire(options){
        modals.push(options); popup={classList:{contains:name=>name==='maintenance-notice-modal'}};
        nodes['maintenance-notice-seconds']={textContent:''};
        nodes['maintenance-notice-reason']={textContent:'',hidden:false};
        return Promise.resolve({});
    },
    showValidationMessage(){}
};
class Clock extends Date { static now(){return now;} }
const context = {
    URL,URLSearchParams,console,Date:Clock,
    document:{baseURI:'https://example.test/gslip/dashboard.php',getElementById:id=>nodes[id]||null,addEventListener:(name,fn)=>listeners[name]=fn,visibilityState:'visible'},
    window:{Swal:swal,addEventListener:(name,fn)=>listeners[name]=fn},
    performance:{now:()=>now},
    location:{assign:url=>redirects.push(String(url))},
    setInterval:(f,ms)=>{intervalStarts++;intervals.set(ms,f);return ms;},
    clearInterval:ms=>intervals.delete(ms),
    navigator:{serviceWorker:{getRegistration:async()=>({getNotifications:async()=>[
        {data:{type:'maintenance',event_id:'old'},close(){}},
        {data:{type:'approved'},close(){throw Error('Unrelated notification closed');}}
    ]})}},
    fetch:async(url,options)=>{
        if(!online) throw Error('offline');
        if(options.method==='POST'){
            posts.push(Object.fromEntries(options.body));
            return {ok:true,json:async()=>({success:true,logout:acceptLogout})};
        }
        return {ok:true,json:async()=>({state:{...state},authenticated:true,admin,csrf:'token'})};
    }
};
const settle=()=>new Promise(resolve=>setImmediate(resolve));
(async()=>{
    vm.runInNewContext(fs.readFileSync('assets/js/maintenance.js','utf8'),context);
    await settle();
    assert.equal(modals.length,1);
    assert.equal(nodes['maintenance-notice-seconds'].textContent,'60');
    assert.equal(modals[0].position,'center');
    assert.equal(modals[0].backdrop,'rgba(0, 0, 0, 0.65)');
    assert.equal(modals[0].allowOutsideClick,false);
    assert.equal(modals[0].allowEscapeKey,false);
    assert.equal(modals[0].showCloseButton,false);
    await intervals.get(3000)();
    assert.equal(modals.length,1,'polls must not duplicate modals');
    now+=1000; intervals.get(200)();
    assert.equal(nodes['maintenance-notice-seconds'].textContent,'59');
    assert.equal(posts.length,0);
    // Same-event polls, even with old server samples, cannot restart the countdown.
    now+=7000;
    await intervals.get(3000)();
    assert.equal(nodes['maintenance-notice-seconds'].textContent,'52');
    assert.equal(intervalStarts,2,'one polling timer and one display timer');
    vm.runInNewContext(fs.readFileSync('assets/js/maintenance.js','utf8'),context);
    assert.equal(intervalStarts,2,'duplicate script must not create timers');
    now+=2000; listeners.visibilitychange(); await settle();
    assert.equal(nodes['maintenance-notice-seconds'].textContent,'50');
    state={...state,enabled:false,phase:'ended'};
    await intervals.get(3000)();
    assert.equal(popup,null);
    assert.equal(intervals.has(200),false,'cancellation stops the display timer');
    assert.equal(closed,1);
    assert.equal(posts.length,0,'cancellation must not request logout');
    await intervals.get(3000)();
    assert.equal(modals.length,1,'expired notices must not reappear');
    state={...state,id:'second',enabled:true,phase:'warning'};
    await intervals.get(3000)();
    // A late callback from the first modal must retain its original event ID.
    await modals[0].preConfirm();
    assert.equal(posts[0].event_id,'first');
    assert.equal(redirects.length,0,'server-rejected stale event preserves session');
    online=false;
    now+=60000; intervals.get(200)(); await settle();
    assert.equal(posts.length,1,'network loss must not cause local deadline logout');
    online=true; state={...state,phase:'active',server_time:160}; acceptLogout=true;
    await intervals.get(3000)();
    assert.equal(posts[1].event_id,'second');
    assert.equal(redirects.pop(),'https://example.test/gslip/maintenance.php');
    admin=true; state={...state,phase:'warning'};
    await intervals.get(3000)();
    assert.equal(popup,null,'admin must not see forced logout modal');
    assert.equal(posts.length,2);
    console.log('PASS: centered modal, backdrop, dismissal locks, live countdown, no duplicates, cancellation, stale events, offline safety, automatic logout redirect and admin exemption');
})().catch(e=>{console.error(e);process.exitCode=1;});
