// Real browser interactions with deterministic bulk responses. No application data is written.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),os=require('node:os');
const {execFileSync}=require('node:child_process');
const {chromium}=require(process.env.GSLIP_UI_PLAYWRIGHT||'playwright');
const root=path.resolve(__dirname,'..');
const renderControls = view => execFileSync('php',['-r',`$_SESSION=['is_recommender'=>true,'is_approver'=>true];$bulkPageContext='${view}';$bulkPageTitle='${view==='approved'?'Approved':view==='recommended'?'Recommended':'Pending'} Gas Slips';include 'includes/bulk_processing_ui.php';`],{cwd:root,encoding:'utf8'}).replace(/<script[\s\S]*?<\/script>/g,'');
const css=['bootstrap.min.css','sweetalert2.min.css','style.css','bulk-processing.css'].map(f=>fs.readFileSync(path.join(root,'assets/css',f),'utf8')).join('\n')+'\n'+fs.readFileSync(path.join(root,'assets/fontawesome/css/all.min.css'),'utf8');
const scripts=['bootstrap.bundle.min.js','sweetalert2.all.min.js','gas-slip.js','bulk-processing.js'].map(f=>fs.readFileSync(path.join(root,'assets/js',f),'utf8')).join('\n;\n');
const groupRows=Array.from({length:24},(_,i)=>({id:i+1,gas_slip_id:`GS${i+1}`,requested_by:`Requested ${i+1}`,excluded:i===1?1:0}));
let groups=[{id:1,group_name:'Routine Field Trips',names:[{id:1,requested_by_name:'Requested 1'}]},{id:3,group_name:'Empty group',names:[{id:3,requested_by_name:'Future Name'}]}];
let snapshots=[],invalid=false,processCount=0,resultSummary=null,stateFailures=[],stateRequests=0,editorOps=[],nextGroupId=50,nextNameId=100;
let eligibleLimit=null;
function rowsFor(group){const base=eligibleLimit===null?groupRows:groupRows.slice(0,eligibleLimit);return group===3?[]:group===1?base.filter(r=>r.id<=12):base;}

const approvedSource=fs.readFileSync(path.join(root,'approved_slips.php'),'utf8').replace(/\r\n/g,'\n');
const filterStart=approvedSource.lastIndexOf('<div',approvedSource.indexOf('id="filterModal"'));
const filterModal=execFileSync('php',['-r',"$hasDates=false;$showPrinted=true;$dateFrom=null;$dateTo=null;$search='';?>"+approvedSource.slice(filterStart,approvedSource.indexOf('<script src="assets/js/bootstrap',filterStart))],{cwd:root,encoding:'utf8'});
const printStart=approvedSource.indexOf("document\n    .getElementById('printSelected')");
const printHandlers=approvedSource.slice(printStart,approvedSource.indexOf('</script>',printStart));
function historyRowsFor(view,group){const base=groupRows.slice(0,view==='recommended'?6:4);return group===0?base:group===1?base.slice(0,view==='recommended'?2:3):[];}
function html(url){const u=new URL(url),group=Number(u.searchParams.get('bulk_group')||0),page=Number(u.searchParams.get('page')||1),pending=(u.searchParams.get('view')||'pending')==='pending',approved=u.searchParams.get('view')==='approved',pageRows=pending?rowsFor(group):historyRowsFor(u.searchParams.get('view'),group);return `<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>${css}</style><main style="padding:16px;max-width:1200px;margin:auto">${renderControls(u.searchParams.get('view')||'pending')}<div class="bulk-table-scroll" role="region" aria-label="Gas slips table" tabindex="0"><table class="styled-table excel-table"><thead><tr class="group-header">${approved?'<th class="no-modal"><input id="checkAll" type="checkbox" aria-label="Select printed slips"></th>':''}${pending?'<th class="bulk-select-column no-modal"><input id="bulk-all" type="checkbox" aria-label="Select all eligible gas slips across all pages" disabled></th>':''}<th>No.</th><th>Gas Slip No.</th><th>Date Requested</th><th>Requested By</th><th>Purpose</th><th>Route</th><th>Status</th></tr></thead><tbody>${pageRows.slice((page-1)*10,page*10).map(r=>`<tr class="gas-slip-row ${r.id===1?'today-row':''}" data-id="${r.id}" tabindex="0">${approved?`<td class="no-modal"><input class="print-check" type="checkbox" value="${r.id}"></td>`:''}${pending?'<td class="bulk-select-column no-modal"></td>':''}<td>${r.id}</td><td><strong>${r.gas_slip_id}</strong><span class="badge bg-info">P</span></td><td>2026-11-06</td><td>${r.requested_by}</td><td>Field work</td><td>Office → Field</td><td>For Recommendation</td></tr>`).join('')||'<tr><td colspan="7">No pending gas slips found</td></tr>'}</tbody></table></div><ul class="pagination"><li><a href="?page=1">Page 1</a></li><li><a href="?page=2">Page 2</a></li></ul></main><div class="modal" id="gasSlipModal"><div class="modal-dialog"><div class="modal-content"><div id="gasSlipModalTitle"></div><div id="gasSlipModalBody"></div></div></div></div>${approved?filterModal:''}<script>${scripts}
${approved?printHandlers:''}</script>`;}
(async()=>{
 const browser=await chromium.launch({channel:'chrome',headless:true});
 try{
  // Use the unchanged individual Reject configuration as the computed-style reference.
  const individualSource=fs.readFileSync(path.join(root,'pending_gas_slips.php'),'utf8').replace(/\r\n/g,'\n');
  const individualStart=individualSource.indexOf("title: 'Reject Gas Slip?',\n    input: 'textarea'");
  assert.ok(individualStart>0);
  const individualOptions='({'+individualSource.slice(individualStart,individualSource.indexOf('\n});',individualStart))+'})';
  const referencePage=await browser.newPage();
  await referencePage.setContent(`<style>${css}</style>`);
  await referencePage.addScriptTag({content:fs.readFileSync(path.join(root,'assets/js/sweetalert2.all.min.js'),'utf8')});
  const typography=['fontFamily','fontSize','fontWeight','lineHeight'];
  const inputStyles=[...typography,'padding','borderRadius','borderColor','borderWidth','boxShadow'];
  const computed=async(page,selector,keys)=>page.locator(selector).evaluate((el,keys)=>{const style=getComputedStyle(el);return Object.fromEntries(keys.map(key=>[key,style[key]]));},keys);
  const settle=async locator=>locator.evaluate(async el=>{await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));await Promise.all(el.getAnimations().map(animation=>animation.finished));});
  const references={};
  for(const dark of [false,true]) {
   await referencePage.evaluate(dark=>document.body.classList.toggle('dark-mode',dark),dark);
   await referencePage.evaluate('void Swal.fire('+individualOptions+')');
   await referencePage.locator('.swal2-textarea').waitFor();
   await referencePage.locator('.swal2-textarea').fill('Reference rejection reason');
   await referencePage.locator('.swal2-confirm').focus();await settle(referencePage.locator('.swal2-textarea'));
   const reference={title:await computed(referencePage,'.swal2-title',typography),body:await computed(referencePage,'.swal2-html-container',typography),label:await computed(referencePage,'.swal2-input-label',typography),button:await computed(referencePage,'.swal2-confirm',typography),textarea:await computed(referencePage,'.swal2-textarea',inputStyles)};
   await referencePage.locator('.swal2-textarea').focus();await settle(referencePage.locator('.swal2-textarea'));
   reference.focus=await computed(referencePage,'.swal2-textarea',inputStyles);references[dark]=reference;
   await referencePage.evaluate(()=>Swal.close());
  }
  await referencePage.close();
  const page=await browser.newPage({viewport:{width:1280,height:900}}),errors=[];
  const verifyWarningAppearance=async()=>{
   const appearance=await page.locator('.bulk-confirmation-warning').evaluate(el=>{
    const style=getComputedStyle(el),heading=getComputedStyle(el.querySelector('.bulk-warning-heading')),paragraph=getComputedStyle(el.querySelector('p'));
    return {background:style.backgroundColor,border:style.borderTopWidth,shadow:style.boxShadow,headingSize:heading.fontSize,headingLineHeight:heading.lineHeight,headingWeight:heading.fontWeight,headingCase:heading.textTransform,headingAlign:heading.textAlign,paragraphAlign:paragraph.textAlign,lastLine:paragraph.textAlignLast,titleAlign:getComputedStyle(document.querySelector('.swal2-title')).textAlign,descriptionCount:document.querySelectorAll('.bulk-confirmation-description').length,narrow:innerWidth<=400};
   });
   assert.equal(appearance.background,'rgba(0, 0, 0, 0)');assert.equal(appearance.border,'0px');assert.equal(appearance.shadow,'none');
   assert.equal(appearance.headingSize,'18px');assert.equal(appearance.headingLineHeight,'25.2px');assert.equal(appearance.headingWeight,'700');assert.equal(appearance.headingCase,'uppercase');assert.equal(appearance.headingAlign,'center');
   assert.equal(appearance.paragraphAlign,appearance.narrow?'left':'justify');assert.equal(appearance.lastLine,'auto');
   assert.equal(appearance.titleAlign,'center');assert.equal(appearance.descriptionCount,0);
   const headingFits=await page.locator('.bulk-warning-heading').evaluate(el=>el.scrollWidth<=el.clientWidth && parseFloat(getComputedStyle(el).fontSize)<parseFloat(getComputedStyle(document.querySelector('.swal2-title')).fontSize));
   assert.equal(headingFits,true,'Warning heading wraps within the modal and stays smaller than its title');
  };
  const verifyStrongAppearance=async phrase=>{
   assert.equal(await page.locator('.bulk-required-phrase').textContent(),phrase);
   const style=await page.evaluate(()=>{
    const label=document.querySelector('.bulk-acknowledgment'),checkbox=label.querySelector('input'),span=label.querySelector('span'),phrase=document.querySelector('.bulk-required-phrase');
    const a=getComputedStyle(label),c=getComputedStyle(checkbox),p=getComputedStyle(phrase);
    return {width:c.width,height:c.height,gap:a.gap,alignment:a.alignItems,font:a.fontSize,paragraphFont:getComputedStyle(document.querySelector('.bulk-confirmation-warning p')).fontSize,weight:p.fontWeight,decoration:p.textDecorationLine,offset:p.textUnderlineOffset,sectionGap:getComputedStyle(document.querySelector('.bulk-typed-confirmation')).marginTop,instructionGap:getComputedStyle(document.querySelector('.bulk-confirmation-instruction')).marginBottom,fits:span.scrollWidth<=span.clientWidth};
   });
   assert.equal(style.width,'18px');assert.equal(style.height,'18px');assert.equal(style.gap,'12px');assert.equal(style.alignment,'flex-start');
   assert.equal(style.font,'16px');assert.equal(style.weight,'700');assert.equal(style.decoration,'underline');assert.equal(style.offset,'3px');
   assert.equal(style.sectionGap,'16px');assert.equal(style.instructionGap,'8px');assert.equal(style.fits,true);
   assert.equal(await page.locator('.bulk-acknowledgment-heading').textContent(),'I understand the consequences');
   assert.match(await page.locator('.bulk-acknowledgment-description').textContent(),/I acknowledge that all 24 affected gas slips will be (recommended|approved)/);
   assert.equal(await page.locator('.bulk-confirmation-instruction').textContent(),`Type ${phrase} to confirm`);
   assert.equal(await page.locator('#bulk-phrase').getAttribute('placeholder'),'Enter confirmation phrase');
   const cardStyle=await page.locator('.bulk-acknowledgment').evaluate(el=>({align:getComputedStyle(el).textAlign,border:getComputedStyle(el).borderTopWidth,radius:getComputedStyle(el).borderRadius,heading:getComputedStyle(el.querySelector('strong')).fontSize,description:getComputedStyle(el.querySelector('.bulk-acknowledgment-description')).fontSize,descriptionAlign:getComputedStyle(el.querySelector('.bulk-acknowledgment-description')).textAlign}));
   assert.deepEqual(cardStyle,{align:'left',border:'1px',radius:'8px',heading:'16px',description:'14px',descriptionAlign:'left'});
   const buttonText=phrase==='APPROVE ALL'?'Approve All (24)':'Recommend All (24)';
   assert.equal(await page.locator('.swal2-confirm').textContent(),buttonText);
   const buttons=await page.locator('.swal2-actions button:visible').allTextContents();assert.deepEqual(buttons,['Cancel',buttonText]);
  };
  const verifyRejectTypography=async()=>{
   for(const dark of [false,true]) {
    await page.evaluate(dark=>document.body.classList.toggle('dark-mode',dark),dark);
    const ref=references[dark];
    await verifyWarningAppearance();
    if(await page.locator('.bulk-acknowledgment').count()) {
     assert.equal(await page.locator('.bulk-acknowledgment-description').evaluate(el=>getComputedStyle(el).textAlign),'left');
     assert.equal(await page.locator('.bulk-acknowledgment-heading').textContent(),'I understand the consequences');
    }
    assert.deepEqual(await computed(page,'.swal2-title',typography),ref.title);
    assert.deepEqual(await computed(page,'.bulk-confirmation-warning',typography),ref.body);
    assert.deepEqual(await computed(page,'label[for="bulk-reason"]',typography),ref.label);
    assert.deepEqual(await computed(page,'.swal2-confirm',typography),ref.button);
    await page.locator('#bulk-reason').fill('Typed bulk rejection reason');
    await page.locator('.swal2-cancel').focus();await settle(page.locator('#bulk-reason'));
    assert.deepEqual(await computed(page,'#bulk-reason',inputStyles),ref.textarea);
    await page.locator('#bulk-reason').focus();await settle(page.locator('#bulk-reason'));
    assert.deepEqual(await computed(page,'#bulk-reason',inputStyles),ref.focus);
   }
   await page.evaluate(()=>document.body.classList.remove('dark-mode'));
   await page.locator('#bulk-reason').fill('');
  };
  const verifyMultiRecordAcknowledgment=async(action,control)=>{
   await page.locator('.bulk-check').nth(1).check();
   await page.locator(control).click();await page.locator('.bulk-acknowledgment').waitFor();
   const operation=action==='reject'?'rejected using the same reason':action==='approve'?'approved':'recommended';
   assert.equal(await page.locator('.bulk-acknowledgment-description').textContent(),`I acknowledge that all 2 affected gas slips will be ${operation}, including those I may not have reviewed individually.`);
   assert.equal(await page.locator('#bulk-phrase').count(),0);assert.equal(await page.locator('.swal2-confirm').isDisabled(),true);
   if(action==='reject'){await page.locator('#bulk-reason').fill('Shared reason');assert.equal(await page.locator('.swal2-confirm').isDisabled(),true);}
   await page.locator('.bulk-acknowledgment > span').click();assert.equal(await page.locator('.swal2-confirm').isDisabled(),false);
   await page.getByRole('button',{name:'Cancel',exact:true}).click();await page.locator('.bulk-check').nth(1).uncheck();
  };
  page.on('pageerror',e=>errors.push(e.message));
  await page.route('http://bulk.test/**',async route=>{
   const request=route.request(),url=new URL(request.url());
   if(url.pathname==='/fixture')return route.fulfill({contentType:'text/html',body:html(url.href)});
   if(url.pathname.startsWith('/webfonts/'))return route.fulfill({contentType:'font/woff2',body:fs.readFileSync(path.join(root,'assets/fontawesome/webfonts',path.basename(url.pathname)))});
   if(url.pathname==='/print_gas_slip.php')return route.fulfill({contentType:'text/html',body:'Selected print destination'});
   if(url.pathname==='/view_gas_slip_modal.php')return route.fulfill({contentType:url.searchParams.get('mode')==='json'?'application/json':'text/html',body:url.searchParams.get('mode')==='json'?JSON.stringify({id:url.searchParams.get('id'),gas_slip_id:`GS${url.searchParams.get('id')}`,statusClass:'bg-info',statusLabel:'Pending'}):'<button id="recommendBtn">Recommend</button><button class="rejectBtn">Reject</button>'});
   if(url.pathname==='/bulk_processing.php'){
    const d=request.postDataJSON();let result={success:true};
    if(d.op==='state') {
     stateRequests++;
     const fault=stateFailures.shift();
     if(fault) {
      if(fault==='network')return route.abort('failed');
      return route.fulfill({status:fault==='status'?503:200,contentType:fault==='type'?'text/html':'application/json',body:fault==='json'?'{invalid':fault==='status'?JSON.stringify({success:false,message:'Bulk storage is temporarily unavailable. Please try again.'}):'<br><b>Warning: private/path</b>'});
     }
    }
    if(d.op==='state')result={...result,groups,counts:Object.fromEntries([[0,d.page==='pending'?rowsFor(0).length:historyRowsFor(d.page,0).length],...groups.map(g=>[g.id,d.page==='pending'?rowsFor(g.id).length:historyRowsFor(d.page,g.id).length])]),records:d.page==='pending'?rowsFor(d.group):[],action:new URL(page.url()).searchParams.get('actor')==='approver'?'approve':'recommend'};
    if(d.op==='snapshot'){
     const eligible=rowsFor(d.group),ids=d.ids.length?d.ids:eligible.map(r=>r.id),all=ids.length===eligible.length;
     const snapshot={records:eligible.filter(r=>ids.includes(r.id)),action:d.action,all,strong:d.action==='reject'?all:!d.group&&all};
     snapshots.push(d);result={...result,snapshot,token:'fixture-token'};
    }
    if(d.op==='validate')result.valid=!invalid;
    if(d.op==='process'){processCount++;result.summary=resultSummary||{successful:[],skipped:[],failed:[]};}
    if(d.op==='exclude')groupRows.find(r=>r.id===d.id).excluded=d.excluded?1:0;
    const normalized=name=>name.trim().replace(/\s+/g,' ').toLowerCase();
    if(d.op==='suggest')result.names=(d.query.toLowerCase().startsWith('keyboard')?['Keyboard Requested Name']:['New Requested Name']).filter(name=>!groups.some(g=>g.names.some(n=>normalized(n.requested_by_name)===normalized(name))));
    if(['create','rename','delete','add_name','remove_name'].includes(d.op)) {
     editorOps.push(d);
     if((d.op==='create'||d.op==='rename')&&groups.some(g=>(d.op==='create'||g.id!==d.group)&&normalized(g.group_name)===normalized(d.name)))return route.fulfill({status:409,contentType:'application/json',body:JSON.stringify({success:false,message:'This group name is already in use.'})});
     if(d.op==='create'){const g={id:nextGroupId++,group_name:d.name.trim(),names:[]};groups.push(g);result.group=g.id;}
     if(d.op==='rename')groups.find(g=>g.id===d.group).group_name=d.name.trim();
     if(d.op==='delete')groups=groups.filter(g=>g.id!==d.group);
     if(d.op==='add_name'){
      if(groups.some(g=>g.names.some(n=>normalized(n.requested_by_name)===normalized(d.name))))return route.fulfill({status:409,contentType:'application/json',body:JSON.stringify({success:false,message:'This name is already assigned to one of your groups.'})});
      groups.find(g=>g.id===d.group).names.push({id:nextNameId++,requested_by_name:d.name.trim()});
     }
     if(d.op==='remove_name'){const g=groups.find(g=>g.id===d.group);g.names=g.names.filter(n=>n.id!==d.name_id);}
     result.groups=groups;
    }
    return route.fulfill({contentType:'application/json',body:JSON.stringify(result)});
   }
   return route.fulfill({status:404,body:''});
  });
  const ready=()=>page.waitForFunction(()=>document.getElementById('bulk-action').textContent==='Recommend All (24)');
  const cancel=()=>page.getByRole('button',{name:'Cancel',exact:true}).click();
  for(const activeGroup of [0,1]) {
   for(const limit of [0,1,2]) {
    eligibleLimit=limit;await page.goto(`http://bulk.test/fixture?bulk_group=${activeGroup}`);
    await page.getByRole('tab',{name:`${activeGroup?'Routine Field Trips':'All Gas Slips'} (${limit} eligible)`,exact:true}).waitFor();
    assert.equal(await page.locator('.bulk-actions').isVisible(),limit>=2);
    assert.equal(await page.locator('thead .bulk-select-column').isVisible(),limit>=2);
    assert.equal(await page.locator('.bulk-check').count(),limit>=2?limit:0);
    assert.equal(await page.locator('thead th:visible').first().textContent(),limit>=2?'':'No.');
    if(limit===0)assert.equal(await page.locator('td[colspan]').getAttribute('colspan'),'7');
    if(limit===1) {
     await page.locator('.gas-slip-row td').last().click();await page.locator('#gasSlipModal #recommendBtn').waitFor();
     await page.evaluate(()=>bootstrap.Modal.getInstance(document.getElementById('gasSlipModal')).hide());
    }
   }
  }
  eligibleLimit=2;await page.goto('http://bulk.test/fixture');await page.getByRole('tab',{name:'All Gas Slips (2 eligible)',exact:true}).waitFor();
  await page.locator('#bulk-all').check();assert.equal(await page.locator('.bulk-check:checked').count(),2);
  const refreshEditor=async()=>{await page.locator('#bulk-manage').click();await page.locator('.bulk-groups-dialog').waitFor();await page.evaluate(()=>Swal.close());await page.locator('.bulk-groups-dialog').waitFor({state:'hidden'});};
  eligibleLimit=1;await refreshEditor();await page.getByRole('tab',{name:'All Gas Slips (1 eligible)',exact:true}).waitFor();
  assert.equal(await page.locator('#bulk-all').isChecked(),false);assert.equal(await page.locator('.bulk-check').count(),0);assert.equal(await page.locator('.bulk-actions').isVisible(),false);
  eligibleLimit=2;await refreshEditor();await page.getByRole('tab',{name:'All Gas Slips (2 eligible)',exact:true}).waitFor();
  assert.equal(await page.locator('.bulk-actions').isVisible(),true);assert.equal(await page.locator('.bulk-check:checked').count(),0);
  await page.locator('#bulk-action').click();await page.locator('#bulk-phrase').waitFor();
  await page.locator('#bulk-ack').check();await page.locator('#bulk-phrase').fill('RECOMMEND ALL');assert.equal(await page.locator('.swal2-confirm').isDisabled(),false);
  eligibleLimit=1;invalid=true;await page.waitForFunction(()=>document.querySelector('.swal2-confirm').disabled);
  assert.match(await page.locator('#bulk-validity').textContent(),/refresh/i);await cancel();
  await page.getByRole('tab',{name:'All Gas Slips (1 eligible)',exact:true}).waitFor();
  assert.equal(await page.locator('.bulk-actions').isVisible(),false);assert.equal(await page.locator('thead .bulk-select-column').isVisible(),false);invalid=false;
  await page.goto('http://bulk.test/fixture?actor=approver');await page.getByRole('tab',{name:'All Gas Slips (1 eligible)',exact:true}).waitFor();
  assert.equal(await page.locator('#bulk-action').textContent(),'Approve All (1)');assert.equal(await page.locator('.bulk-actions').isVisible(),false);
  eligibleLimit=11;await page.goto('http://bulk.test/fixture?page=2');await page.getByRole('tab',{name:'All Gas Slips (11 eligible)',exact:true}).waitFor();
  assert.equal(await page.locator('.gas-slip-row').count(),1);assert.equal(await page.locator('.bulk-actions').isVisible(),true);
  await page.locator('#bulk-all').check();assert.equal(await page.locator('#bulk-action').textContent(),'Recommend All (11)');
  await page.evaluate(()=>sessionStorage.clear());eligibleLimit=null;await page.goto('http://bulk.test/fixture');await ready();await page.locator('#bulk-all').uncheck();
  for(const fault of ['type','json','status','network']) {
   stateFailures=[fault];const before=stateRequests;
   await page.goto('http://bulk.test/fixture');
   await page.locator('#bulk-retry').waitFor({state:'visible'});
   assert.equal(await page.locator('#bulk-action').textContent(),'Unavailable');
   assert.equal(await page.locator('#bulk-action').isDisabled(),true);
   assert.equal(await page.locator('#bulk-all').isDisabled(),true);
   assert.equal(await page.locator('#bulk-title').count(),0);
   assert.ok(!/private\/path|<br|0 eligible/.test(await page.locator('#bulk-message').textContent()));
   await page.locator('#bulk-retry').click();await ready();
   assert.equal(stateRequests-before,2,'A single Retry sends exactly one new initialization request');
   assert.equal(await page.locator('#bulk-retry').isVisible(),false);
   assert.equal(await page.locator('.bulk-check').count(),10);
  }
  await page.goto('http://bulk.test/fixture');await ready();
  assert.equal(await page.locator('.bulk-toolbar,#bulk-title').count(),0);
  assert.equal(await page.locator('thead th').count(),8);
  assert.equal(await page.locator('thead th').first().locator('#bulk-all').count(),1);
  assert.equal(await page.locator('thead th').nth(1).textContent(),'No.');
  assert.equal(await page.getByRole('tab',{name:'All Gas Slips (24 eligible)',exact:true}).locator('.bulk-tab-count').textContent(),'24');
  assert.equal(await page.getByRole('tab',{name:'Routine Field Trips (12 eligible)',exact:true}).locator('.bulk-tab-count').textContent(),'12');
  assert.equal(await page.getByRole('tab',{name:'Empty group (0 eligible)',exact:true}).locator('.bulk-tab-count').textContent(),'0');
  assert.equal(await page.locator('tr[data-id="1"] td').first().locator('.bulk-check').count(),1);
  assert.equal(await page.locator('#bulk-reject').isVisible(),false);
  assert.equal(await page.locator('#bulk-manage').count(),1);assert.equal(await page.locator('.bulk-page-header #bulk-manage').count(),0);assert.equal(await page.locator('.bulk-nav-left > #bulk-manage').count(),1);assert.equal(await page.locator('#bulk-manage').getAttribute('title'),'Manage Groups');
  assert.equal(await page.locator('.bulk-check').count(),10);
  assert.equal(await page.locator('#bulk-workflow,.bulk-review').count(),0);
  await page.locator('.bulk-check').first().check();
  assert.equal(await page.locator('#bulk-action').textContent(),'Recommend Selected (1)');
  assert.equal(await page.locator('#bulk-action').isDisabled(),true);
  await verifyMultiRecordAcknowledgment('recommend','#bulk-action');
  assert.equal(await page.locator('#gasSlipModal').isVisible(),false);
  assert.equal(await page.locator('#bulk-all').evaluate(e=>e.indeterminate),true);
  await page.locator('.bulk-check').first().press('Enter');
  assert.equal(await page.locator('#gasSlipModal').isVisible(),false);
  await page.locator('#bulk-all').check();
  assert.equal(await page.locator('#bulk-action').textContent(),'Recommend All (24)');
  await page.getByRole('link',{name:'Page 2'}).click();await ready();
  assert.equal(await page.locator('.bulk-check:checked').count(),10);
  assert.equal(await page.locator('#bulk-all').isChecked(),true);
  await page.locator('#bulk-action').click();await page.locator('#bulk-phrase').waitFor();
  await verifyStrongAppearance('RECOMMEND ALL');
  await page.locator('.bulk-acknowledgment > span').click();assert.equal(await page.locator('#bulk-ack').isChecked(),true);
  await page.locator('.bulk-acknowledgment > span').click();assert.equal(await page.locator('#bulk-ack').isChecked(),false);
  await page.locator('#bulk-ack').focus();
  assert.equal(await page.locator('.bulk-acknowledgment').evaluate(el=>getComputedStyle(el).outlineStyle),'solid');
  await page.locator('#bulk-ack').press('Space');assert.equal(await page.locator('#bulk-ack').isChecked(),true);
  assert.equal(await page.locator('.swal2-confirm').isDisabled(),true);
  await page.locator('#bulk-ack').press('Space');assert.equal(await page.locator('#bulk-ack').isChecked(),false);
  assert.equal(await page.locator('.swal2-title').textContent(),'Recommend All Gas Slips?');
  assert.equal(await page.locator('.bulk-confirmation-description').count(),0);
  assert.equal(await page.locator('.bulk-confirmation-warning p').count(),2);
  assert.match(await page.locator('.bulk-confirmation-warning').textContent(),/All Gas Slips tab[\s\S]*across all pages/);
  assert.match(await page.locator('.bulk-confirmation-warning p').first().textContent(),/all 24 eligible gas slips/);
  assert.equal(snapshots.at(-1).ids.length,24);
  assert.equal(await page.locator('.swal2-html-container li').count(),0);
  assert.equal(await page.locator('.swal2-confirm').isDisabled(),true);
  await page.locator('#bulk-ack').check();await page.locator('#bulk-phrase').fill('recommend all');
  assert.equal(await page.locator('.swal2-confirm').isDisabled(),true);
  await page.locator('#bulk-phrase').fill('RECOMMEND ALL');assert.equal(await page.locator('.swal2-confirm').isDisabled(),false);
  invalid=true;await page.waitForFunction(()=>document.querySelector('.swal2-confirm').disabled);
  await cancel();invalid=false;
  await page.locator('#bulk-all').uncheck();await page.locator('#bulk-action').click();await page.locator('#bulk-phrase').waitFor();
  assert.equal(snapshots.at(-1).ids.length,0);await cancel();
  await page.getByRole('tab',{name:'Routine Field Trips'}).click();
  await page.waitForFunction(()=>document.querySelector('#bulk-tabs .active')?.getAttribute('aria-label')==='Routine Field Trips (12 eligible)');
  assert.equal(await page.locator('.bulk-check:checked').count(),0);
  assert.equal(await page.locator('#bulk-reject').isDisabled(),true);
  await page.locator('#bulk-action').click();await page.locator('.swal2-popup').waitFor();
  assert.match(await page.locator('.bulk-confirmation-warning').textContent(),/selected group/);
  assert.equal(await page.locator('.bulk-confirmation-warning p').count(),2);
  assert.equal(await page.locator('#bulk-phrase').count(),0);assert.equal(await page.locator('#bulk-ack').count(),1);assert.equal(await page.locator('.swal2-confirm').isDisabled(),true);await page.locator('#bulk-ack').check();assert.equal(await page.locator('.swal2-confirm').isDisabled(),false);await cancel();
  await page.locator('.bulk-check').first().check();assert.equal(await page.locator('#bulk-reject').textContent(),'Reject Selected (1)');
  assert.equal(await page.locator('#bulk-reject').isDisabled(),true);
  await verifyMultiRecordAcknowledgment('reject','#bulk-reject');
  await page.locator('#bulk-all').check();await page.locator('#bulk-reject').click();await page.locator('#bulk-reason').waitFor();
  assert.equal(await page.locator('.bulk-acknowledgment').count(),1);assert.equal(await page.locator('.bulk-typed-confirmation').count(),0);
  assert.equal(await page.locator('.swal2-title').textContent(),'Reject All Gas Slips?');
  assert.equal(await page.locator('.bulk-confirmation-description').count(),0);
  assert.match(await page.locator('.bulk-confirmation-warning').textContent(),/same rejection reason[\s\S]*each successfully rejected gas slip/);
  assert.match(await page.locator('.bulk-confirmation-warning p').first().textContent(),/all 12 selected gas slips/);
  await verifyRejectTypography();
  assert.equal(await page.locator('.swal2-html-container li').count(),0);
  await page.locator('#bulk-reason').fill('Review');assert.equal(await page.locator('.swal2-confirm').isDisabled(),true);
  await page.locator('#bulk-ack').check();assert.equal(await page.locator('.swal2-confirm').isDisabled(),false);await cancel();
  assert.equal(await page.locator('tr[data-id="1"] td').nth(1).textContent(),'1');
  assert.equal(await page.locator('.bulk-review').count(),0);
  await page.locator('tr[data-id="1"] td').last().click();await page.locator('#gasSlipModal #recommendBtn').waitFor();
  assert.equal(await page.locator('#gasSlipModal .rejectBtn').count(),1);
  await page.evaluate(()=>bootstrap.Modal.getInstance(document.getElementById('gasSlipModal')).hide());
  await page.getByRole('tab',{name:'Empty group'}).click();
  await page.waitForFunction(()=>document.querySelector('#bulk-tabs .active')?.getAttribute('aria-label')==='Empty group (0 eligible)');
  assert.equal(await page.locator('#bulk-action').isDisabled(),true);assert.equal(await page.locator('#bulk-reject').isDisabled(),true);
  assert.equal(await page.locator('td[colspan]').textContent(),'No eligible gas slips in this group.');
  await page.locator('#bulk-manage').click();await page.locator('.swal2-popup').waitFor();
  await page.locator('.name').first().fill('new');await page.getByRole('option',{name:'New Requested Name',exact:true}).click();
  await page.getByRole('button',{name:'Remove New Requested Name',exact:true}).waitFor();
  assert.equal(await page.locator('.swal2-popup').isVisible(),true);
  assert.equal(await page.locator('.bulk-group-card').count(),1);assert.equal(await page.locator('#bulk-rename-input').count(),0);
  const create=page.locator('#bulk-create button');assert.equal(await create.isDisabled(),true);
  await page.locator('#bulk-new-group').fill('UI Group');await create.click();
  await page.locator('.bulk-group-title h4').filter({hasText:'UI Group'}).waitFor();
  assert.equal(await page.getByRole('button',{name:'UI Group (0)',exact:true}).getAttribute('aria-pressed'),'true');
  await page.locator('#bulk-new-group').fill('ui group');await create.click();
  await page.waitForFunction(()=>document.getElementById('bulk-editor-message').textContent.includes('already in use'));
  await page.locator('#bulk-new-group').fill('');
  await page.locator('#bulk-rename').click();await page.locator('#bulk-rename-input').fill('Unsaved name');
  const beforeRename=editorOps.filter(d=>d.op==='rename').length;
  await page.locator('#bulk-rename-cancel').click();assert.equal(editorOps.filter(d=>d.op==='rename').length,beforeRename);
  assert.equal(await page.locator('.bulk-group-title h4').textContent(),'UI Group');
  await page.locator('#bulk-rename').click();await page.locator('#bulk-rename-input').fill('Renamed UI Group');await page.locator('#bulk-rename-form button[type="submit"]').click();
  await page.waitForFunction(()=>document.querySelector('.bulk-group-title h4').textContent==='Renamed UI Group');
  await page.locator('#bulk-name-input').fill('Manual Person');await page.locator('#bulk-add-name button').click();
  await page.getByRole('button',{name:'Remove Manual Person',exact:true}).waitFor();assert.equal(await page.locator('#bulk-name-input').inputValue(),'');
  await page.locator('#bulk-name-input').fill('keyboard');await page.getByRole('option',{name:'Keyboard Requested Name',exact:true}).waitFor();
  await page.locator('#bulk-name-input').press('ArrowDown');assert.equal(await page.locator('#bulk-name-input').getAttribute('aria-activedescendant'),'bulk-suggestion-0');
  await page.locator('#bulk-name-input').press('Enter');await page.getByRole('button',{name:'Remove Keyboard Requested Name',exact:true}).waitFor();
  assert.equal(editorOps.filter(d=>d.op==='add_name'&&d.name==='Keyboard Requested Name').length,1);
  await page.locator('#bulk-name-input').fill(' new  requested name ');await page.locator('#bulk-add-name button').click();
  await page.waitForFunction(()=>document.getElementById('bulk-editor-message').textContent.includes('already assigned'));
  assert.equal(await page.locator('.bulk-name-chip').count(),2);
  await page.getByRole('button',{name:'Remove Manual Person',exact:true}).click();
  await page.waitForFunction(()=>document.getElementById('bulk-editor-message').textContent.includes('Gas slips were kept'));
  assert.equal(await page.getByRole('button',{name:'Renamed UI Group (1)',exact:true}).getAttribute('aria-pressed'),'true');
  const beforeDelete=editorOps.filter(d=>d.op==='delete').length;
  await page.locator('#bulk-delete').click();await page.locator('#bulk-delete-confirm').waitFor();
  assert.equal(editorOps.filter(d=>d.op==='delete').length,beforeDelete);assert.match(await page.locator('.bulk-delete-confirm').textContent(),/Existing gas slips will not be deleted/);
  await page.locator('#bulk-delete-cancel').click();assert.equal(editorOps.filter(d=>d.op==='delete').length,beforeDelete);
  await page.locator('#bulk-delete').click();await page.locator('#bulk-delete-confirm').click();
  await page.waitForFunction(()=>document.getElementById('bulk-editor-message').textContent.includes('Group deleted'));
  assert.equal(await page.getByRole('button',{name:'Renamed UI Group (1)',exact:true}).count(),0);assert.equal(groupRows.length,24);
  await page.setViewportSize({width:390,height:844});
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
  await page.evaluate(()=>Swal.close());await page.locator('.bulk-groups-dialog').waitFor({state:'hidden'});
  // Exercise layout against the same table columns, status colors and badges as the real queue.
  groups.push(...Array.from({length:9},(_,i)=>({id:10+i,group_name:`Additional field group ${i+1}`,names:[{id:10+i,requested_by_name:`Name ${i+1}`}]})));
  await page.goto('http://bulk.test/fixture');await ready();
  const screenshots=fs.mkdtempSync(path.join(os.tmpdir(),'gslip-bulk-layout-'));
  for(const width of [1280,768,390,320]) {
   await page.setViewportSize({width,height:900});
   const layout=await page.evaluate(()=>{
    const tabs=document.querySelector('.bulk-nav-left'),style=getComputedStyle(tabs),toolbar=document.querySelector('.bulk-navigation').getBoundingClientRect();
    const manage=document.getElementById('bulk-manage').getBoundingClientRect(),heading=document.querySelector('.bulk-page-heading').getBoundingClientRect();
    const table=document.querySelector('.bulk-table-scroll');
    return {sameRow:Math.abs(document.querySelector('.bulk-nav-left').getBoundingClientRect().top+document.querySelector('.bulk-nav-left').getBoundingClientRect().height/2-document.querySelector('.bulk-actions').getBoundingClientRect().top-document.querySelector('.bulk-actions').getBoundingClientRect().height/2)<2,pageOverflow:document.documentElement.scrollWidth>innerWidth,overflowY:style.overflowY,tabHeight:tabs.getBoundingClientRect().height,tabScroll:tabs.scrollWidth>tabs.clientWidth,verticalScroll:tabs.scrollHeight>tabs.clientHeight+1,tabTops:[...tabs.children].map(t=>{const r=t.getBoundingClientRect();return r.top+r.height/2;}),headerWrapped:manage.top>=heading.bottom,headerRight:manage.left>=heading.right,tableScroll:table.scrollWidth>table.clientWidth,buttonsFit:[...document.querySelectorAll('.bulk-actions button:not([hidden])')].every(b=>{const r=b.getBoundingClientRect();return r.left>=toolbar.left&&r.right<=toolbar.right&&r.bottom<=toolbar.bottom;}),rowColor:getComputedStyle(document.querySelector('tr[data-id="1"] td')).backgroundColor};
   });
   assert.equal(layout.pageOverflow,false,`No page overflow at ${width}px`);
   assert.equal(layout.overflowY,'hidden');assert.equal(layout.verticalScroll,false);assert.ok(layout.tabHeight<=60);
   assert.ok(Math.max(...layout.tabTops)-Math.min(...layout.tabTops)<2);assert.equal(layout.tabScroll,true);assert.equal(layout.buttonsFit,true);
   assert.equal(layout.rowColor,'rgb(245, 223, 161)');
   if(width<=390){assert.equal(layout.tableScroll,true);}else{assert.equal(layout.sameRow,true);}
   assert.equal(await page.locator('tr[data-id="1"] .badge').textContent(),'P');
   await page.screenshot({path:path.join(screenshots,`${width}.png`),fullPage:true});
   await page.locator('#bulk-manage').click();await page.locator('.bulk-groups-dialog').waitFor();
   await page.locator('.bulk-groups-dialog').evaluate(async el=>{await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));await Promise.all(el.getAnimations().map(animation=>animation.finished));});
   const modal=await page.evaluate(()=>{
    const popup=document.querySelector('.bulk-groups-dialog'),rect=popup.getBoundingClientRect(),content=popup.querySelector('.swal2-html-container');
    return {width:rect.width,fits:rect.left>=0&&rect.right<=innerWidth&&rect.top>=0&&rect.bottom<=innerHeight+1,contentFits:content.getBoundingClientRect().bottom<=rect.bottom,noHorizontalScroll:content.scrollWidth<=content.clientWidth+1,inputsUsable:[...popup.querySelectorAll('.form-control')].every(i=>i.getBoundingClientRect().width>=120),labelsFit:[...popup.querySelectorAll('.bulk-editor-row .btn')].every(b=>getComputedStyle(b).whiteSpace==='nowrap'&&b.scrollWidth<=b.clientWidth),scrollY:getComputedStyle(content).overflowY};
   });
   assert.equal(modal.fits,true);assert.equal(modal.contentFits,true);assert.equal(modal.noHorizontalScroll,true);assert.equal(modal.inputsUsable,true);assert.equal(modal.labelsFit,true);assert.equal(modal.scrollY,'auto');
   if(width>=768)assert.ok(modal.width>=600&&modal.width<=700);
   await page.screenshot({path:path.join(screenshots,`groups-${width}.png`),fullPage:true});
   await page.evaluate(()=>Swal.close());await page.locator('.bulk-groups-dialog').waitFor({state:'hidden'});
  }
  await page.evaluate(()=>document.body.classList.add('dark-mode'));
  await page.screenshot({path:path.join(screenshots,'320-dark.png'),fullPage:true});
  await page.locator('#bulk-manage').click();await page.locator('.bulk-groups-dialog').waitFor();
  await page.locator('.bulk-groups-dialog').evaluate(async el=>{await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));await Promise.all(el.getAnimations().map(animation=>animation.finished));});
  assert.equal(await page.locator('.bulk-groups-dialog').evaluate(el=>getComputedStyle(el).backgroundColor),'rgb(31, 41, 55)');
  assert.equal(await page.locator('.bulk-group-pill[aria-pressed="true"]').evaluate(el=>getComputedStyle(el).backgroundColor),'rgb(36, 59, 96)');
  await page.screenshot({path:path.join(screenshots,'groups-320-dark.png'),fullPage:true});
  await page.evaluate(()=>Swal.close());await page.locator('.bulk-groups-dialog').waitFor({state:'hidden'});
  await page.setViewportSize({width:1280,height:900});
  await page.goto('http://bulk.test/fixture?actor=approver');
  await page.waitForFunction(()=>document.getElementById('bulk-action').textContent==='Approve All (24)');
  await page.locator('.bulk-check').first().check();assert.equal(await page.locator('#bulk-action').isDisabled(),true);
  await verifyMultiRecordAcknowledgment('approve','#bulk-action');
  await page.locator('.bulk-check').first().uncheck();
  assert.equal(await page.locator('#bulk-workflow').count(),0);
  assert.equal(await page.locator('#bulk-all').isChecked(),false);
  await page.locator('#bulk-action').click();await page.locator('#bulk-phrase').waitFor();
  assert.equal(await page.locator('.swal2-title').textContent(),'Approve All Gas Slips?');
  assert.equal(await page.locator('.bulk-confirmation-description').count(),0);
  assert.match(await page.locator('.bulk-confirmation-warning').textContent(),/properly recommended/);
  assert.match(await page.locator('.bulk-confirmation-warning p').first().textContent(),/all 24 eligible gas slips/);
  assert.equal(await page.locator('.bulk-confirmation-warning p').count(),2);
  await page.setViewportSize({width:320,height:568});
  await page.locator('.bulk-confirmation-dialog').evaluate(async el=>{await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));await Promise.all(el.getAnimations().map(animation=>animation.finished));});
  const warningColors=[];
  for(const dark of [false,true]) {
   await page.evaluate(dark=>document.body.classList.toggle('dark-mode',dark),dark);
   await verifyWarningAppearance();
   assert.deepEqual(await computed(page,'.bulk-confirmation-warning',typography),references[dark].body);
   const layout=await page.evaluate(()=>{
    const popup=document.querySelector('.bulk-confirmation-dialog'),body=popup.querySelector('.swal2-html-container'),rect=popup.getBoundingClientRect(),actions=popup.querySelector('.swal2-actions').getBoundingClientRect();
    return {fits:rect.left>=0&&rect.right<=innerWidth&&rect.top>=0&&rect.bottom<=innerHeight+1,bodyFits:body.getBoundingClientRect().bottom<=rect.bottom,internalScroll:getComputedStyle(body).overflowY==='auto'&&body.scrollHeight>body.clientHeight,buttonsVisible:actions.bottom<=innerHeight,color:getComputedStyle(popup.querySelector('.bulk-confirmation-warning')).color};
   });
   await verifyStrongAppearance('APPROVE ALL');
   assert.equal(layout.fits,true);assert.equal(layout.bodyFits,true);assert.equal(layout.internalScroll,true);assert.equal(layout.buttonsVisible,true);warningColors.push(layout.color);
   await page.screenshot({path:path.join(screenshots,`approve-warning-320-${dark?'dark':'light'}.png`),fullPage:true});
  }
  assert.notEqual(warningColors[0],warningColors[1]);
  await page.setViewportSize({width:1280,height:900});
  await page.locator('#bulk-ack').check();await page.locator('#bulk-phrase').fill('APPROVE ALL');
  assert.equal(await page.locator('.swal2-confirm').isDisabled(),false);await cancel();
  await page.goto('http://bulk.test/fixture?actor=approver&bulk_group=1');
  await page.waitForFunction(()=>document.getElementById('bulk-action').textContent==='Approve All (12)');
  await page.locator('#bulk-action').click();await page.locator('.bulk-acknowledgment').waitFor();
  assert.equal(await page.locator('#bulk-phrase').count(),0);
  assert.match(await page.locator('.bulk-acknowledgment-description').textContent(),/all 12 affected gas slips will be approved/);
  assert.equal(await page.locator('.swal2-confirm').isDisabled(),true);await page.locator('#bulk-ack').check();assert.equal(await page.locator('.swal2-confirm').isDisabled(),false);await cancel();
  for(const view of ['recommended','approved']) {
   await page.goto('http://bulk.test/fixture?view='+view);
   await page.getByRole('tab',{name:'Routine Field Trips'}).waitFor();
   assert.equal(await page.getByRole('tab',{name:`All Gas Slips (${view==='recommended'?6:4} records)`,exact:true}).locator('.bulk-tab-count').textContent(),view==='recommended'?'6':'4');
   assert.equal(await page.getByRole('tab',{name:`Routine Field Trips (${view==='recommended'?2:3} records)`,exact:true}).locator('.bulk-tab-count').textContent(),view==='recommended'?'2':'3');
   assert.equal(await page.locator('#bulk-manage').count(),1);assert.equal(await page.locator('.bulk-page-header button').count(),0);
   assert.equal(await page.locator('#bulk-tabs + #bulk-manage').count(),1);
   assert.equal(await page.locator('#bulk-action,#bulk-reject,#bulk-all,#bulk-workflow,.bulk-check,.bulk-review,.bulk-toolbar').count(),0);
   assert.equal(await page.locator('tr[data-id="1"] td').nth(view==='approved'?1:0).textContent(),'1');
   await page.getByRole('tab',{name:'Routine Field Trips'}).click();
   await page.waitForURL('**bulk_group=1');
   assert.equal(await page.locator('.bulk-toolbar,.bulk-check').count(),0);
   await page.locator('tr[data-id="1"] td').last().click();await page.locator('#gasSlipModal #recommendBtn').waitFor();
   await page.evaluate(()=>bootstrap.Modal.getInstance(document.getElementById('gasSlipModal')).hide());
   await page.locator('#bulk-manage').click();await page.locator('.bulk-groups-dialog').waitFor();
   await page.evaluate(()=>Swal.close());await page.locator('.bulk-groups-dialog').waitFor({state:'hidden'});
   if(view==='approved') {
    assert.equal(await page.locator('#printSelected').count(),1);assert.equal(await page.locator('[data-bs-target="#filterModal"]').count(),1);
    assert.equal(await page.locator('.bulk-navigation > .bulk-print-actions #printSelected').count(),1);
    for(const width of [1280,768,390,320]) {
     await page.setViewportSize({width,height:900});
     const layout=await page.evaluate(()=>{const nav=document.querySelector('.bulk-navigation').getBoundingClientRect(),tabs=document.querySelector('.bulk-nav-left').getBoundingClientRect(),print=document.getElementById('printSelected').getBoundingClientRect(),settings=document.querySelector('[data-bs-target="#filterModal"]').getBoundingClientRect();return {sameRow:Math.abs(tabs.top+tabs.height/2-print.top-print.height/2)<2,order:print.right<=settings.left+1,fit:settings.right<=nav.right+1&&print.left>=nav.left-1,overflow:document.documentElement.scrollWidth>innerWidth};});
     assert.equal(layout.order,true);assert.equal(layout.fit,true);assert.equal(layout.overflow,false);if(width>=768)assert.equal(layout.sameRow,true);
     await page.screenshot({path:path.join(screenshots,`approved-toolbar-${width}.png`),fullPage:true});
    }
    await page.locator('[data-bs-target="#filterModal"]').click();await page.locator('#filterModal').waitFor({state:'visible'});
    assert.equal(await page.locator('#filterModal form').count(),1);await page.locator('#filterModal .btn-close').click();await page.locator('#filterModal').waitFor({state:'hidden'});
    await page.locator('#printSelected').click();await page.getByText('Selection Required',{exact:true}).waitFor();await page.evaluate(()=>Swal.close());
    await page.locator('#checkAll').check();assert.equal(await page.locator('.print-check:checked').count(),3);
    await page.locator('#checkAll').uncheck();assert.equal(await page.locator('.print-check:checked').count(),0);
    await page.locator('#checkAll').check();await page.locator('#printSelected').click();await page.waitForURL('**/print_gas_slip.php?ids=1%2C2%2C3');
   } else assert.equal(await page.locator('.bulk-actions').count(),0);
  }
  console.log(`Layout screenshots: ${screenshots}`);
  assert.equal(processCount,0);assert.deepEqual(errors,[]);
  const source=fs.readFileSync(path.join(root,'assets/js/bulk-processing.js'),'utf8');
  const formatter=new Function('return ('+source.slice(source.indexOf(' function bulkResultMessage('),source.indexOf(' async function act(')).trim()+')')();
  for(const action of ['recommend','approve','reject']) {
   const past={recommend:'recommended',approve:'approved',reject:'rejected'}[action];
   for(const n of [1,2]) {
    const message=formatter(action,{successful:Array(n).fill({}),skipped:[],failed:[]},n);
    assert.equal(message.icon,'success');assert.ok(message.text.startsWith(`${n} gas ${n===1?'slip has':'slips have'} been successfully ${past}`));assert.equal(message.confirmButtonText,'OK');
   }
   assert.equal(formatter(action,{successful:[{}],skipped:[],failed:[]},2).icon,'warning','Unreported snapshot records cannot be falsely presented as complete success');
   for(const outcome of ['success','partial','failure']) {
    resultSummary=outcome==='success'?{successful:[{id:1},{id:2}],skipped:[],failed:[]}:outcome==='partial'?{successful:[{id:1}],skipped:[{id:2,reason:'Private diagnostic /secret/path'}],failed:[]}:{successful:[],skipped:[],failed:[{id:1,reason:'Private SQL diagnostic'},{id:2}]};
    await page.evaluate(()=>sessionStorage.clear());
    const target=`http://bulk.test/fixture?actor=${action==='approve'?'approver':'recommender'}&bulk_group=${action==='reject'?1:0}`;
    await page.goto(target);await page.waitForFunction(()=>document.querySelectorAll('.bulk-check').length>1);
    await page.evaluate(dark=>document.body.classList.toggle('dark-mode',dark),outcome==='partial');
    await page.locator('.bulk-check').nth(0).check();await page.locator('.bulk-check').nth(1).check();
    await page.locator(action==='reject'?'#bulk-reject':'#bulk-action').click();await page.locator('#bulk-ack').check();
    if(action==='reject')await page.locator('#bulk-reason').fill('Shared rejection reason');
    await page.locator('.swal2-confirm').click();
    const title=outcome==='success'?`Gas Slips ${past[0].toUpperCase()+past.slice(1)} Successfully!`:outcome==='partial'?'Some Gas Slips Could Not Be Processed':`Unable to ${action[0].toUpperCase()+action.slice(1)} Gas Slips`;
    await page.getByRole('heading',{name:title,exact:true}).waitFor();
    assert.equal(await page.locator('.swal2-popup').evaluate(el=>getComputedStyle(el).backgroundColor),outcome==='partial'?'rgb(31, 41, 55)':'rgb(255, 255, 255)');
    const text=await page.locator('.swal2-html-container').textContent();
    assert.match(text,outcome==='success'?new RegExp(`2 gas slips have been successfully ${past}`):outcome==='partial'?new RegExp(`1 of 2 gas slips was successfully ${past}`):new RegExp(`No gas slips were ${past}`));
    assert.equal(await page.locator(`.swal2-icon.swal2-${outcome==='success'?'success':outcome==='partial'?'warning':'error'}`).count(),1);
    assert.equal(await page.locator('.swal2-html-container details,.swal2-html-container ul').count(),0);assert.ok(!/secret|diagnostic|SQL|skipped|failed/.test(text));
    if(action==='recommend')await page.screenshot({path:path.join(screenshots,`${action}-${outcome}-result.png`),fullPage:true});
    const refreshed=page.waitForEvent('framenavigated',frame=>frame===page.mainFrame());await page.getByRole('button',{name:'OK',exact:true}).click();await refreshed;
    await page.waitForFunction(()=>document.querySelectorAll('.bulk-check').length>1);
    assert.equal(new URL(page.url()).searchParams.get('bulk_group'),action==='reject'?'1':'0');assert.equal(await page.locator('.bulk-check:checked').count(),0);
   }
  }
  assert.equal(processCount,9);assert.deepEqual(errors,[]);
  console.log('PASS: Recommend/Approve/Reject full success, partial warning and complete failure; actual backend counts, singular/plural grammar, no expandable breakdown/diagnostics, light/dark themes, OK refresh and preserved group with cleared selection.');
  console.log('PASS: Group create/select, inline rename/cancel, confirmed delete/cancel, manual/suggested/keyboard names, duplicate feedback, removal/counts, light/dark mobile editor; existing bulk/individual UI and safeguards');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
