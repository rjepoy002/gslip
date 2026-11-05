// Exercise the actual template-loader event path, including a cold code cache.
const assert=require('node:assert/strict'),path=require('node:path');
const {chromium}=require(process.env.GSLIP_UI_PLAYWRIGHT||'playwright');
const first={route_id:10,route_code:'PX0100',name:'Same name',km:30,fuel_allocation:2,is_fixed_fuel:0};
const second={route_id:20,route_code:'PX0101',name:'Same name',km:12.75,fuel_allocation:1,is_fixed_fuel:0};
const uncoded={route_id:30,route_code:null,name:'Uncoded name',km:5,fuel_allocation:3,is_fixed_fuel:1};
const templateRows=[
 {category:'2-wheels',purpose:'First',destinations:[first,second],fuel_requests:[{id:1,qty:'2.00',container:'no'}]},
 {category:'2-wheels',purpose:'Second',destinations:[uncoded,second],fuel_requests:[]}
];
(async()=>{
 const browser=await chromium.launch({channel:'chrome',headless:true});
 try{
  const page=await browser.newPage(),errors=[];let templateRequests=0,destinationRequests=0;
  page.on('pageerror',e=>errors.push(e.message));
  await page.route('http://template.test/**',async route=>{
   const url=new URL(route.request().url());
   if(url.pathname.endsWith('load_template.php')){templateRequests++;return route.fulfill({json:{success:true,rows:templateRows}});}
   if(url.pathname.endsWith('get_destinations.php')){destinationRequests++;return route.fulfill({status:503,json:{error:'Unavailable'}});}
   return route.fulfill({body:'<html><body></body></html>',contentType:'text/html'});
  });
  await page.goto('http://template.test/fixture');
  await page.setContent(`<table id="gasSlipGrid"><tbody><tr><td><input name="purpose[]"><input class="vehicle-category"><input class="destinations-data"><input class="fuel-data"><span class="dest-count">0</span><span class="fuel-count">0</span><span class="row-number">1</span></td><td class="destination-cell"><button>Destinations</button></td></tr></tbody></table><div id="destinationsModal"><input id="useRoutes" type="checkbox"><input id="destinationSearch"><div id="destinationList"></div><table id="selectedDestinationsTable"><tbody></tbody></table><button id="saveDestinationsBtn">Save</button></div>`);
  await page.evaluate(()=>{window.TEMPLATE_ID=7;window.APP={USER_ORIGIN:'',USER_ORIGIN_NAME:''};window.Swal={fire:async()=>({})};const modal={show(){},hide(){}};window.bootstrap={Modal:{getOrCreateInstance:()=>modal,getInstance:()=>modal}};});
  await page.addScriptTag({path:path.resolve(__dirname,'../assets/js/destinations.js')});
  await page.addScriptTag({path:path.resolve(__dirname,'../assets/js/template_loader.js')});
  await page.evaluate(()=>document.dispatchEvent(new Event('DOMContentLoaded')));
  await page.waitForFunction(()=>document.querySelectorAll('#gasSlipGrid tbody tr').length===2);
  assert.equal(templateRequests,1);assert.equal(destinationRequests,0,'No extra API request for template display metadata');
  assert.equal(await page.evaluate(()=>destinationRouteCodes.get(10)),'PX0100','Code cache populated before rendering');
  const hidden=await page.locator('.destinations-data').evaluateAll(fields=>fields.map(f=>JSON.parse(f.value)));
  assert.deepEqual(hidden[0].map(d=>d.route_id),[10,20]);assert.deepEqual(hidden[1].map(d=>d.route_id),[30,20],'Stored template order unchanged');
  assert.ok(hidden.flat().every(d=>!('route_code' in d)),'Route codes are not added to saved form data');
  assert.equal(await page.locator('.fuel-data').first().inputValue(),JSON.stringify(templateRows[0].fuel_requests),'Loaded fuel values unchanged');
  const names=()=>page.locator('#selectedDestinationsTable tbody tr').filter({has:page.locator('button')}).locator('td:nth-child(2)').allTextContents().then(values=>values.map(s=>s.trim()));
  await page.locator('.destination-cell button').first().click();
  assert.deepEqual(await names(),['PX0100 - Same name','PX0101 - Same name'],'Cold template cache resolves both IDs without depending on Available Destinations');
  await page.locator('#selectedDestinationsTable button[data-route-id="10"]').click();
  assert.deepEqual(await names(),['PX0101 - Same name'],'Removing one same-name route preserves the other');
  await page.locator('#destinationList input[data-route-id="10"]').check();
  assert.deepEqual(await names(),['PX0101 - Same name','PX0100 - Same name'],'Reselection appends after existing selection and keeps actual code');
  await page.locator('.destination-cell button').nth(1).click();
  assert.deepEqual(await names(),['Uncoded name','PX0101 - Same name'],'Every template row uses ID lookup, with graceful missing-code fallback');
  // A late/failing Available Destinations request cannot erase template codes.
  await page.evaluate(()=>loadDestinationsForOrigin('Test'));
  await page.waitForFunction(()=>!document.getElementById('destinationList').textContent.includes('Loading'));
  assert.deepEqual(await names(),['Uncoded name','PX0101 - Same name']);
  assert.equal(templateRequests,1);assert.equal(destinationRequests,1);
  assert.deepEqual(errors,[]);
  console.log('Actual template-loader browser checks passed: cold cache, old ID-based selections, multiple rows, correct IDs for duplicate names, no extra metadata requests, failure fallback and unchanged saved/fuel values.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
