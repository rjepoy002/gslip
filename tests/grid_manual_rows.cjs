const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {chromium}=require(process.env.GSLIP_UI_PLAYWRIGHT||'playwright');
const root=path.resolve(__dirname,'..');
const create=fs.readFileSync(path.join(root,'create_gas_slip.php'),'utf8');
// Execute the actual Add Row/default-date/renumber functions from the page.
const start=create.indexOf("  document.getElementById('btnAddRow')");
const end=create.indexOf('</script>',start);
const rowHandlers=create.slice(start,end);
(async()=>{
 const browser=await chromium.launch({channel:'chrome',headless:true});
 try{
  const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.setContent(`<button id="btnAddRow">Add Row</button><button id="outside">Outside</button><table id="gasSlipGrid"><tbody><tr><td><span class="row-number">1</span><button class="remove-row">Remove</button></td><td><input name="validity_until[]" type="date" value="2026-11-06"></td><td><input name="purpose[]" value=""></td><td><input name="requested_by[]" value="Original requester"><input class="destinations-data" type="hidden" value="[]"><input class="fuel-data" type="hidden" value="[]"></td></tr></tbody></table>`);
  await page.evaluate(()=>{window.prompts=[];window.confirmBelow=true;window.hold=false;window.Swal={fire:options=>{prompts.push(options);return hold?new Promise(resolve=>window.finishPrompt=resolve):Promise.resolve({isConfirmed:confirmBelow});}};});
  await page.addScriptTag({path:path.join(root,'assets/js/grid.js')});
  await page.addScriptTag({content:rowHandlers});
  for(let i=0;i<3;i++)await page.click('#btnAddRow');
  assert.equal(await page.locator('#gasSlipGrid tbody tr').count(),4);
  assert.equal(await page.evaluate(()=>prompts.length),0,'Actual Add Row initialization is silent');
  const unrelated=()=>page.locator('#gasSlipGrid input:not([name="purpose[]"]):not([name="validity_until[]"])').evaluateAll(inputs=>inputs.map(input=>input.value));
  const originalOtherFields=await unrelated();
  const fields=name=>page.locator(`[name="${name}[]"]`);
  const values=name=>fields(name).evaluateAll(inputs=>inputs.map(i=>i.value));
  // Reproduce picker focus restoration between input and committed change.
  await fields('validity_until').first().focus();
  await fields('validity_until').first().evaluate(input=>{
   input.value='2026-12-01';input.dispatchEvent(new Event('input',{bubbles:true}));
   input.dispatchEvent(new FocusEvent('focusin',{bubbles:true}));
   input.dispatchEvent(new Event('change',{bubbles:true}));
  });
  await page.waitForFunction(()=>document.querySelectorAll('[name="validity_until[]"]')[3].value==='2026-12-01');
  assert.deepEqual(await values('validity_until'),Array(4).fill('2026-12-01'));
  assert.equal(await page.evaluate(()=>prompts.length),1,'Date picker commit prompts exactly once');
  assert.deepEqual(await unrelated(),originalOtherFields,'Date propagation leaves requesters, destinations and fuel data unchanged');
  await fields('validity_until').first().dispatchEvent('change');
  assert.equal(await page.evaluate(()=>prompts.length),1,'Repeated change does not duplicate the prompt');
  await page.evaluate(()=>confirmBelow=false);
  await fields('purpose').first().fill('Individual purpose');
  assert.equal(await page.evaluate(()=>prompts.length),1,'Purpose keystrokes do not prompt');
  await page.click('#outside');
  assert.equal(await page.evaluate(()=>prompts.length),2,'Purpose blur prompts once');
  assert.deepEqual(await values('purpose'),['Individual purpose','','','']);
  await page.evaluate(()=>confirmBelow=true);
  await fields('purpose').nth(1).fill('Rows below two');await page.click('#outside');
  assert.deepEqual(await values('purpose'),['Individual purpose','Rows below two','Rows below two','Rows below two']);
  assert.deepEqual(await unrelated(),originalOtherFields,'Purpose propagation leaves unrelated form values unchanged');
  const beforeLast=await page.evaluate(()=>prompts.length);
  await fields('purpose').nth(3).fill('Last row');await page.click('#outside');
  assert.equal(await page.evaluate(()=>prompts.length),beforeLast,'Last row does not prompt');
  // An open confirmation must not be applied against a changed row structure.
  for(const mutation of ['remove','reorder','edit']){
   await page.evaluate(()=>hold=true);
   await fields('purpose').first().fill('Pending '+mutation);await page.click('#outside');
   const expected=await page.evaluate(mutation=>{
    const body=document.querySelector('#gasSlipGrid tbody');
    if(mutation==='remove')body.lastElementChild.remove();
    if(mutation==='reorder')body.insertBefore(body.lastElementChild,body.firstElementChild);
    if(mutation==='edit'){
     const input=body.firstElementChild.querySelector('[name="purpose[]"]');
     input.value='Newer edit';input.dispatchEvent(new Event('input',{bubbles:true}));
    }
    const expected=[...body.querySelectorAll('[name="purpose[]"]')].map(input=>input.value);
    finishPrompt({isConfirmed:true});hold=false;
    return expected;
   },mutation);
   await page.evaluate(()=>new Promise(resolve=>setTimeout(resolve,0)));
   const after=await values('purpose');
   assert.deepEqual(after,expected,'Stale '+mutation+' confirmation did not overwrite rows');
  }
  // Actual template row builder uses the same global handlers with no template gate.
  await page.addScriptTag({path:path.join(root,'assets/js/template_loader.js')});
  await page.evaluate(()=>{
   const body=document.querySelector('#gasSlipGrid tbody'),base=body.firstElementChild.cloneNode(true);
   body.replaceChildren(...[0,1,2].map(i=>createTemplateRow({purpose:'Template '+i,destinations:[],fuel_requests:[]},i,base)));
  });
  const afterLoad=await page.evaluate(()=>prompts.length);
  await fields('purpose').first().fill('Template changed');await page.click('#outside');
  assert.deepEqual(await values('purpose'),Array(3).fill('Template changed'));
  await page.click('#btnAddRow');await page.click('#btnAddRow');
  assert.equal(await page.evaluate(()=>prompts.length),afterLoad+1,'Template and later Add Row population remain silent');
  await fields('purpose').nth(3).fill('New row changed');await page.click('#outside');
  assert.equal((await values('purpose'))[4],'New row changed');
  assert.deepEqual(errors,[]);
  assert.match(create,/grid\.js\?v=<\?= filemtime/);
  console.log('Manual Add Row regression checks passed: four-row date commit/focus return, purpose blur, confirm/cancel, last row, stale confirmations, real template row builder and rows added afterward.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
