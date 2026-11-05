const assert = require('node:assert/strict');
const path = require('node:path');
const {chromium} = require(process.env.GSLIP_UI_PLAYWRIGHT || 'playwright');
(async () => {
  const browser = await chromium.launch({channel: 'chrome', headless: true});
  try {
    const page = await browser.newPage();
    const errors = []; page.on('pageerror', e => errors.push(e.message));
    await page.setContent(`<table id="gasSlipGrid"><tbody>${[1,2,3].map(i=>`<tr><td><input name="validity_until[]" type="date" value="2026-11-06"></td><td><input name="purpose[]" value="Individual ${i}"></td><td><input name="requested_by[]" value="Requester ${i}"></td></tr>`).join('')}</tbody></table>`);
    await page.evaluate(() => {
      window.prompts = []; window.confirmBelow = false;
      window.Swal = {fire: async options => {prompts.push(options); return {isConfirmed: confirmBelow};}};
    });
    await page.addScriptTag({path: path.resolve(__dirname, '../assets/js/grid.js')});
    const purposes = () => page.locator('#gasSlipGrid [name="purpose[]"]').evaluateAll(fields=>fields.map(f=>f.value));
    async function edit(index, name, value) {
      const field = page.locator(`#gasSlipGrid [name="${name}[]"]`).nth(index);
      await field.focus(); await field.fill(value); await field.dispatchEvent('change');
      await page.evaluate(()=>new Promise(resolve=>setTimeout(resolve,0)));
    }
    await edit(0,'purpose','Changed manually');
    assert.deepEqual(await purposes(),['Changed manually','Individual 2','Individual 3'],'No keeps individual values without a template');
    assert.equal(await page.evaluate(()=>prompts[0].cancelButtonText),'No, Keep Individual');
    assert.equal(await page.evaluate(()=>prompts[0].confirmButtonText),'Apply to All Below');
    await page.evaluate(()=>confirmBelow=true);
    await edit(1,'purpose','Apply later');
    assert.deepEqual(await purposes(),['Changed manually','Apply later','Apply later'],'Only subsequent rows changed');
    assert.deepEqual(await page.locator('[name="requested_by[]"]').evaluateAll(fields=>fields.map(f=>f.value)),['Requester 1','Requester 2','Requester 3']);
    await edit(0,'validity_until','2026-11-10');
    assert.deepEqual(await page.locator('[name="validity_until[]"]').evaluateAll(fields=>fields.map(f=>f.value)),Array(3).fill('2026-11-10'));
    const count = await page.evaluate(()=>prompts.length);
    await edit(0,'purpose','Changed manually'); // unchanged
    await edit(0,'purpose','   '); // invalid empty purpose
    await edit(0,'validity_until','');
    await edit(2,'purpose','Last row');
    assert.equal(await page.evaluate(()=>prompts.length),count,'Unchanged/invalid/last-row edits do not prompt');
    await page.evaluate(()=>{
      const body=document.querySelector('#gasSlipGrid tbody');
      const manual=body.firstElementChild.cloneNode(true);
      manual.querySelector('[name="purpose[]"]').value='New manual row'; body.append(manual);
    });
    await edit(2,'purpose','Applies to added row');
    assert.equal((await purposes())[3],'Applies to added row','Delegation covers Add Row clones');
    await page.evaluate(()=>{
      const body=document.querySelector('#gasSlipGrid tbody'), base=body.firstElementChild.cloneNode(true);
      body.replaceChildren(...[1,2,3].map(i=>{const row=base.cloneNode(true);row.querySelector('[name="purpose[]"]').value='Template '+i;return row;}));
      window.TEMPLATE_ID=42;
    });
    assert.equal(await page.evaluate(()=>prompts.length),count+1,'Programmatic template loading is silent');
    await edit(0,'purpose','Template edit');
    assert.deepEqual(await purposes(),Array(3).fill('Template edit'),'Loaded template rows use same handler');
    await page.evaluate(()=>{
      const body=document.querySelector('#gasSlipGrid tbody');body.append(body.firstElementChild.cloneNode(true));
      window.TEMPLATE_ID=0;
    });
    await edit(2,'purpose','Mixed workflow');
    assert.equal((await purposes())[3],'Mixed workflow','Rows added after template loading need no template selection');
    await page.evaluate(()=>{
      document.querySelector('#gasSlipGrid [name="validity_until[]"]').min='2026-11-01';
    });
    const beforeInvalid=await page.evaluate(()=>prompts.length);
    await edit(0,'validity_until','2026-10-01');
    assert.equal(await page.evaluate(()=>prompts.length),beforeInvalid,'Native date constraints respected');
    assert.deepEqual(errors,[]);
    console.log('Apply-to-all-below browser checks passed: no template, template rows, later clones, confirm/cancel, only later rows, valid changed fields, unrelated columns and silent programmatic loads.');
  } finally { await browser.close(); }
})().catch(e=>{console.error(e);process.exitCode=1;});
