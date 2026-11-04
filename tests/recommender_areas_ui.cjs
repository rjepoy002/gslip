/* Run with GSLIP_UI_PLAYWRIGHT pointing to an external Playwright installation.
 * Renders PHP fixtures; intercepts saves. Never writes application data. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const {execFileSync} = require('node:child_process');
const {chromium} = require(process.env.GSLIP_UI_PLAYWRIGHT || 'playwright');
const root = path.resolve(__dirname, '..');
const php = `
$multiAreaRecommenders = true; $canManageRecommenders = true;
$role = 'user'; $recommenderDepartment = 1;
$_SESSION = ['recommender_csrf' => 'fixture-token'];
$recommenderAreas = [];
for ($i = 1; $i <= 30; $i++) { $recommenderAreas[] = ['id' => $i, 'area_name' => ['Roxas', 'Taytay', 'El Nido'][$i-1] ?? 'Area ' . $i]; }
$assignedAreas = [10 => [1,2], 11 => [3]];
$currentRecommenders = [
 ['id'=>10,'first_name'=>'Recommender','last_name'=>'A','middle_name'=>'','designation'=>'Supervisor','area_name'=>'Roxas'],
 ['id'=>11,'first_name'=>'Recommender','last_name'=>'B','middle_name'=>'','designation'=>'Supervisor','area_name'=>'El Nido']
];
echo '<div class="container-fluid py-3"><div class="row">';
include 'includes/settings/cards/recommender_card.php';
echo '</div></div>';
include 'includes/modals/recommender_areas_modal.php';
$recommenderCandidates = [['id'=>22,'first_name'=>'Albert','last_name'=>'Artillaga','designation'=>'Supervisor','area_name'=>'Roxas']];
function formatUserDisplayName($user) { return $user['last_name'] . ', ' . $user['first_name']; }
include 'includes/modals/recommender_modal.php';
`;
const html = execFileSync('php', ['-r', php], {cwd: root, encoding: 'utf8'});
(async () => {
    const browser = await chromium.launch({channel: 'chrome', headless: true});
    try {
        const page = await browser.newPage({viewport: {width: 1280, height: 900}});
        const failures = [];
        page.on('pageerror', error => failures.push(error.message));
        await page.route('http://gslip.test/**', route => route.fulfill({body: '<html><body></body></html>', contentType: 'text/html'}));
        await page.goto('http://gslip.test/fixture');
        await page.setContent(`<meta name="viewport" content="width=device-width, initial-scale=1">${html}`);
        for (const file of ['assets/css/bootstrap.min.css', 'assets/css/style.css']) {
            await page.addStyleTag({content: fs.readFileSync(path.join(root,file),'utf8')});
        }
        for (const file of ['assets/js/bootstrap.bundle.min.js','assets/js/sweetalert2.all.min.js','assets/js/recommender-areas.js']) {
            await page.addScriptTag({content: fs.readFileSync(path.join(root,file),'utf8')});
        }
        let requests = [];
        let rejectSave = false;
        await page.route('**/save_recommender_areas.php', async route => {
            requests.push(route.request().postData());
            await new Promise(resolve => setTimeout(resolve, 150));
            const submitted = [...route.request().postData().matchAll(/name="area_ids\[\]"\r?\n\r?\n(\d+)/g)].map(match => Number(match[1]));
            const restored = !submitted.length;
            const areas = (restored ? [1] : submitted).map(id => ({id,area_name: ['Roxas','Taytay','El Nido'][id-1] || 'Area ' + id}));
            await route.fulfill({status: rejectSave ? 400 : 200, contentType: 'application/json', body: JSON.stringify(rejectSave ? {success:false,message:'Unauthorized department.'} : {success:true,areas,default_area_restored:restored,message:restored ? "Primary area Roxas retained as the recommender's default area." : 'Assigned areas updated.'})});
        });
        const edit = page.locator('.edit-recommender-areas').first();
        const modal = page.locator('#recommenderAreasModal');
        const open = async () => { await edit.click(); await page.waitForFunction(() => document.activeElement.id === 'recommenderAreasSearch'); };
        const close = async () => { await modal.getByRole('button',{name:'Cancel',exact:true}).click(); await modal.waitFor({state:'hidden'}); };
        const dismissSuccess = async () => { await page.locator('.swal2-popup').waitFor(); await page.evaluate(() => Swal.close()); };
        assert.equal(await page.locator('#recommenderModal select').count(),0);
        assert.deepEqual(await page.locator('#recommenderModal th').allTextContents(), ['Name','Designation','Area']);
        assert.equal(await page.locator('#recommenderModal .add-recommender-row').count(),1);
        // The second fixture represents a newly registered user with one saved primary area.
        await page.locator('.edit-recommender-areas').nth(1).click();
        await page.waitForFunction(() => document.activeElement.id === 'recommenderAreasSearch');
        assert.equal(await modal.locator('input:checked').count(),1);
        assert.equal(await modal.locator('input[value="3"]').isChecked(),true);
        await close();
        assert.equal(await page.locator('tr[data-user-id="10"] .badge').count(),2);
        assert.equal(await page.locator('#recommenderTableBody select').count(),0);
        await open();
        assert.equal(await modal.locator('input:checked').count(),2);
        await page.locator('#recommenderAreasSearch').fill('el nido');
        assert.equal(await modal.locator('.area-checkbox-option.d-flex').count(),1);
        await modal.locator('input[value="3"]').check();
        assert.match(await page.locator('#recommenderAreasCount').innerText(),/^3 of 30/);
        await page.locator('#recommenderAreasSave').click();
        await page.getByText('Saving…',{exact:true}).waitFor();
        await modal.waitFor({state:'hidden'});
        await dismissSuccess();
        assert.match(requests[0],/fixture-token/);
        assert.equal(await page.locator('tr[data-user-id="10"] .badge').count(),3);
        await open();
        assert.equal(await modal.locator('input:checked').count(),3);
        await page.locator('#recommenderAreasClearAll').click();
        await close();
        await open();
        assert.equal(await modal.locator('input:checked').count(),3);
        rejectSave = true;
        await page.locator('#recommenderAreasSave').click();
        await page.locator('#recommenderAreasError').waitFor();
        assert.match(await page.locator('#recommenderAreasError').innerText(),/Unauthorized/);
        assert.equal(await page.locator('tr[data-user-id="10"] .badge').count(),3);
        rejectSave = false;
        await page.locator('#recommenderAreasClearAll').click();
        const beforeReset = requests.length;
        await page.locator('#recommenderAreasSave').click();
        await page.getByRole('heading',{name:'Reset to Default Area?',exact:true}).waitFor();
        await page.getByText("No areas are selected. This will reset the recommender's assigned areas to their primary area: Roxas. Do you want to continue?",{exact:true}).waitFor();
        assert.equal(requests.length,beforeReset);
        assert.ok(await page.locator('#recommenderAreasModal .swal2-popup').isVisible());
        await page.getByRole('button',{name:'Keep Editing',exact:true}).click();
        await page.locator('.swal2-container').waitFor({state:'hidden'});
        assert.equal(await modal.isVisible(),true);
        assert.equal(await modal.locator('input:checked').count(),0);
        assert.equal(requests.length,beforeReset);
        assert.equal(await page.locator('#recommenderAreasSave').isEnabled(),true);
        await page.locator('#recommenderAreasSave').click();
        await page.getByRole('button',{name:'Reset to Default',exact:true}).click();
        await modal.waitFor({state:'hidden'});
        assert.equal(await page.locator('.swal2-cancel:visible').count(),0);
        await page.getByText("Primary area Roxas retained as the recommender's default area.",{exact:true}).waitFor();
        assert.equal(await page.locator('tr[data-user-id="10"] .badge').count(),1);
        assert.equal(await page.locator('tr[data-user-id="10"] .badge').innerText(),'Roxas');
        await dismissSuccess();
        await open();
        assert.equal(await modal.locator('input:checked').count(),1);
        assert.equal(await modal.locator('input[type="checkbox"][value="1"]').isChecked(),true);
        assert.match(await page.locator('#recommenderAreasCount').innerText(),/^1 of 30/);
        await page.locator('#recommenderAreasSearch').fill('no matching area');
        await page.locator('#recommenderAreasNoResults').waitFor();
        await page.locator('#recommenderAreasSelectAll').click();
        assert.equal(await modal.locator('input:checked').count(),30);
        await close();
        for (const width of [375,320]) {
            await page.setViewportSize({width,height:568});
            await open();
            const geometry = await modal.evaluate(element => {
                const dialog = element.querySelector('.modal-dialog').getBoundingClientRect();
                const list = element.querySelector('fieldset');
                return {left:dialog.left,right:dialog.right,viewport:innerWidth,scrolls:list.scrollHeight > list.clientHeight};
            });
            assert.ok(geometry.left >= 0 && geometry.right <= geometry.viewport,`Modal fits ${width}px`);
            assert.ok(geometry.scrolls,'Checkbox list scrolls');
            await page.screenshot({path:path.join(os.tmpdir(),`gslip-areas-${width}.png`)});
            await close();
        }
        assert.deepEqual(failures,[]);
        console.log('PASS: existing badges/prechecks; search/count; multi-area save/loading; reopen; cancel; reset confirmation/cancel/no premature request; fallback badges/count/reopen; error feedback; Select All; 375px/320px scrollable modal; no browser errors.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
