const assert=require('node:assert/strict'),path=require('node:path');
const {chromium}=require(process.env.GSLIP_UI_PLAYWRIGHT||'playwright');
const fixtures=[
 {route_id:10,route_code:'PX10',name:'Bapa',km:30,fuel_allocation:2,is_fixed_fuel:0},
 {route_id:2,route_code:'PX2',name:'Bapa',km:12.75,fuel_allocation:1,is_fixed_fuel:0},
 {route_id:1,route_code:'PX1',name:'Other',km:null,fuel_allocation:1,is_fixed_fuel:0},
 {route_id:20,route_code:null,name:'Uncoded',km:5,fuel_allocation:3,is_fixed_fuel:1}
];
(async()=>{
 const browser=await chromium.launch({channel:'chrome',headless:true});
 try{
  const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('http://dest.test/**',async route=>{
   const url=new URL(route.request().url());
   if(url.pathname.endsWith('get_destinations.php')){
    const approved=url.searchParams.get('useRoutes')==='1';
    return route.fulfill({json:{destinations:approved?fixtures.filter(f=>f.route_code):fixtures.filter(f=>!f.route_code),route_codes:Object.fromEntries(fixtures.map(f=>[f.route_id,f.route_code]))}});
   }
   return route.fulfill({body:'<html><body></body></html>',contentType:'text/html'});
  });
  await page.goto('http://dest.test/fixture');
  await page.setContent(`<table id="gasSlipGrid"><tbody>${[1,2].map(i=>`<tr><td><input class="vehicle-category" value="2-wheels"><input class="destinations-data" value="[]"><span class="dest-count">0</span></td><td class="destination-cell"><button>Destinations ${i}</button></td></tr>`).join('')}</tbody></table><div id="destinationsModal"><input id="useRoutes" type="checkbox"><input id="destinationSearch"><div id="destinationList"></div><table id="selectedDestinationsTable"><thead><tr><th>#</th><th>Destination</th><th>KM</th><th>Action</th></tr></thead><tbody></tbody></table><button id="saveDestinationsBtn">Save Destinations</button></div>`);
  await page.evaluate(()=>{window.APP={USER_ORIGIN:'Test',USER_ORIGIN_NAME:'Test'};const modal={show(){},hide(){}};window.bootstrap={Modal:{getOrCreateInstance:()=>modal,getInstance:()=>modal}};});
  await page.addScriptTag({path:path.resolve(__dirname,'../assets/js/destinations.js')});
  await page.locator('.destination-cell button').first().click();
  await page.waitForSelector('#destinationList label');
  const ids=()=>page.locator('#destinationList input').evaluateAll(inputs=>inputs.map(i=>Number(i.dataset.routeId)));
  assert.deepEqual(await ids(),[1,2,10],'Natural route order');
  assert.match(await page.locator('#destinationList label').nth(1).textContent(),/PX2 - Bapa\s*\(12.75 km\)/);
  assert.equal(await page.locator('#destinationList input[data-route-id="2"]').inputValue(),'Bapa','Checkbox value unchanged');
  await page.locator('#destinationList input[data-route-id="2"]').check();
  await page.fill('#destinationSearch','pX2');
  assert.equal(await page.locator('#destinationList label:visible').count(),1,'Case-insensitive route search');
  await page.fill('#destinationSearch','bAPA');assert.equal(await page.locator('#destinationList label:visible').count(),2);
  await page.fill('#destinationSearch','no result');assert.equal(await page.locator('#destinationList label:visible').count(),0);
  await page.fill('#destinationSearch','');assert.deepEqual(await ids(),[1,2,10]);
  await page.locator('#destinationList input[data-route-id="10"]').check();
  await page.click('#saveDestinationsBtn');
  const saved=await page.locator('.destinations-data').first().inputValue();
  assert.deepEqual(JSON.parse(saved).map(d=>d.route_id),[2,10],'Same-name routes remain distinct');
  assert.ok(JSON.parse(saved).every(d=>!('route_code' in d)),'No submitted payload change');
  assert.equal(await page.locator('#selectedDestinationsTable th').count(),4,'Selected table unchanged');
  const selectedNames=()=>page.locator('#selectedDestinationsTable tbody tr').filter({has:page.locator('button')}).locator('td:nth-child(2)').allTextContents().then(names=>names.map(name=>name.trim()));
  assert.deepEqual(await selectedNames(),['PX2 - Bapa','PX10 - Bapa'],'New selections display their own route-ID codes despite identical names');
  const beforeDisplay=await page.locator('.destinations-data').first().inputValue();
  await page.evaluate(()=>{
   const list=document.getElementById('destinationList');
   list.prepend(list.querySelector('input[data-route-id="10"]').closest('label'));
   updateSelectedDestinationsTable();
  });
  assert.deepEqual(await selectedNames(),['PX2 - Bapa','PX10 - Bapa'],'Available-list reorder does not reorder saved selections');
  assert.equal(await page.locator('.destinations-data').first().inputValue(),beforeDisplay,'Display does not rewrite saved values');
  assert.equal(await page.locator('#gasSlipGrid tbody tr').first().evaluate(row=>row.dataset.estimatedKm),'30','Existing longest-distance computation preserved');
  await page.uncheck('#useRoutes');
  await page.waitForFunction(()=>document.querySelectorAll('#destinationList label').length===3);
  assert.deepEqual(await ids(),[2,10,20],'Saved coded selections survive unchecked filter and precede uncoded');
  assert.match(await page.locator('#destinationList label').last().textContent(),/^\s*Uncoded/);
  await page.locator('#destinationList input[data-route-id="20"]').check();
  assert.deepEqual(await selectedNames(),['PX2 - Bapa','PX10 - Bapa','Uncoded'],'Uncoded selections retain the original name');
  await page.locator('#destinationList input[data-route-id="20"]').uncheck();
  await page.locator('#selectedDestinationsTable button[data-route-id="2"]').click();
  assert.equal(await page.locator('#destinationList input[data-route-id="10"]').isChecked(),true,'Removing one duplicate name keeps the other route');
  await page.check('#useRoutes');
  await page.waitForFunction(()=>document.querySelectorAll('#destinationList label').length===3);
  assert.equal(await page.locator('#destinationList input[data-route-id="2"]').isChecked(),false);
  assert.equal(await page.locator('#destinationList input[data-route-id="10"]').isChecked(),true);
  // A second manually created row and then a template-style row replacement.
  await page.locator('.destination-cell button').nth(1).click();
  await page.waitForFunction(()=>document.querySelectorAll('#destinationList input:checked').length===0);
  await page.evaluate(saved=>{
   const body=document.querySelector('#gasSlipGrid tbody'),row=body.firstElementChild.cloneNode(true);
   row.querySelector('.destinations-data').value=saved;body.replaceChildren(row);window.TEMPLATE_ID=42;
  },saved);
  await page.locator('.destination-cell button').click();
  await page.waitForFunction(()=>document.querySelectorAll('#destinationList input:checked').length===2);
  assert.deepEqual(await ids(),[1,2,10]);
  assert.deepEqual(await selectedNames(),['PX2 - Bapa','PX10 - Bapa'],'Template selections recover codes from existing route-ID metadata');
  await page.evaluate(saved=>{
   activeDestinationRow.querySelector('.destinations-data').value=saved;
   window.TEMPLATE_ID=0;
   loadDestinationsForOrigin(USER_ORIGIN);
  },saved);
  await page.waitForFunction(()=>document.querySelectorAll('#destinationList input:checked').length===2);
  assert.deepEqual(await selectedNames(),['PX2 - Bapa','PX10 - Bapa'],'Previously saved gas-slip selections recover codes without a template');
  await page.evaluate(()=>{
   document.getElementById('destinationList').append(createDestinationLabel({route_id:99,route_code:'<img>',name:'<script>',km:null},false));
  });
  assert.equal(await page.locator('#destinationList img, #destinationList script').count(),0,'Code/name safely escaped');
  await page.setViewportSize({width:390,height:844});
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Compact labels fit mobile width');
  assert.deepEqual(errors,[]);
  console.log('Destination route-code browser checks passed: natural sorting, route/name search, missing codes, duplicate names, selections, filter toggles, IDs/payload, distance, manual/template rows and escaping.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
