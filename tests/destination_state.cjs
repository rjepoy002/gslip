const assert=require('node:assert/strict'),path=require('node:path'),fs=require('node:fs');
const {chromium}=require(process.env.GSLIP_UI_PLAYWRIGHT||'playwright');
const a={route_id:1,route_code:'PX0100',name:'Duplicate',km:30,fuel_allocation:2,is_fixed_fuel:0};
const b={route_id:2,route_code:'PX0101',name:'Duplicate',km:12.75,fuel_allocation:1,is_fixed_fuel:0};
(async()=>{
 const browser=await chromium.launch({channel:'chrome',headless:true});
 try{
  const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.setContent(`<table id="gasSlipGrid"><tbody>${[0,1].map(()=>'<tr><td><input class="vehicle-category" value="2-wheels"><input class="destinations-data" value="[]"><span class="dest-count"></span></td><td class="destination-cell"><button>Open</button></td></tr>').join('')}</tbody></table><div id="destinationsModal"><input id="useRoutes" type="checkbox"><input id="destinationSearch"><div id="destinationList"></div><table id="selectedDestinationsTable"><tbody></tbody></table><button id="saveDestinationsBtn">Save</button></div>`);
  await page.evaluate(()=>{
   window.APP={USER_ORIGIN:'Test'};const modal={show(){},hide(){}};
   window.bootstrap={Modal:{getOrCreateInstance:()=>modal,getInstance:()=>modal}};
   window.pending=[];
   // Deliberately ignore abort: version/row guards must still reject late data.
   window.fetch=(url,options)=>new Promise(resolve=>pending.push({url,options,resolve}));
  });
  await page.addScriptTag({path:path.resolve(__dirname,'../assets/js/destinations.js')});
  const complete=async(index,data)=>{
   await page.evaluate(({index,data})=>pending[index].resolve({ok:true,json:async()=>data}),{index,data});
   await page.evaluate(()=>new Promise(resolve=>setTimeout(resolve,0)));
  };
  const names=()=>page.locator('#selectedDestinationsTable tbody tr').filter({has:page.locator('button')}).locator('td:nth-child(2)').allTextContents().then(names=>names.map(s=>s.trim()));
  const raw=async index=>JSON.parse(await page.locator('.destinations-data').nth(index).inputValue());
  await page.evaluate(records=>document.querySelector('.destinations-data').value=JSON.stringify(records),[b,a]);
  await page.locator('.destination-cell button').first().click(); // request 0
  await page.uncheck('#useRoutes'); // request 1
  await complete(1,{destinations:[a,b]});
  assert.deepEqual(await names(),['PX0101 - Duplicate','PX0100 - Duplicate'],'Stored selection order is independent of available route sorting');
  await complete(0,{destinations:[{...a,route_code:null}]});
  assert.deepEqual(await names(),['PX0101 - Duplicate','PX0100 - Duplicate'],'Old filter response cannot clear the latest codes');
  assert.equal(await page.evaluate(()=>pending[0].options.signal.aborted),true);
  await page.check('#useRoutes'); // request 2
  const {route_code,...incomplete}=a;
  await complete(2,{destinations:[incomplete,b],route_codes:{1:'PX0100'}});
  assert.match(await page.locator('#destinationList label').first().textContent(),/PX0100 - Duplicate/,'Available formatter resolves metadata map, not just response object');
  assert.deepEqual(await names(),['PX0101 - Duplicate','PX0100 - Duplicate']);
  assert.deepEqual((await raw(0)).map(d=>d.route_id),[2,1]);
  await page.fill('#destinationSearch','px0100');assert.equal(await page.locator('#destinationList label:visible').count(),1);
  await page.fill('#destinationSearch','');
  await page.locator('#destinationList input[data-route-id="2"]').uncheck();
  await page.locator('#destinationList input[data-route-id="2"]').check();
  assert.deepEqual((await raw(0)).map(d=>d.route_id),[1,2],'New selections append after existing selections');
  await page.uncheck('#useRoutes'); // request 3
  await complete(3,{destinations:[],route_codes:{1:'PX0100',2:'PX0101'}});
  assert.equal(await page.locator('#destinationList input:checked').count(),2,'Toggle preserves live unsaved checks');
  assert.deepEqual((await raw(0)).map(d=>d.route_id),[1,2]);
  await page.check('#useRoutes'); // request 4 pending
  await page.locator('.destination-cell button').nth(1).click(); // request 5
  await complete(5,{destinations:[b]});
  await complete(4,{destinations:[a],route_codes:{2:null}});
  assert.equal(await page.locator('#destinationList input[data-route-id="1"]').count(),0,'Old row cannot overwrite new row');
  assert.deepEqual(await raw(1),[],'Old row response cannot select destinations in another row');
  await page.locator('#destinationList input[data-route-id="2"]').check();
  assert.deepEqual(await names(),['PX0101 - Duplicate']);
  await page.uncheck('#useRoutes'); // request 6
  await page.evaluate(()=>document.getElementById('destinationsModal').dispatchEvent(new Event('hidden.bs.modal')));
  await complete(6,{destinations:[]});
  assert.deepEqual((await raw(1)).map(d=>d.route_id),[2],'Closing during a pending load does not erase selections');
  await page.locator('.destination-cell button').nth(1).click(); // request 7
  await complete(7,{destinations:[b]});
  assert.deepEqual(await names(),['PX0101 - Duplicate'],'Reopening keeps code display');
  await page.uncheck('#useRoutes'); // request 8
  await complete(8,{destinations:[{route_id:99,name:'Unresolved',km:1}]});
  assert.match(await page.locator('#destinationList').textContent(),/Some route codes could not be loaded/,'Missing metadata is not silently treated as uncoded');
  await page.check('#useRoutes'); // request 9
  await complete(9,{destinations:[{route_id:100,route_code:null,name:'Genuinely uncoded',km:null}]});
  assert.doesNotMatch(await page.locator('#destinationList').textContent(),/Some route codes/);
  assert.match(await page.locator('#destinationList').textContent(),/Genuinely uncoded/);
  const php=fs.readFileSync(path.resolve(__dirname,'../create_gas_slip.php'),'utf8');
  assert.match(php,/destinations\.js\?v=<\?= filemtime/);
  assert.match(php,/template_loader\.js\?v=<\?= filemtime/);
  assert.deepEqual(errors,[]);
  console.log('Destination state tests passed: stale filter/row responses, abort, incomplete metadata, shared labels, selection order, live toggles, close/reopen and versioned assets.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
