const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const events = {};
let active = false, currentId = 'second', shown = [], navigated = [];
const context = {
    URL, console,
    fetch: async () => ({ok:true,json:async()=>({state:{enabled:active,id:currentId}})}),
    clients: {matchAll:async()=>[],openWindow:async url=>navigated.push(url)},
    self: {
        addEventListener:(name,callback)=>events[name]=callback,
        registration:{scope:'https://example.test/gslip/',showNotification:async(title,options)=>shown.push({title,...options})}
    }
};
vm.runInNewContext(fs.readFileSync('sw.js','utf8'),context);
async function push(payload) {
    let done;
    events.push({data:{json:()=>payload},waitUntil:p=>done=p});
    await done;
}
async function click(data) {
    let done;
    events.notificationclick({notification:{data,close(){}},waitUntil:p=>done=p});
    await done;
}
(async()=>{
    await push({type:'maintenance',event_id:'first'});
    assert.equal(shown.length,0,'ended event must not display');
    active=true;
    await push({type:'maintenance',event_id:'first'});
    assert.equal(shown.length,0,'old event must not display during new event');
    await push({type:'maintenance',event_id:'second',url:'logout.php'});
    assert.equal(shown.length,1);
    assert.equal(shown[0].data.url,'maintenance.php');
    active=false;
    await click({type:'maintenance',event_id:'second',url:'logout.php'});
    assert.equal(navigated.pop(),'https://example.test/gslip/dashboard.php');
    active=true;
    await click({type:'maintenance',event_id:'second',url:'logout.php'});
    assert.equal(navigated.pop(),'https://example.test/gslip/maintenance.php');
    context.fetch=async()=>{throw Error('offline');};
    await push({type:'maintenance',event_id:'second'});
    assert.equal(shown.length,1,'unverifiable maintenance must not display');
    await push({type:'approved',title:'Gas slip approved',url:'approved_slips.php',gas_slip_id:123});
    assert.equal(shown.length,2,'ordinary gas slip push still works offline');
    assert.equal(shown[1].data.gas_slip_id,123);
    await click(shown[1].data);
    assert.equal(navigated.pop(),'https://example.test/gslip/approved_slips.php');
    console.log('PASS: delayed/expired push, consecutive events, safe old clicks, network failure, normal gas slip push');
})().catch(e=>{console.error(e);process.exitCode=1;});
