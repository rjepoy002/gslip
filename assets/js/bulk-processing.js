/* Additive bulk controls; openGasSlipDetails remains the single individual details interface. */
(() => {
 'use strict';
 const root = document.getElementById('bulk-processing');
 if (!root) return;
 const $ = id => document.getElementById(id);
 const params = new URLSearchParams(location.search);
 let group = Number(params.get('bulk_group') || 0);
 const actionable = root.dataset.page === 'pending';
 const workflow = {value:'recommend'};
 let records = [], groups = [], counts = {}, selected = new Set(), busy = false, initialized = false, initializing = false;
 const key = () => `gslip-bulk:${root.dataset.user}:${location.pathname}:${workflow.value}:${group}`;
 const eligible = () => records;
 const ids = () => eligible().map(r => Number(r.id));
 const escape = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 async function api(op, data = {}) {
  const response = await fetch('bulk_processing.php', {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body:JSON.stringify({csrf:root.dataset.csrf,page:root.dataset.page,op,group,...data})});
  const jsonType = /\bapplication\/json\b/i.test(response.headers.get('content-type') || '');
  let result;
  try { result = jsonType ? JSON.parse(await response.text()) : null; } catch { result=null; }
  if (!result || typeof result !== 'object' || typeof result.success !== 'boolean') {
   throw new Error(response.status===401 || response.status===403 ? 'Your session could not be verified. Refresh the page and sign in again.' : 'The server returned an unreadable response. Please try again.');
  }
  if (!response.ok || !result.success) throw new Error(typeof result.message==='string' ? result.message : 'Bulk request failed. Please try again.');
  return result;
 }
 const error = e => Swal.fire({icon:'error',title:'Unable to continue',text:e.message});
 function save() { sessionStorage.setItem(key(),JSON.stringify({ids:[...selected],eligible:ids()})); }
 function bulkVisibility(show) {
  if(!actionable)return;
  root.querySelector('.bulk-actions').hidden=!show;
  document.querySelectorAll('.bulk-select-column').forEach(cell=>cell.hidden=!show);
  const empty=document.querySelector('.bulk-table-scroll tbody td[colspan]');
  if(empty)empty.colSpan=show?8:7;
 }
 function update() {
  root.dataset.busy=String(busy);
  if(!actionable) return;
  const n=ids().length,available=initialized&&n>=2;
  if(!available)selected.clear();
  const x=selected.size,all=n>0&&x===n;
  bulkVisibility(available);
  $('bulk-all').setAttribute('aria-label',`Select all ${n} eligible gas slips in ${groups.find(g=>Number(g.id)===group)?.group_name||'All Gas Slips'} across all pages`);
  $('bulk-all').checked = all; $('bulk-all').indeterminate = x > 0 && !all; $('bulk-all').disabled = !available || busy;
  const verb = workflow.value === 'recommend' ? 'Recommend' : 'Approve';
  $('bulk-action').textContent = `${verb} ${x && !all ? 'Selected' : 'All'} (${x && !all ? x : n})`;
  $('bulk-action').disabled = !available || x===1 || busy;
  root.querySelector('.bulk-actions').title=x===1?'Select at least two gas slips, or open the single gas slip details for individual processing.':'';
  $('bulk-reject').hidden = !group;
  $('bulk-reject').textContent = `Reject ${all ? 'All' : 'Selected'} (${x})`;
  $('bulk-reject').disabled = !available || !group || x<2 || busy;
  root.querySelectorAll('.bulk-tab').forEach(b => b.disabled = busy);
  document.querySelectorAll('.bulk-check').forEach(c => {c.checked=selected.has(Number(c.value)); c.disabled=busy;});
  save();
 }
 function navigate(nextGroup) {
  sessionStorage.removeItem(key());
  const url = new URL(location.href); url.searchParams.delete('page');
  url.searchParams.set('bulk_group',nextGroup); url.searchParams.delete('bulk_workflow');
  location.href=url.href;
 }
 function tabs() {
  $('bulk-tabs').replaceChildren();
  [{id:0,group_name:'All Gas Slips'},...groups.filter(g=>g.names.length)].forEach(g => {
   const button=document.createElement('button'); button.type='button'; button.className=`bulk-tab nav-link text-nowrap ${Number(g.id)===group?'active':''}`;
   button.setAttribute('role','tab'); button.setAttribute('aria-selected',Number(g.id)===group?'true':'false');
   button.textContent=g.group_name;
   const badge=document.createElement('span');badge.className='bulk-tab-count';badge.textContent=counts[g.id];badge.setAttribute('aria-hidden','true');button.append(badge);button.setAttribute('aria-label',`${g.group_name} (${counts[g.id]} ${actionable?'eligible':'records'})`);
   button.onclick=()=>navigate(Number(g.id)); $('bulk-tabs').append(button);
  });
  $('bulk-tabs').querySelector('[aria-selected="true"]')?.scrollIntoView({block:'nearest',inline:'nearest'});
 }
 function rowControls() {
  if(!actionable) return;
  document.querySelectorAll('.gas-slip-row').forEach(row=>{
   const cell=row.querySelector('td.bulk-select-column');
   if(!cell)return;
   cell.replaceChildren();
   cell.onclick=e=>e.stopPropagation();cell.onkeydown=e=>e.stopPropagation();
   const record=records.find(r=>Number(r.id)===Number(row.dataset.id));
   if (!record || records.length<2) return;
    const c=document.createElement('input');c.type='checkbox';c.className='bulk-check';c.value=record.id;
    c.setAttribute('aria-label',`Select gas slip ${record.gas_slip_id}`);
    c.onchange=()=>{c.checked?selected.add(Number(record.id)):selected.delete(Number(record.id));update();};cell.append(c);
  });
 }
 async function load(restore = true) {
  const old=ids().join(',');
  const state=await api('state');
  if(!Array.isArray(state.records)||!Array.isArray(state.groups)||!['recommend','approve'].includes(state.action)) throw new Error('The server returned incomplete bulk-processing data. Please try again.');
  if(!state.counts||![0,...state.groups.map(g=>g.id)].every(id=>Number.isInteger(state.counts[id])&&state.counts[id]>=0))throw new Error('Record counts are unavailable. Please try again.');
  records=state.records;groups=state.groups;counts=state.counts||{};workflow.value=state.action;initialized=true;
  if (group && !groups.some(g=>Number(g.id)===group && g.names.length)) {navigate(0);return;}
  const available=new Set(ids());
  if (restore) {
   try {const saved=JSON.parse(sessionStorage.getItem(key())); if(saved){selected=new Set(saved.ids.filter(id=>available.has(id)));if([...saved.eligible].sort().join(',')!==[...available].sort().join(',')) $('bulk-message').textContent='Eligibility changed. Review the updated count and selection.';}} catch {selected.clear();}
  } else {selected=new Set([...selected].filter(id=>available.has(id)));if(old!==ids().join(',')) $('bulk-message').textContent='Eligibility changed. Review the updated count and selection.';}
  tabs();rowControls();update();
  if(group && records.length===0) {const empty=document.querySelector('.styled-table tbody td[colspan]');if(empty)empty.textContent=actionable?'No eligible gas slips in this group.':'No gas slips in this group.';}
  document.querySelectorAll('.pagination a').forEach(a=>{const u=new URL(a.href);u.searchParams.set('bulk_group',group);u.searchParams.delete('bulk_workflow');a.href=u.href;});
 }
 function bulkResultMessage(action,summary,expectedCount) {
  if(!summary||!['successful','skipped','failed'].every(key=>Array.isArray(summary[key])))throw new Error('Processing results are unavailable. Refresh the list to check the gas slips before trying again.');
  const successful=summary.successful.length;
  const total=Math.max(expectedCount,successful+summary.skipped.length+summary.failed.length);
  const verb=action==='reject'?'Reject':action==='approve'?'Approve':'Recommend';
  const past=action==='reject'?'rejected':action==='approve'?'approved':'recommended';
  if(!successful)return {icon:'error',title:`Unable to ${verb} Gas Slips`,text:`No gas slips were ${past}. Please refresh the list and try again.`,confirmButtonText:'OK'};
  if(successful<total)return {icon:'warning',title:'Some Gas Slips Could Not Be Processed',text:`${successful} of ${total} gas slips ${successful===1?'was':'were'} successfully ${past}. Some requests could not be processed. Please refresh the list and review the remaining requests.`,confirmButtonText:'OK'};
  const subject=`${successful} gas ${successful===1?'slip has':'slips have'} been successfully ${past}`;
  const suffix=action==='reject'?`. The rejection reason has been recorded for each gas slip.`:action==='approve'?` and ${successful===1?'is':'are'} ready for the next stage of processing.`:' and forwarded for approval.';
  return {icon:'success',title:`Gas ${successful===1?'Slip':'Slips'} ${past[0].toUpperCase()+past.slice(1)} Successfully!`,text:subject+suffix,confirmButtonText:'OK'};
 }
 async function act(action) {
  if(busy || !initialized || initializing || records.length<2 || selected.size===1) return;
  busy=true;update();
  try {
   const data=await api('snapshot',{action,ids:[...selected],eligible_ids:ids()});
   const s=data.snapshot, count=s.records.length, verb=action==='reject'?'Reject':action==='approve'?'Approve':'Recommend';
   const phrase=`${action.toUpperCase()} ALL`;
   const allLabel=s.all?'All':'Selected';
   const scope=group?'the selected group':'the All Gas Slips tab';
   const acrossPages=group?'':' This includes records across all pages.';
   const warnings={
    recommend:{heading:'Please review before proceeding',paragraphs:[
     `This action will recommend all ${count} eligible gas slips in ${scope}, including those you may not have individually opened or reviewed. Any incorrect or incomplete requests may also be forwarded for approval.${acrossPages}`,
     'Please ensure that the gas slip details, destinations, and requested fuel quantities are accurate before confirming. Proceed only if you are confident that all included requests are ready for recommendation.'
    ]},
    approve:{heading:'Important: Verify before approving',paragraphs:[
     `This action will approve all ${count} eligible gas slips in ${scope}, including requests you may not have individually opened or reviewed. Any incorrect or incomplete information may also be approved and allowed to proceed to the next stage.${acrossPages}`,
     'Please verify that the requests, destinations, and fuel quantities are accurate and properly recommended before confirming. Proceed only if you are confident that all included requests meet the requirements for approval.'
    ]},
    reject:{heading:'Warning: All selected requests will be rejected',paragraphs:[
     `You are about to reject all ${count} selected gas slips in this group. All affected requests will be processed using the same rejection reason, including records you may not have reviewed individually.`,
     'This may delay their processing and require corrections or resubmission. Please ensure that every selected request should be rejected before proceeding. The rejection reason you provide will be recorded for each successfully rejected gas slip.'
    ]}
   };
   const selectedWarnings={
    recommend:{heading:'Please review before proceeding',paragraphs:[
     `This action will recommend all ${count} selected gas slips and forward them for approval. Any incorrect or incomplete requests among your selection may also be forwarded.`,
     'Please verify that the selected gas slips have the correct details, destinations, and requested fuel quantities before confirming. Proceed only if you are confident that every selected request is ready for recommendation.'
    ]},
    approve:{heading:'Important: Verify before approving',paragraphs:[
     `This action will approve all ${count} selected gas slips, including any requests that may contain incorrect or incomplete information. Once approved, these requests may proceed to the next stage of processing.`,
     'Please ensure that the selected gas slips have accurate request details, destinations, and fuel quantities and have completed the required recommendation process before confirming.'
    ]},
    reject:{heading:'Warning: Multiple requests will be rejected',paragraphs:[
     `This action will reject all ${count} selected gas slips using the same rejection reason. Rejected requests may require corrections or resubmission, potentially delaying their processing.`,
     'Please verify that every selected request should be rejected before proceeding. The shared rejection reason will be recorded for each successfully rejected gas slip.'
    ]}
   };
   const warning=(s.all?warnings:selectedWarnings)[action];
   const verification=s.strong&&s.all&&!group&&action!=='reject';
   const needsAcknowledgment=s.strong||count>=2;
   const acknowledgedAction=action==='reject'?'rejected using the same reason':action==='approve'?'approved':'recommended';
   let interval, checking=false, valid=true;
   const confirmation=await Swal.fire({
    customClass:{popup:`bulk-confirmation-dialog${needsAcknowledgment?' bulk-acknowledgment-dialog':''}${verification?' bulk-verification-dialog':''}`},
    title:`${verb} ${allLabel} Gas Slips?`,icon:'warning',showCancelButton:true,
    confirmButtonText:verification?`${verb} All (${count})`:s.all?`Confirm ${verb} All (${count})`:`Confirm ${verb} (${count})`,confirmButtonColor:action==='reject'?'#dc3545':undefined,
    reverseButtons:verification,
    html:`<div class="bulk-confirmation-warning"><strong class="bulk-warning-heading d-block mb-2">${escape(warning.heading)}</strong>${warning.paragraphs.map(p=>`<p>${escape(p)}</p>`).join('')}</div>
     ${action==='reject'?'<label for="bulk-reason" class="form-label">Shared rejection reason (required)</label><textarea id="bulk-reason" class="swal2-textarea bulk-rejection-reason" maxlength="5000"></textarea>':''}
     ${needsAcknowledgment?`<label class="bulk-acknowledgment"><input id="bulk-ack" type="checkbox" aria-labelledby="bulk-ack-heading" aria-describedby="bulk-ack-description"><span><strong id="bulk-ack-heading" class="bulk-acknowledgment-heading">I understand the consequences</strong><span id="bulk-ack-description" class="bulk-acknowledgment-description">I acknowledge that all ${count} affected gas slips will be ${acknowledgedAction}, including those I may not have reviewed individually.</span></span></label>`:''}
     ${verification?`<div class="bulk-typed-confirmation"><label for="bulk-phrase" class="bulk-confirmation-instruction">Type <strong class="bulk-required-phrase">${phrase}</strong> to confirm</label><input id="bulk-phrase" class="form-control" placeholder="Enter confirmation phrase" autocomplete="off"></div>`:''}<div id="bulk-validity" role="status" class="small text-danger"></div>`,
    didOpen:()=>{
     const check=()=>{const ok=valid&&(!needsAcknowledgment||$('bulk-ack').checked)&&(!s.strong||action==='reject'||$('bulk-phrase').value===phrase)&&(action!=='reject'||$('bulk-reason').value.trim());Swal.getConfirmButton().disabled=!ok;};
     Swal.getHtmlContainer().addEventListener('input',check);check();
     interval=setInterval(async()=>{if(checking)return;checking=true;try{const r=await api('validate',{token:data.token});valid=r.valid;if(!valid)$('bulk-validity').textContent='Eligibility changed. Cancel and refresh the gas slips before confirming again.';}catch{valid=false;if($('bulk-validity'))$('bulk-validity').textContent='Unable to validate snapshot. Cancel and try again.';}finally{checking=false;if(Swal.isVisible())check();}},3000);
    },
    willClose:()=>clearInterval(interval),
    preConfirm:async()=>{
     if(!valid)return false;
     const input={token:data.token,acknowledge:!!$('bulk-ack')?.checked,phrase:$('bulk-phrase')?.value||'',reason:$('bulk-reason')?.value||''};
     try {const check=await api('validate',{token:data.token});if(!check.valid){valid=false;Swal.showValidationMessage('Eligibility changed. Cancel and refresh the gas slips before confirming again.');return false;}return input;}catch(e){Swal.showValidationMessage(e.message);return false;}
    }
   });
   if(!confirmation.isConfirmed){if(!valid)await load(false);return;}
   Swal.fire({title:`${verb} in progress…`,text:'Please keep this page open.',allowOutsideClick:false,allowEscapeKey:false,didOpen:()=>Swal.showLoading()});
   const result=await api('process',confirmation.value), summary=result.summary;
   selected.clear();save();
   await Swal.fire(bulkResultMessage(action,summary,count));
   location.reload();
  } catch(e){await error(e);await load(false).catch(()=>{});}finally{busy=false;update();}
 }
 async function manage() {
  try {
   const state=await api('state');groups=state.groups;
   let selectedGroup=groups.some(g=>Number(g.id)===group)?group:Number(groups[0]?.id||0);
   let editorBusy=false,renaming=false,deleting=false,disposed=false,activeMembershipChanged=false,timer,sequence=0;
   await Swal.fire({title:'Manage Groups',width:660,customClass:{popup:'bulk-groups-dialog'},showConfirmButton:false,showCloseButton:true,allowOutsideClick:()=>!editorBusy,allowEscapeKey:()=>!editorBusy,
    html:`<div class="text-start"><p class="bulk-groups-subtitle">Organize gas slips by Requested By names.</p>
     <section class="bulk-editor-section" aria-labelledby="bulk-create-heading"><h3 id="bulk-create-heading">Create a new group</h3><form id="bulk-create" class="bulk-editor-row"><input id="bulk-new-group" class="form-control" maxlength="100" placeholder="Enter group name" aria-label="New group name" required><button class="btn btn-primary" disabled><span aria-hidden="true">+</span> Create</button></form></section>
     <section class="bulk-editor-section" aria-labelledby="bulk-your-groups-heading"><h3 id="bulk-your-groups-heading">Your groups</h3><div id="bulk-group-picker" class="bulk-group-picker" role="group" aria-label="Your groups"></div></section>
     <section class="bulk-editor-section bulk-details-section" aria-labelledby="bulk-details-heading"><h3 id="bulk-details-heading">Selected group details</h3><div id="bulk-editors"></div></section>
     <div id="bulk-editor-message" role="status" aria-live="polite" class="bulk-editor-message"></div></div>`,
    willClose:()=>{disposed=true;clearTimeout(timer);sequence++;},didOpen:()=>{
    const message=(text,isError=false)=>{if(disposed)return;$('bulk-editor-message').textContent=text;$('bulk-editor-message').classList.toggle('text-danger',isError);};
    const updateBusy=()=>{
     if(disposed)return;
     const popup=Swal.getPopup();popup.setAttribute('aria-busy',String(editorBusy));
     popup.querySelectorAll('button,input').forEach(el=>el.disabled=editorBusy);
     $('bulk-create').querySelector('button').disabled=editorBusy||!$('bulk-new-group').value.trim();
     const save=$('bulk-rename-form')?.querySelector('button[type="submit"]');if(save)save.disabled=editorBusy||!$('bulk-rename-input').value.trim();
     const add=$('bulk-add-name')?.querySelector('button');if(add)add.disabled=editorBusy||!$('bulk-name-input').value.trim();
    };
    const mutate=async(op,data,feedback)=>{
     if(editorBusy)return;editorBusy=true;updateBusy();message('Saving…');
     try {
      const result=await api(op,{group:selectedGroup,...data});groups=result.groups;
      if(selectedGroup===group&&['add_name','remove_name'].includes(op))activeMembershipChanged=true;
      if(op==='create'){selectedGroup=Number(result.group);$('bulk-new-group').value='';}
      if(op==='delete')selectedGroup=Number(groups[0]?.id||0);
      renaming=false;deleting=false;editorBusy=false;
      if(!disposed){render(op==='rename'?'bulk-rename':op==='delete'?'bulk-new-group':'bulk-name-input');message(feedback);}
     } catch(e){editorBusy=false;updateBusy();message(e.message,true);}
    };
    const render=(focusId='')=>{
     clearTimeout(timer);sequence++;if(disposed)return;
     const picker=$('bulk-group-picker');picker.replaceChildren();
     groups.forEach(g=>{
      const pill=document.createElement('button');pill.type='button';pill.id=`bulk-picker-${g.id}`;pill.className='bulk-group-pill';pill.setAttribute('aria-pressed',String(Number(g.id)===selectedGroup));pill.textContent=`${g.group_name} (${g.names.length})`;
      pill.onclick=()=>{selectedGroup=Number(g.id);renaming=false;deleting=false;message('');render(pill.id);};picker.append(pill);
     });
     if(!groups.length)picker.textContent='No groups yet. Create your first group above.';
     const box=$('bulk-editors'),g=groups.find(g=>Number(g.id)===selectedGroup);box.replaceChildren();
     if(!g){box.textContent='Select a group to manage its Requested By names.';updateBusy();if(focusId)$(focusId)?.focus();return;}
     const panel=document.createElement('div');panel.className='bulk-group-card';
     panel.innerHTML=`<div class="bulk-group-details-header"><div class="bulk-group-title"><h4>${escape(g.group_name)}</h4><span>${g.names.length} configured ${g.names.length===1?'name':'names'}</span></div><div class="bulk-editor-actions"><button type="button" id="bulk-rename" class="btn btn-sm btn-outline-primary">Rename</button><button type="button" id="bulk-delete" class="btn btn-sm btn-outline-danger bulk-delete-icon" aria-label="Delete group ${escape(g.group_name)}" title="Delete group"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 14h10l1-14M10 10v7m4-7v7" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></button></div></div>
      ${renaming?`<form id="bulk-rename-form" class="bulk-editor-row bulk-rename-form"><input id="bulk-rename-input" class="form-control" maxlength="100" aria-label="Group name" value="${escape(g.group_name)}" required><div class="bulk-editor-actions"><button type="submit" class="btn btn-sm btn-primary">Save</button><button type="button" id="bulk-rename-cancel" class="btn btn-sm btn-outline-secondary">Cancel</button></div></form>`:''}
      ${deleting?`<div class="bulk-delete-confirm" role="group" aria-labelledby="bulk-delete-heading"><strong id="bulk-delete-heading">Delete ${escape(g.group_name)}?</strong><p>Only this group configuration and its configured names will be deleted. Existing gas slips will not be deleted.</p><div class="bulk-editor-actions"><button type="button" id="bulk-delete-cancel" class="btn btn-sm btn-outline-secondary">Cancel</button><button type="button" id="bulk-delete-confirm" class="btn btn-sm btn-danger">Delete group</button></div></div>`:''}
      <div class="chips" aria-label="Configured Requested By names"></div><p class="bulk-name-help">Removing a name changes this group only. Gas slips are kept.</p>
      <label for="bulk-name-input" class="form-label">Add a Requested By name</label><form id="bulk-add-name" class="add bulk-editor-row"><input id="bulk-name-input" class="form-control name" maxlength="100" placeholder="Search or enter a name" aria-label="Requested By name" role="combobox" aria-autocomplete="list" aria-controls="bulk-name-suggestions" aria-expanded="false" autocomplete="off" required><button class="btn btn-sm btn-primary" disabled>Add</button></form><div id="bulk-name-suggestions" class="suggestions list-group" role="listbox" aria-label="Requested By suggestions"></div>`;
     box.append(panel);
     g.names.forEach(n=>{const chip=document.createElement('button');chip.type='button';chip.className='bulk-name-chip';chip.textContent=`${n.requested_by_name} ×`;chip.setAttribute('aria-label',`Remove ${n.requested_by_name}`);chip.title='Remove this name from the group; keep gas slips';chip.onclick=()=>mutate('remove_name',{name_id:Number(n.id)},'Name removed from the group. Gas slips were kept.');panel.querySelector('.chips').append(chip);});
     if(!g.names.length)panel.querySelector('.chips').textContent='No Requested By names configured yet.';
     $('bulk-rename').onclick=()=>{renaming=true;deleting=false;render('bulk-rename-input');$('bulk-rename-input').select();};
     $('bulk-delete').onclick=()=>{deleting=true;renaming=false;render('bulk-delete-confirm');};
     if(renaming){$('bulk-rename-form').onsubmit=e=>{e.preventDefault();mutate('rename',{name:$('bulk-rename-input').value},'Group renamed.');};$('bulk-rename-input').oninput=updateBusy;$('bulk-rename-cancel').onclick=()=>{renaming=false;render('bulk-rename');};}
     if(deleting){$('bulk-delete-confirm').onclick=()=>mutate('delete',{},'Group deleted. Gas slips were kept.');$('bulk-delete-cancel').onclick=()=>{deleting=false;render('bulk-delete');};}
     const input=$('bulk-name-input'),list=$('bulk-name-suggestions');let names=[],active=-1;
     const highlight=()=>{[...list.children].forEach((el,i)=>{el.setAttribute('aria-selected',String(i===active));el.classList.toggle('active',i===active);if(i===active)el.scrollIntoView({block:'nearest'});});if(active>=0)input.setAttribute('aria-activedescendant',list.children[active].id);else input.removeAttribute('aria-activedescendant');};
     const addName=name=>mutate('add_name',{name},'Requested By name added.');
     input.oninput=()=>{
      updateBusy();clearTimeout(timer);const current=++sequence;names=[];active=-1;list.replaceChildren();input.setAttribute('aria-expanded','false');input.removeAttribute('aria-activedescendant');
      timer=setTimeout(async()=>{try{
       const result=await api('suggest',{query:input.value});if(disposed||current!==sequence||!input.isConnected)return;
       names=result.names;names.forEach((name,i)=>{const option=document.createElement('button');option.type='button';option.id=`bulk-suggestion-${i}`;option.className='list-group-item list-group-item-action';option.setAttribute('role','option');option.setAttribute('aria-selected','false');option.tabIndex=-1;option.textContent=name;option.onclick=()=>addName(name);list.append(option);});input.setAttribute('aria-expanded',String(names.length>0));
      }catch(e){if(!disposed&&current===sequence)message(e.message,true);}},250);
     };
     input.onkeydown=e=>{if((e.key==='ArrowDown'||e.key==='ArrowUp')&&names.length){e.preventDefault();active=active<0?(e.key==='ArrowDown'?0:names.length-1):(active+(e.key==='ArrowDown'?1:-1)+names.length)%names.length;highlight();}else if(e.key==='Enter'&&active>=0){e.preventDefault();addName(names[active]);}else if(e.key==='Escape'&&names.length){e.preventDefault();e.stopPropagation();sequence++;names=[];active=-1;list.replaceChildren();input.setAttribute('aria-expanded','false');input.removeAttribute('aria-activedescendant');}};
     $('bulk-add-name').onsubmit=e=>{e.preventDefault();addName(input.value);};updateBusy();if(focusId)$(focusId)?.focus();
    };
    $('bulk-new-group').oninput=updateBusy;
    $('bulk-create').onsubmit=e=>{e.preventDefault();if($('bulk-new-group').value.trim())mutate('create',{name:$('bulk-new-group').value},'Group created. Add Requested By names below.');};
    render();
   }});
   if(group&&!groups.some(g=>Number(g.id)===group&&g.names.length)){navigate(0);return;}
   if(activeMembershipChanged){location.reload();return;}
   await load(false);
  } catch(e){error(e);}
 }
 if(actionable) {
  $('bulk-all').onchange=e=>{selected=e.target.checked?new Set(ids()):new Set();update();};
  $('bulk-action').onclick=()=>act(workflow.value);$('bulk-reject').onclick=()=>act('reject');
 }
 $('bulk-manage').onclick=manage;
 async function initialize() {
  if(initializing) return;
  initializing=true;initialized=false;
  bulkVisibility(false);
  $('bulk-retry').hidden=true;$('bulk-retry').disabled=true;
  $('bulk-message').textContent='Loading bulk-processing controls…';
  if(actionable){$('bulk-action').textContent='Loading…';$('bulk-action').disabled=true;$('bulk-all').disabled=true;$('bulk-reject').disabled=true;}
  try {await load();$('bulk-message').textContent='';}
  catch(e){
   initialized=false;
   $('bulk-message').textContent=e instanceof TypeError?'Unable to connect to the server. Please try again.':e.message;
   $('bulk-retry').hidden=false;
   if(actionable){$('bulk-action').textContent='Unavailable';$('bulk-action').disabled=true;$('bulk-all').disabled=true;$('bulk-reject').disabled=true;}
  } finally {initializing=false;$('bulk-retry').disabled=false;}
 }
 $('bulk-retry').onclick=initialize;
 initialize();
})();
