/* Set GSLIP_UI_PLAYWRIGHT to an external Playwright install. No application writes. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {chromium} = require(process.env.GSLIP_UI_PLAYWRIGHT || 'playwright');
const root = path.resolve(__dirname, '..');
const session = 'monitor-ui-' + Date.now();
const html = execFileSync('php', ['-r', `ini_set('session.use_cookies','0'); session_save_path(sys_get_temp_dir()); session_id('${session}'); session_start(); $_SESSION=['user_id'=>1,'session_token'=>'test','role'=>'admin','department_id'=>1,'area'=>1]; session_write_close(); $_SERVER['PHP_SELF']='monitoring.php'; include 'monitoring.php';`], {cwd:root,encoding:'utf8'});
// Render the actual shared PHP body, using read-only fixture data and a real result set.
function sharedBody(status) {
    return execFileSync('php',['-r', `require 'includes/config.php'; require 'includes/display_format.php'; $conn=getDBConnection();
        $mode='body'; $readOnly=true; $isExpired=false; $isRecommender=false; $isApprover=false; $isPrivateApprover=false; $canRecommendSlip=false; $canApproveSlip=false;
        $_SESSION=['user_id'=>1];
        $data=['id'=>1,'user_id'=>2,'status'=>'${status}','ownership'=>'coop-owned','date_issued'=>'2026-11-05 09:00:00','validity_until'=>'2027-01-01 09:00:00','requested_by'=>'J. pe'.mb_chr(0x00f1).'a','plate_no'=>'ABC','brand'=>'Test','model'=>'Vehicle','purpose'=>'LINE CLEARING AT PALECO','route_path'=>'NARRA '.mb_chr(0x2192).' [LABOG, ESPA'.mb_chr(0x00d1).'OLA] - ICT/GPS','recommended_by'=>'Pe'.mb_chr(0x00f1).'a','recommended_at'=>'2026-11-05 10:00:00','approved_by'=>'Ni'.mb_chr(0x00f1).'o','approved_at'=>'2026-11-05 11:00:00','approver_type'=>'primary','printed_at'=>'2026-11-05 12:00:00','rejected_by'=>'Pe'.mb_chr(0x00f1).'a','rejected_at'=>'2026-11-05 13:00:00','rejection_reason'=>null];
        $fuelResult=$conn->query("SELECT 'Diesel' fuel_name,12 quantity,'no' container");
        $source=file_get_contents('view_gas_slip_modal.php');eval(substr($source,strpos($source,'/* BODY MODE')));`],{cwd:root,encoding:'utf8'});
}
const bodies=Object.fromEntries(['pending','recommended','approved','printed','rejected'].map(status=>[status,sharedBody(status)]));
const rows = Array.from({length:31}, (_,i) => ({id:i+1,gas_slip_id:`MONITOR-${String(i+1).padStart(3,'0')}`,date_display:'11/05/2026 11:59 PM',department:'ASOD',area:'Narra',requested_by:'Requester',purpose:'<img src=x onerror=alert(1)> Inspection',routes:'Origin → Destination; Destination → Second',status:i%2?'printed':'approved'}));
const display=JSON.parse(execFileSync('php',['-r', `require 'includes/display_format.php';echo json_encode(['requested_by'=>formatRequestedBy('Requester'),'purpose'=>formatPurpose('<img src=x onerror=alert(1)> Inspection'),'routes'=>formatRoute('ORIGIN ? DESTINATION; DESTINATION ? SECOND')]);`],{cwd:root,encoding:'utf8'}));
rows.forEach(row=>row.display=display);
const filterOptions = {departments:[{id:1,name:'ASOD'},{id:2,name:'ANOD'}],areas:[{id:4,name:'Narra',department_id:1},{id:5,name:'Roxas',department_id:2}],years:[{year:2026},{year:2025}]};
let modalLoads = 0;
let requests = [], fail = false, exportFail = false;
(async () => {
    const browser = await chromium.launch({channel:'chrome',headless:true});
    try {
        const page = await browser.newPage({viewport:{width:1440,height:1100}});
        const errors = []; page.on('pageerror', e => errors.push(e.message));
        await page.clock.install();
        await page.route('http://monitor.test/**', async route => {
            const url = new URL(route.request().url());
            const action = url.searchParams.get('action');
            if (url.pathname.endsWith('/view_gas_slip_modal.php')) {
                assert.equal(url.searchParams.get('context'),'monitoring');
                const status=url.searchParams.get('id')==='999'?'pending':url.searchParams.get('id')==='998'?'recommended':url.searchParams.get('id')==='997'?'rejected':rows[(Number(url.searchParams.get('id'))-1)%rows.length].status;
                if(url.searchParams.get('mode')==='json') { modalLoads++; return route.fulfill({json:{gas_slip_id:'MONITOR-'+url.searchParams.get('id'),statusLabel:status[0].toUpperCase()+status.slice(1),statusClass:status==='printed'?'bg-primary':'bg-success'}}); }
                return route.fulfill({body:bodies[status],contentType:'text/html'});
            }
            if (!action) {
                const asset = url.pathname.replace(/^\//,'');
                if (asset.startsWith('images/') && fs.existsSync(path.join(root,asset))) return route.fulfill({body:fs.readFileSync(path.join(root,asset)),contentType:'image/png'});
                if (asset.startsWith('assets/')) return route.fulfill({body:fs.readFileSync(path.join(root,asset)),contentType:asset.endsWith('.js')?'text/javascript':'text/css'});
                return route.fulfill({body:html,contentType:'text/html'});
            }
            requests.push(Object.fromEntries(url.searchParams));
            if (action === 'export') {
                if (exportFail) return route.fulfill({status:400,json:{error:'Export is temporarily unavailable.'}});
                return route.fulfill({body:'\uFEFFGas Slip No.,Date Issued,Department,Area,Requested By,Purpose,Routes,Status\nMONITOR-002,2026-11-05,ASOD,Narra,Requester,Inspection,Origin -> Destination,Printed\n',contentType:'text/csv; charset=utf-8',headers:{'Content-Disposition':'attachment; filename="gas_slips_20261105_120000.csv"'}});
            }
            let data;
            if (action === 'options') data = filterOptions;
            if (action === 'detail') data = {...rows[Number(url.searchParams.get('id'))-1],vehicle:'ABC Test vehicle',fuel:[{name:'Diesel',quantity:'12',container:'no'}]};
            if (action === 'data') {
                if (fail) return route.fulfill({status:500,json:{error:'Temporary connection failure.'}});
                const s = url.searchParams; let matches = rows.filter(r => (!s.get('status') || r.status === s.get('status')) && (!s.get('search') || r.gas_slip_id.includes(s.get('search'))));
                if (s.get('year') === '2025') matches = [];
                if (s.get('sort') === 'number' && s.get('direction') === 'desc') matches = [...matches].reverse();
                const size = Number(s.get('size')), pages = Math.max(1,Math.ceil(matches.length/size)), current = Math.min(Number(s.get('page')),pages);
                data = {counts:{total:matches.length,approved:matches.filter(r=>r.status==='approved').length,printed:matches.filter(r=>r.status==='printed').length},rows:matches.slice((current-1)*size,current*size),graph:[{department_id:1,area_id:4,label:'S-Narra',total:matches.length},{department_id:2,area_id:5,label:'N-Roxas',total:0}],page:current,pages,size,updated:'11/05/2026 11:59:59 PM'};
            }
            if (action === 'data') data.options = filterOptions;
            await route.fulfill({json:data});
        });
        const applyFilters = async changes => {
            await page.click('#open-filters');
            for (const [name,value] of Object.entries(changes)) {
                const field = page.locator(`[name="${name}"]`);
                if (name === 'from' || name === 'to') await field.fill(value);
                else await field.selectOption(value);
            }
            await page.getByRole('button',{name:'Apply Filters',exact:true}).click();
            await page.waitForFunction(()=>!document.getElementById('filter-dialog').open);
        };
        await page.goto('http://monitor.test/monitoring.php');
        await page.waitForFunction(() => document.getElementById('total').textContent === '31');
        assert.equal(await page.locator('.app-sidebar').count(),1,'One shared sidebar');
        assert.equal(await page.locator('.page-header').count(),1,'One native page heading');
        assert.equal(await page.locator('.app-content > .main-content > .panel-container').count(),1,'Native content wrappers');
        assert.equal(await page.locator('.sidebar-nav a[href="monitoring.php"].active').count(),1,'Monitoring navigation highlighted');
        assert.ok(await page.locator('.app-sidebar').isVisible());
        const originalChartWidth = await page.evaluate(()=>Object.values(Chart.instances)[0].width);
        await page.click('#sidebarToggle'); await new Promise(resolve=>setTimeout(resolve,350)); await page.clock.runFor(500);
        assert.ok(await page.locator('.app-sidebar').evaluate(el=>el.classList.contains('collapsed')));
        assert.ok(await page.evaluate(()=>Object.values(Chart.instances)[0].width)>originalChartWidth,'Chart grows with collapsed desktop sidebar');
        await page.click('#sidebarToggle'); await new Promise(resolve=>setTimeout(resolve,350)); await page.clock.runFor(500);
        assert.ok(!await page.locator('.app-sidebar').evaluate(el=>el.classList.contains('collapsed')));
        assert.equal(await page.evaluate(()=>Object.keys(Chart.instances).length),1,'Sidebar changes retain the chart instance');
        assert.equal(await page.locator('#year').isVisible(),false,'Individual filters are absent from the compact toolbar');
        assert.ok(await page.locator('[name="search"]').isVisible());
        await page.click('#open-filters');
        assert.equal(await page.locator('#year').inputValue(),'2026');
        const draftBefore = requests.filter(r=>r.action==='data').length;
        await page.selectOption('#year','2025');
        await page.selectOption('[name="status"]','printed');
        await page.selectOption('#department','2');
        assert.deepEqual(await page.locator('#area option').allTextContents(),['All Areas','Roxas'],'Draft department-area dependency');
        await page.selectOption('#area','5');
        assert.equal(requests.filter(r=>r.action==='data').length,draftBefore,'Draft edits do not apply');
        for (let i=0;i<12;i++) {
            await page.keyboard.press('Tab');
            assert.ok(await page.evaluate(()=>document.getElementById('filter-dialog').contains(document.activeElement)),'Native dialog traps keyboard focus');
        }
        await page.clock.runFor(61000); await page.waitForTimeout(10);
        const draftRefresh = requests.filter(r=>r.action==='data').at(-1);
        assert.equal(draftRefresh.year,'2026'); assert.equal(draftRefresh.status,''); assert.equal(draftRefresh.department,'0');
        assert.equal(await page.locator('#year').inputValue(),'2025','Auto-refresh does not overwrite drafts');
        await page.click('#cancel-filters');
        await page.waitForFunction(()=>!document.getElementById('filter-dialog').open);
        assert.equal(await page.locator('#year').inputValue(),'2026');
        assert.equal(await page.locator('[name="status"]').inputValue(),'');
        assert.ok(await page.locator('#open-filters').evaluate(el=>el===document.activeElement),'Cancel restores focus');
        await page.click('#open-filters'); await page.selectOption('#year','2025'); await page.keyboard.press('Escape');
        await page.waitForFunction(()=>!document.getElementById('filter-dialog').open && document.getElementById('year').value==='2026');
        assert.equal(await page.locator('#year').inputValue(),'2026','Escape discards drafts');
        await page.click('#open-filters'); await page.selectOption('[name="status"]','printed'); await page.click('#close-filters');
        await page.waitForFunction(()=>!document.getElementById('filter-dialog').open && document.querySelector('[name="status"]').value==='');
        assert.equal(await page.locator('[name="status"]').inputValue(),'','X discards drafts');
        await page.click('#open-filters');
        await page.fill('[name="from"]','2026-11-06'); await page.fill('[name="to"]','2026-11-05');
        await page.getByRole('button',{name:'Apply Filters',exact:true}).click();
        assert.match(await page.locator('#filter-error').textContent(),/must not be later/);
        assert.ok(await page.locator('#filter-dialog').evaluate(el=>el.open),'Invalid dates keep modal open');
        await page.click('#cancel-filters');
        assert.equal(await page.locator('#overview-toggle').getAttribute('aria-expanded'),'true','Overview starts expanded');
        assert.equal(await page.locator('#overview-toggle').getAttribute('aria-controls'),'overview-body');
        const expandedFilterTop = await page.locator('#filters').evaluate(el=>el.getBoundingClientRect().top);
        await page.locator('#overview-toggle').focus(); await page.keyboard.press('Enter');
        assert.equal(await page.locator('#overview-toggle').getAttribute('aria-expanded'),'false');
        assert.equal(await page.locator('#overview-body').isVisible(),false);
        assert.equal(await page.locator('.cards').isVisible(),false);
        assert.equal(await page.locator('#distribution').isVisible(),false);
        assert.ok(await page.locator('#overview-title').isVisible());
        assert.ok(await page.locator('#filters').evaluate(el=>el.getBoundingClientRect().top) < expandedFilterTop-400,'Records move up without blank graph space');
        assert.ok(await page.locator('#filters').isVisible());
        const collapsedBefore = requests.filter(r=>r.action==='data').length;
        await page.clock.runFor(61000); await page.waitForTimeout(10);
        assert.equal(requests.filter(r=>r.action==='data').length,collapsedBefore+1,'One refresh continues while collapsed');
        assert.equal(await page.locator('#overview-body').isVisible(),false,'Refresh preserves collapsed state');
        assert.equal(await page.locator('#records tr').count(),25);
        assert.equal(await page.locator('#records img').count(),0,'User text escaped');
        assert.equal(await page.locator('#records tr').first().locator('td').nth(4).textContent(),'REQUESTER');
        assert.equal(await page.locator('#records tr').first().locator('td').nth(5).textContent(),display.purpose);
        assert.equal(await page.locator('#records tr').first().locator('td').nth(6).textContent(),display.routes);

        await page.click('#next'); await page.waitForFunction(()=>document.getElementById('page-label').textContent==='Page 2 of 2');
        assert.equal(await page.locator('#records tr').count(),6);
        await page.selectOption('#size','10'); await page.waitForFunction(()=>document.getElementById('page-label').textContent==='Page 1 of 4');
        await page.click('[data-sort="number"]'); await page.waitForFunction(()=>document.querySelector('th[data-column="number"]').getAttribute('aria-sort')==='ascending');
        await page.click('[data-sort="number"]'); await page.waitForFunction(()=>document.querySelector('#records button').textContent==='MONITOR-031');
        await applyFilters({status:'printed'}); await page.waitForFunction(()=>document.getElementById('total').textContent==='15');
        assert.equal(await page.locator('#filter-count').textContent(),' 1 active');
        await page.fill('[name="search"]','MONITOR-002'); await page.clock.runFor(400); await new Promise(resolve=>setTimeout(resolve,350)); await page.waitForFunction(()=>document.getElementById('total').textContent==='1');
        await applyFilters({department:'1',area:'4'});
        await page.waitForFunction(()=>document.getElementById('total').textContent==='1');
        assert.equal(await page.locator('[name="search"]').inputValue(),'MONITOR-002','Applying modal filters preserves search');
        const searchedParams = requests.filter(r=>r.action==='data').at(-1);
        assert.equal(searchedParams.search,'MONITOR-002'); assert.equal(searchedParams.department,'1'); assert.equal(searchedParams.area,'4');
        await page.click('#reset'); await page.waitForFunction(()=>document.getElementById('total').textContent==='31');
        assert.equal(await page.locator('#size').inputValue(),'25');
        assert.equal(await page.locator('[name="search"]').inputValue(),'');
        assert.equal(await page.locator('#filter-count').isVisible(),false,'Reset clears active filter indicator');
        await applyFilters({year:'2025'}); await page.waitForFunction(()=>document.getElementById('total').textContent==='0');
        assert.match(await page.locator('#records').textContent(),/No Approved or Printed/);
        await page.click('#reset'); await page.waitForFunction(()=>document.getElementById('total').textContent==='31');
        await page.locator('#overview-toggle').focus(); await page.keyboard.press('Space');
        for (let i=0;i<3;i++) { await page.click('#overview-toggle'); await page.click('#overview-toggle'); }
        await page.clock.runFor(1000);
        assert.equal(await page.locator('#overview-toggle').getAttribute('aria-expanded'),'true');
        assert.ok(await page.locator('.cards').isVisible());
        assert.ok(await page.locator('#distribution').isVisible());
        assert.ok(await page.evaluate(()=>{const chart=Object.values(Chart.instances)[0];return Object.keys(Chart.instances).length===1 && chart.width>0 && chart.height>0 && chart.data.datasets[0].data[0]===31;}),'Repeated expansion retains the single chart with latest values');
        const barPoint = await page.evaluate(() => { const chart = Object.values(Chart.instances)[0]; const p = chart.getDatasetMeta(0).data[0]._model; const rect = chart.canvas.getBoundingClientRect(); return {x:rect.left+p.x,y:rect.top+(p.y+p.base)/2}; });
        await page.mouse.click(barPoint.x,barPoint.y); await page.waitForFunction(()=>document.getElementById('department').value==='1');
        assert.equal(await page.locator('#area').inputValue(),'4');
        assert.equal(await page.locator('#filter-count').textContent(),' 2 active','Chart selection updates applied filters');
        await page.clock.runFor(500); await page.mouse.click(barPoint.x,barPoint.y); await page.waitForFunction(()=>document.getElementById('department').value==='0');
        const checkRowOpens = async trigger => {
            const before=modalLoads;
            await trigger();
            await page.waitForSelector('#gasSlipModalBody .gas-slip-timeline');
            assert.equal(modalLoads,before+1,'Each row interaction opens exactly once');
            await page.locator('#gasSlipModal .btn-close').click();
            await page.waitForFunction(()=>!document.getElementById('gasSlipModal').classList.contains('show'));
            await page.waitForFunction(()=>document.activeElement?.closest('#records') && document.getElementById('records').getAttribute('aria-busy')!=='true');
            await new Promise(resolve=>setTimeout(resolve,350));
        };
        await page.locator('#records tr').first().hover();
        assert.equal(await page.locator('#records tr td').first().evaluate(e=>getComputedStyle(e).backgroundColor),'rgb(240, 248, 245)','Subtle row hover highlight');
        for(const column of [1,2,3,4,5,6,7]) {
            await checkRowOpens(()=>page.locator('#records tr').first().locator('td').nth(column).click());
        }
        await checkRowOpens(()=>page.locator('#records .slip-link').first().click());
        for(const key of ['Enter','Space']) {
            await page.keyboard.press('Tab');
            await page.locator('#records tr').first().focus();
            const css=await page.locator('#records tr').first().evaluate(e=>({cursor:getComputedStyle(e).cursor,label:e.getAttribute('aria-label'),outline:getComputedStyle(e).outlineStyle}));
            assert.equal(css.cursor,'pointer'); assert.match(css.label,/View gas slip/); assert.equal(css.outline,'solid');
            await checkRowOpens(()=>page.keyboard.press(key));
        }
        // Nested controls remain independent of row activation.
        await page.locator('#records tr').first().evaluate(row=>{
            const td=row.lastElementChild;
            td.insertAdjacentHTML('beforeend','<button id="row-control">Control</button><a id="row-link" href="#">Link</a><label id="row-label"><input id="row-check" type="checkbox">Check</label>');
            row.querySelector('#row-link').addEventListener('click',e=>e.preventDefault());
        });
        const beforeControls=modalLoads;
        for(const control of ['#row-control','#row-link','#row-check','#row-label']) await page.click(control);
        assert.equal(modalLoads,beforeControls,'Other row controls never open details');
        await page.locator('#row-control').focus(); await page.keyboard.press('Enter');
        assert.equal(modalLoads,beforeControls);
        // Closing restores both focus and the table position after its refresh.
        await page.locator('#records tr').first().evaluate(row=>row.lastElementChild.querySelectorAll('button,a,label').forEach(e=>e.remove()));
        const position=await page.evaluate(()=>({y:scrollY,left:document.querySelector('.table-scroll').scrollLeft}));
        await checkRowOpens(()=>page.locator('#records tr').first().locator('td').nth(2).click());
        assert.equal(await page.evaluate(()=>scrollY),position.y);
        const beforeNumber=modalLoads;
        await page.locator('#records button').first().click(); await page.waitForSelector('#gasSlipModalBody .gas-slip-timeline');
        assert.equal(modalLoads,beforeNumber+1,'Number click must not bubble into a second modal load');
        assert.match(await page.locator('#gasSlipModalBody table').textContent(),/Diesel[\s\S]*12\.00/);
        assert.equal(await page.locator('#gasSlipModal button').count(),2,'Only shared close controls');
        assert.equal(await page.locator('#gasSlipModal #recommendBtn,#gasSlipModal #approveBtn,#gasSlipModal #btnWithdraw,#gasSlipModal .rejectBtn').count(),0);
        const before = requests.filter(r=>r.action==='data').length;
        await page.clock.runFor(61000);
        assert.equal(requests.filter(r=>r.action==='data').length,before,'Refresh pauses in modal');
        await page.locator('#gasSlipModal .btn-close').click(); await page.waitForFunction(()=>!document.getElementById('gasSlipModal').classList.contains('show'));
        await page.waitForTimeout(10);
        await page.clock.runFor(61000); await page.waitForTimeout(10);
        assert.ok(requests.filter(r=>r.action==='data').length>before,'Refresh resumes');
        await page.selectOption('#size','10');
        await applyFilters({status:'printed'});
        await page.waitForFunction(()=>document.getElementById('total').textContent==='15');
        await page.click('#next'); await page.waitForFunction(()=>document.getElementById('page-label').textContent==='Page 2 of 2');
        await checkRowOpens(()=>page.locator('#records tr').first().locator('td').nth(3).click());
        await page.click('#overview-toggle');
        assert.equal(await page.locator('#overview-body').isVisible(),false);
        const downloadEvent = page.waitForEvent('download');
        await page.click('#export'); const download = await downloadEvent;
        assert.equal(download.suggestedFilename(),'gas_slips_20261105_120000.csv');
        assert.match(fs.readFileSync(await download.path(),'utf8'),/Gas Slip No.,Date Issued,Department,Area/);
        const exportRequest = requests.filter(r=>r.action==='export').at(-1);
        assert.equal(exportRequest.status,'printed'); assert.equal(exportRequest.year,'2026');
        assert.equal(exportRequest.sort,'date'); assert.equal(exportRequest.direction,'desc');
        assert.equal(exportRequest.page,undefined,'Export omits pagination');
        assert.equal(await page.locator('#page-label').textContent(),'Page 2 of 2','Download preserves pagination');
        assert.ok(await page.locator('#export').isEnabled());
        exportFail = true; await page.click('#export');
        await page.waitForFunction(()=>document.getElementById('message').textContent.includes('Export is temporarily'));
        assert.ok(await page.locator('#export').isEnabled(),'Failed export can retry'); exportFail = false;
        const filteredBefore = requests.filter(r=>r.action==='data').length;
        await page.clock.runFor(61000); await page.waitForTimeout(10);
        const refreshed = requests.filter(r=>r.action==='data');
        assert.ok(refreshed.length>filteredBefore);
        assert.equal(refreshed.at(-1).page,'2'); assert.equal(refreshed.at(-1).status,'printed'); assert.equal(refreshed.at(-1).size,'10');
        await page.evaluate(()=>{Object.defineProperty(document,'hidden',{configurable:true,value:true});document.dispatchEvent(new Event('visibilitychange'));});
        const hiddenBefore = requests.filter(r=>r.action==='data').length;
        await page.clock.runFor(121000);
        assert.equal(requests.filter(r=>r.action==='data').length,hiddenBefore,'Hidden page stops polling');
        await page.evaluate(()=>{Object.defineProperty(document,'hidden',{configurable:true,value:false});document.dispatchEvent(new Event('visibilitychange'));});
        await page.waitForTimeout(10);
        assert.ok(requests.filter(r=>r.action==='data').length>hiddenBefore);
        await page.click('#reset'); await page.waitForFunction(()=>document.getElementById('total').textContent==='31');
        const lastSuccessful = await page.locator('#updated').textContent();
        fail = true; await page.clock.runFor(61000); await page.waitForFunction(()=>document.getElementById('message').textContent.includes('Temporary'));
        assert.equal(await page.locator('#total').textContent(),'31','Failure retains data');
        assert.match(await page.locator('#refresh-status').textContent(),/Connection issue/);
        assert.equal(await page.locator('#updated').textContent(),lastSuccessful,'Failed refresh retains actual successful timestamp');
        fail = false; await page.clock.runFor(61000); await page.waitForFunction(()=>document.getElementById('message').textContent==='');
        assert.equal(await page.getByRole('button',{name:'Refresh',exact:true}).count(),0,'No manual Refresh button remains');
        assert.equal(await page.locator('#refresh-status').textContent(),'Auto-refresh active');
        await page.setViewportSize({width:390,height:844}); await page.clock.runFor(400); await new Promise(resolve=>setTimeout(resolve,350));
        assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth <= innerWidth),'Mobile page has no horizontal overflow');
        assert.ok(await page.locator('#overview-toggle').isVisible());
        await page.click('.mobile-menu-toggle'); await new Promise(resolve=>setTimeout(resolve,300));
        assert.ok(await page.locator('.app-sidebar').evaluate(el=>el.classList.contains('mobile-open')),'Existing mobile navigation opens');
        assert.equal(await page.locator('.mobile-menu-toggle').getAttribute('aria-expanded'),'true');
        await page.keyboard.press('Escape'); await new Promise(resolve=>setTimeout(resolve,300));
        assert.ok(!await page.locator('.app-sidebar').evaluate(el=>el.classList.contains('mobile-open')),'Existing mobile navigation closes');
        await page.screenshot({path:path.join(require('node:os').tmpdir(),'gslip-monitoring-collapsed-mobile.png'),fullPage:true});
        await page.click('#open-filters');
        assert.ok(await page.locator('#filter-dialog').isVisible());
        assert.ok(await page.locator('#filter-dialog').evaluate(el=>el.scrollWidth<=el.clientWidth),'Mobile dialog contents fit width');
        await page.screenshot({path:path.join(require('node:os').tmpdir(),'gslip-monitoring-filters-mobile.png'),fullPage:true});
        await page.keyboard.press('Escape');
        await page.click('#overview-toggle'); await page.clock.runFor(1000);
        assert.ok(await page.locator('#distribution').isVisible());
        assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth <= innerWidth),'Expanded mobile overview has no horizontal overflow');
        await page.screenshot({path:path.join(require('node:os').tmpdir(),'gslip-monitoring-mobile.png'),fullPage:true});
        for (const [id,label] of [[999,'Pending'],[998,'Recommended'],[1,'Approved'],[2,'Printed'],[997,'Rejected']]) {
            await page.evaluate(id=>openGasSlipDetails(id),id);
            await page.waitForSelector('#gasSlipModalBody .gas-slip-timeline');
            await page.waitForFunction(label=>document.getElementById('gasSlipModalTitle').textContent.includes(label),label);
            const dialog=await page.locator('#gasSlipModal .modal-dialog').boundingBox();
            assert.ok(dialog.x>=0 && dialog.x+dialog.width<=390,'Shared mobile dialog fits viewport');
            assert.equal(await page.locator('#gasSlipModalBody .styled-table').count(),1);
            const values=await page.locator('#gasSlipModalBody .info-value').allTextContents();
            assert.equal(values[2].trim(),'J. PE\u00d1A');
            assert.equal(values[4].trim(),'Line Clearing at PALECO');
            assert.equal(values[5].trim(),'Narra \u2192 [Labog, Espa\u00f1ola] - ICT/GPS');
            const people=(await page.locator('#gasSlipModalBody .timeline-user').allTextContents()).map(value=>value.trim());
            assert.equal(people[0],'J. PE\u00d1A');
            assert.ok(people.includes('PE\u00d1A'));
            if(label==='Approved'||label==='Printed') assert.ok(people.includes('NI\u00d1O'));
            assert.ok(people.every(value=>value===value.toLocaleUpperCase('en')));

            assert.equal(await page.locator('#gasSlipModalBody #recommendBtn,#gasSlipModalBody #approveBtn,#gasSlipModalBody #btnWithdraw').count(),0);
            if(label==='Printed') {
                assert.match(await page.locator('#gasSlipModalBody .gas-slip-timeline').textContent(),/Printed[\s\S]*12:00 PM/);
                await new Promise(resolve=>setTimeout(resolve,350));
                await page.screenshot({path:path.join(require('node:os').tmpdir(),'gslip-monitoring-shared-modal-mobile.png'),fullPage:false});
            }
            await page.locator('#gasSlipModal .btn-close').click();
            await page.waitForFunction(()=>!document.getElementById('gasSlipModal').classList.contains('show'));
            await new Promise(resolve=>setTimeout(resolve,350));
        }

        await page.setViewportSize({width:1440,height:1100}); await new Promise(resolve=>setTimeout(resolve,350)); await page.clock.runFor(500);
        await page.clock.resume(); await page.waitForTimeout(600);
        await page.screenshot({path:path.join(require('node:os').tmpdir(),'gslip-monitoring-desktop.png'),fullPage:false});
        assert.ok(await page.locator('.app-sidebar').evaluate(el=>el.getBoundingClientRect().right>200),'Desktop sidebar remains visible');
        await page.locator('.sidebar-nav a[href="pending_gas_slips.php"]').click();
        await page.waitForURL('**/pending_gas_slips.php');
        assert.deepEqual(errors,[]);
        console.log('Monitoring browser checks passed: native shell, active Monitoring link, desktop/sidebar chart resize, mobile navigation, filter drafts/apply/cancel/X/Escape, search/reset/export, chart/collapse, sorting/pagination, refresh retry and details pause/resume, desktop/mobile.');
    } finally {
        await browser.close();
        const file = path.join(require('node:os').tmpdir(),'sess_'+session);
        if (fs.existsSync(file)) fs.unlinkSync(file);
    }
})().catch(e=>{console.error(e);process.exitCode=1;});
