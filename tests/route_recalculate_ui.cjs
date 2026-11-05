// Real modal/planner DOM with deterministic road responses; no application writes.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {execFileSync}=require('node:child_process');
const {chromium}=require(process.env.GSLIP_UI_PLAYWRIGHT||'playwright');
const root=path.resolve(__dirname,'..');
const html=execFileSync('php',['-r',"$areaResult=new class {function fetch_assoc(){return false;}};include 'includes/add_routes_modal.php';"],{cwd:root,encoding:'utf8'});
(async()=>{
 const browser=await chromium.launch({channel:'chrome',headless:true});
 try {
  const page=await browser.newPage({viewport:{width:1400,height:1000}}),errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  await page.setContent(html);
  await page.addStyleTag({content:fs.readFileSync(path.join(root,'assets/css/bootstrap.min.css'),'utf8')});
  await page.addScriptTag({content:fs.readFileSync(path.join(root,'assets/js/route-planner.js'),'utf8')});
  await page.evaluate(()=>{
   document.getElementById('addRoutesModal').style.display='block';
   document.getElementById('addRoutesModal').classList.add('show');
   const layer=()=>({addTo(){return this;},bindPopup(){return this;},openPopup(){return this;}});
   window.L={latLng:(lat,lng)=>({lat,lng}),marker:layer,geoJSON:layer,featureGroup:()=>({getBounds:()=>[]})};
   routeMap={hasLayer:()=>false,removeLayer(){},fitBounds(){},setView(){}};
   getRoadRoute=async(a,b)=>({distance:b.lat===9?40000:10000,geometry:{}});
   document.getElementById('twoWheelsRoute').checked=true;
  });
  for(const type of ['round_trip','one_way']) {
   await page.evaluate(type=>{
    clearRoutePlanner();document.getElementById('trip_type').value=type;
    setRouteOrigin(9,118);addRouteDestination(10,119);addRouteDestination(11,120);
   },type);
   await page.click('#finalizeRouteBtn');
   await page.waitForFunction(()=>document.getElementById('finalizeRouteBtn').textContent==='Edit Route / Recalculate');
   assert.equal(await page.locator('.remove-destination-btn:disabled').count(),2);
   const original=await page.locator('#distance_km').inputValue();
   assert.equal(original,type==='round_trip'?'60.00':'20.00');
   await page.click('#finalizeRouteBtn');
   assert.equal(await page.locator('#route_data').inputValue(),'');
   assert.equal(await page.locator('#route_map_dirty').inputValue(),'1');
   assert.equal(await page.locator('.remove-destination-btn:enabled').count(),2);
   assert.equal(await page.locator('#trip_type').inputValue(),type);
   if(type==='round_trip') await page.evaluate(()=>addRouteDestination(12,121));
   else await page.locator('.remove-destination-btn').last().click();
   await page.click('#finalizeRouteBtn');
   await page.waitForFunction(()=>document.getElementById('finalizeRouteBtn').textContent==='Edit Route / Recalculate');
   assert.equal(await page.locator('#distance_km').inputValue(),type==='round_trip'?'70.00':'10.00');
   await page.click('#finalizeRouteBtn');
   await page.locator('.remove-origin-btn').click();
   assert.equal(await page.locator('#finalizeRouteBtn').isDisabled(),true,'No points disables finalization');
  }
  assert.deepEqual(errors,[]);
  console.log('Route recalculation browser checks passed: actual action button, lock/unlock, add/remove points, both trip types and no-points disabled state.');
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
