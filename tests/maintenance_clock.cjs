// Deterministic monotonic-clock, transport-delay and cross-tab regression tests.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync('assets/js/maintenance.js','utf8');
const settle=()=>new Promise(resolve=>setImmediate(resolve));
function tab(deviceTime) {
    let now=0, wall=deviceTime, state={id:'one',enabled:true,phase:'warning',effective_at:1060,server_time:1000};
    let serverMs=1000100, block, fail=false, popup, polls=0, posts=0, starts=0;
    const timers=new Map(),listeners={},nodes={};
    const swal={getPopup:()=>popup,close:()=>popup=null,fire(){popup={classList:{contains:()=>true}};nodes['maintenance-notice-seconds']={textContent:''};return Promise.resolve({});}};
    class WallClock extends Date {static now(){return wall;}}
    const context={URL,URLSearchParams,console,Date:WallClock,performance:{now:()=>now},
        window:{Swal:swal,addEventListener:(n,f)=>listeners[n]=f},
        document:{baseURI:'https://test/gslip/dashboard.php',getElementById:id=>nodes[id]||null,visibilityState:'visible',addEventListener:(n,f)=>listeners[n]=f},
        navigator:{},location:{assign(){throw Error('Unexpected logout');}},
        setInterval:(fn,ms)=>{starts++;timers.set(ms,fn);return ms;},clearInterval:ms=>timers.delete(ms),
        fetch:async(url,options)=>{
            if(options.method==='POST'){posts++;return {ok:true,json:async()=>({success:true,logout:false})};}
            polls++;
            if(fail)throw Error('offline');
            if(block)await block;
            // First request has a measured 200ms round trip and midpoint server timestamp.
            if(now===0)now=200;
            return {ok:true,json:async()=>({state:{...state},server_time_ms:serverMs,authenticated:true,admin:false,csrf:'token'})};
        }};
    vm.runInNewContext(source,context);
    return {context,timers,listeners,nodes,swal,
        get polls(){return polls;},get posts(){return posts;},get starts(){return starts;},
        set now(value){now=value;},set wall(value){wall=value;},set state(value){state=value;},
        set serverMs(value){serverMs=value;},set fail(value){fail=value;},set block(value){block=value;},
        seconds:()=>Number(nodes['maintenance-notice-seconds'].textContent)};
}
(async()=>{
    const a=tab(0),b=tab(9999999999999);
    await settle();
    assert.equal(a.seconds(),60);assert.equal(b.seconds(),60,'wrong device dates must not affect countdown');
    a.now=1000;a.wall=-999999999999;a.timers.get(200)();
    assert.equal(a.seconds(),59,'device clock adjustment must not affect monotonic countdown');
    let release;a.block=new Promise(resolve=>release=resolve);
    const pending=a.timers.get(3000)();
    await settle();assert.equal(a.polls,2);
    a.now=7000;a.timers.get(200)();
    assert.equal(a.seconds(),53,'countdown keeps moving during delayed polling');
    await a.timers.get(3000)();a.listeners.focus();
    assert.equal(a.polls,2,'timer/focus must not overlap an outstanding poll');
    release();await pending;
    assert.equal(a.seconds(),53,'delayed response must not restart the countdown');
    assert.equal(a.starts,2);
    vm.runInNewContext(source,a.context);assert.equal(a.starts,2,'duplicate script must remain a singleton');
    a.now=45500;b.now=45500;
    a.listeners.visibilitychange();b.listeners.focus();await settle();
    assert.equal(a.seconds(),15);assert.equal(b.seconds(),15,'tabs independently converge on the same server deadline');
    a.fail=true;a.now=61000;a.timers.get(200)();await settle();
    assert.equal(a.seconds(),0);assert.equal(a.posts,0,'zero must verify server state before logout');
    a.fail=false;a.state={id:'one',enabled:false,phase:'ended',effective_at:1060,server_time:1061};
    await a.timers.get(3000)();assert.equal(a.timers.has(200),false);assert.equal(a.posts,0);
    a.serverMs=1061000;a.state={id:'two',enabled:true,phase:'warning',effective_at:1121,server_time:1061};
    await a.timers.get(3000)();assert.equal(a.seconds(),60,'a new event receives its own clock sample');
    assert.equal(a.starts,3,'only a new event starts a replacement display timer');
    console.log('PASS: measured RTT offset, wrong/jumping device clocks, 200ms updates, delayed polls, no timer resets/overlap, throttled/resumed tabs, duplicate loading, cancellation and zero-state validation');
})().catch(e=>{console.error(e);process.exitCode=1;});
