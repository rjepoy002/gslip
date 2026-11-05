// Exercises the actual planner with deterministic road responses, no live map API.
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const source = fs.readFileSync(require('node:path').join(__dirname,'../assets/js/route-planner.js'),'utf8');
function planner(type, count=2) {
    const elements = new Map(), calls = [], drawn = [];
    function element(id) {
        if (!elements.has(id)) elements.set(id,{value:'',checked:false,textContent:'',disabled:false,innerHTML:'',style:{},listeners:{},addEventListener(name,fn){this.listeners[name]=fn;},classList:{add(){},remove(){},toggle(){}},querySelectorAll(){return [];}});
        return elements.get(id);
    }
    const layer = style => ({addTo(){drawn.push(style);return this;}});
    const ctx = vm.createContext({document:{getElementById:element,addEventListener(){}},console:{log(){},error(){}},alert(){},setTimeout,clearTimeout,L:{geoJSON:(g,o)=>layer(o.style),polyline:()=>layer({}),featureGroup:()=>({getBounds:()=>[]})}});
    vm.runInContext(source,ctx);
    ctx.road = async (a,b) => {calls.push([a.lat,b.lat]);return {distance:(b.lat===0?40000:b.lat===1?10000:20000),geometry:{}};};
    element('trip_type').value=type; element('twoWheelsRoute').checked=true;
    vm.runInContext(`routeMap={hasLayer:()=>false,removeLayer(){},fitBounds(){},invalidateSize(){},setView(){}};originLatLng={lat:0,lng:0};destinationPoints=[{lat:1,lng:1}${count===2?',{lat:2,lng:2}':''}];getRoadRoute=road;updateDestinationList=()=>{};`,ctx);
    return {ctx,element,calls,drawn};
}
(async()=>{
    for (const count of [1,2]) for (const type of ['round_trip','one_way']) {
        const p=planner(type,count); assert.equal(await vm.runInContext('finalizeRoute()',p.ctx),true);
        const data=JSON.parse(p.element('route_data').value);
        const outgoing=count===1?10:30, returning=type==='one_way'?0:40;
        assert.equal(data.trip_type,type); assert.equal(data.route_distance,outgoing);
        assert.equal(data.return_distance,returning); assert.equal(data.final_distance,outgoing+returning);
        assert.equal(p.calls.length,count+(type==='round_trip'?1:0));
        assert.equal(p.drawn.filter(style=>style.dashArray).length,type==='round_trip'?1:0,'Only Round Trip draws a return leg');
        assert.equal(p.element('distance_km').value,(outgoing+returning).toFixed(2));
        assert.equal(p.element('fuel_allocation').value,((outgoing+returning)/30+count*.6).toFixed(2),'Existing fuel formula and rounding');
        p.element('trip_type').value=type==='one_way'?'round_trip':'one_way';
        p.element('trip_type').listeners.change();
        assert.equal(p.element('route_data').value,'','Type change invalidates finalized distance');
        assert.equal(vm.runInContext('destinationPoints.length',p.ctx),count,'Pins survive type change');
    }
    const fixed=planner('one_way'); fixed.element('is_fixed_fuel').checked=true; fixed.element('fuel_allocation').value='7.50';
    assert.equal(await vm.runInContext('finalizeRoute()',fixed.ctx),true);
    assert.equal(fixed.element('distance_km').value,'0.1');assert.equal(fixed.element('fuel_allocation').value,'7.50');assert.equal(fixed.calls.length,0);
    fixed.element('trip_type').listeners.change();assert.equal(fixed.element('fuel_allocation').value,'7.50');
    const failed=planner('one_way');vm.runInContext('getRoadRoute=async()=>null;',failed.ctx);
    assert.equal(await vm.runInContext('finalizeRoute()',failed.ctx),false,'Failed outgoing leg cannot produce a partial One-Way route');
    assert.equal(failed.element('route_data').value,'');
    for (const type of ['one_way','round_trip',undefined]) {
        const p=planner('round_trip'); p.ctx.saved={distance_km:'23.45',fuel_allocation:'4.56',is_fixed_fuel:0,...(type?{trip_type:type}:{})};
        p.ctx.pins=[{latitude:0,longitude:0,location_type:'origin',sequence:1},{latitude:1,longitude:1,location_type:'destination',sequence:2},{latitude:2,longitude:2,location_type:'destination',sequence:3}];
        vm.runInContext('setRouteOrigin=(lat,lng)=>{originLatLng={lat,lng};};addRouteDestination=(lat,lng)=>destinationPoints.push({lat,lng});',p.ctx);
        await vm.runInContext('restoreSavedRoute(saved,pins)',p.ctx);
        assert.equal(p.element('trip_type').value,type||'round_trip');
        assert.equal(p.element('distance_km').value,'23.45');assert.equal(p.element('fuel_allocation').value,'4.56');
        assert.equal(p.calls.length,type==='one_way'?2:3,'Editing redraw respects trip type');
        assert.equal(p.element('finalizeRouteBtn').textContent,'Edit Route / Recalculate');
        assert.equal(p.element('route_map_dirty').value,'0','Opening saved map is not a database change');
    }
    for (const [type,edit] of [['round_trip','add'],['one_way','remove'],['round_trip','unchanged']]) {
        const p=planner(type);await vm.runInContext('finalizeRoute()',p.ctx);
        const oldData=p.element('route_data').value;
        assert.equal(p.element('finalizeRouteBtn').disabled,false,'Finalized action remains clickable');
        assert.equal(p.element('finalizeRouteBtn').textContent,'Edit Route / Recalculate');
        const points=vm.runInContext('JSON.stringify([originLatLng,destinationPoints])',p.ctx);
        await p.element('finalizeRouteBtn').listeners.click();
        assert.equal(vm.runInContext('JSON.stringify([originLatLng,destinationPoints])',p.ctx),points,'Unlock preserves all ordered points');
        assert.equal(p.element('trip_type').value,type);
        assert.equal(p.element('route_data').value,'');assert.equal(p.element('distance_km').value,'');assert.equal(p.element('fuel_allocation').value,'');
        assert.equal(p.element('route_map_dirty').value,'1');assert.equal(p.element('finalizeRouteBtn').textContent,'Finalize Route');
        if(edit==='add')vm.runInContext('destinationPoints.push({lat:3,lng:3});generateRoadRoutes();',p.ctx);
        if(edit==='remove')vm.runInContext('destinationPoints.splice(1,1);generateRoadRoutes();',p.ctx);
        await p.element('finalizeRouteBtn').listeners.click();
        const recalculated=JSON.parse(p.element('route_data').value);
        assert.equal(recalculated.destinations.length,edit==='add'?3:edit==='remove'?1:2);
        assert.equal(recalculated.final_distance,edit==='add'?90:edit==='remove'?10:70);
        assert.equal(p.element('fuel_allocation').value,(recalculated.final_distance/30+recalculated.destinations.length*.6).toFixed(2));
        assert.equal(p.element('finalizeRouteBtn').textContent,'Edit Route / Recalculate');
        if(edit==='unchanged')assert.equal(p.element('route_data').value,oldData);
    }
    const asyncPlanner=planner('one_way');
    vm.runInContext('getRoadRoute=()=>new Promise(resolve=>{globalThis.finishOldRequest=resolve;});',asyncPlanner.ctx);
    const first=vm.runInContext('finalizeRoute()',asyncPlanner.ctx);
    assert.equal(asyncPlanner.element('finalizeRouteBtn').disabled,true);
    assert.equal(await vm.runInContext('finalizeRoute()',asyncPlanner.ctx),false,'Repeated calculation is rejected while busy');
    vm.runInContext('unlockRouteForEditing();getRoadRoute=road;',asyncPlanner.ctx);
    await vm.runInContext('finalizeRoute()',asyncPlanner.ctx);
    const latest=asyncPlanner.element('route_data').value;
    asyncPlanner.ctx.finishOldRequest({distance:999000,geometry:{}});assert.equal(await first,false);
    assert.equal(asyncPlanner.element('route_data').value,latest,'Stale routing response cannot overwrite newer finalization');
    for(const type of ['round_trip','one_way']) {
        const p=planner(type);vm.runInContext('getRoadRoute=async()=>null;',p.ctx);
        assert.equal(await vm.runInContext('finalizeRoute()',p.ctx),false);
        assert.equal(p.element('finalizeRouteBtn').disabled,false,'Failure restores retry');
        vm.runInContext('getRoadRoute=road;',p.ctx);
        assert.equal(await vm.runInContext('finalizeRoute()',p.ctx),true,'Failed route can be recalculated');
    }
    console.log('Route trip-type planner checks passed: single/multiple destinations, outgoing-only/round-trip calls and drawing, unchanged fuel formula, re-finalization, saved edit restore and Fixed Fuel.');
})().catch(e=>{console.error(e);process.exitCode=1;});
