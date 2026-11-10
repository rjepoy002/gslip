/* Run through authentication_lifecycle.py against its disposable database. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const {chromium} = require(process.env.GSLIP_UI_PLAYWRIGHT || 'playwright');
const base = process.env.GSLIP_AUTH_TEST_URL;
if (!base) throw new Error('Run with an isolated GSLIP_AUTH_TEST_URL');
let checks = 0;
const check = (value, label) => { assert.ok(value, label); checks++; };

(async () => {
  const channel = process.env.GSLIP_UI_CHANNEL;
  const launchOptions = {headless:true, ...(channel ? {channel} : {})};
  let browser = await chromium.launch(launchOptions);
  try {
    let context = await browser.newContext();
    let page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => {
      // Dashboard fixtures contain no chart records or notification endpoints.
      // This suite checks JavaScript errors on the authentication pages.
      if (/\/(index|reset_password)\.php/.test(page.url())) errors.push(error.message);
    });
    await page.goto(base+'index.php');
    await page.waitForSelector('.password-toggle');
    check(await page.locator('input[type=password]').count() === 3, 'login and signup have three hidden password fields');
    check(!(await page.locator('#rememberMe').isChecked()), 'Remember Me starts unchecked');
    async function toggle(inputSelector) {
      const input = page.locator(inputSelector);
      const id = await input.getAttribute('id');
      const button = page.locator(`button[aria-controls="${id}"]`);
      await input.fill('Toggle-test-123!');
      const required = await input.getAttribute('required');
      await input.evaluate(el => el.setSelectionRange(2,5));
      await button.click();
      check(await input.getAttribute('type') === 'text' && await input.inputValue() === 'Toggle-test-123!', id+' reveals without modifying value');
      check(await button.getAttribute('aria-label') === 'Hide password', id+' accessible hide label');
      check(await input.evaluate(el => document.activeElement === el && el.selectionStart === 2 && el.selectionEnd === 5), id+' focus and selection preserved');
      await button.focus(); await page.keyboard.press('Enter');
      check(await input.getAttribute('type') === 'password' && await button.getAttribute('aria-label') === 'Show password', id+' keyboard toggle hides');
      check(await input.getAttribute('required') === required, id+' validation preserved');
      const box = await input.boundingBox(), eye = await button.boundingBox();
      check(eye.x >= box.x+box.width-55 && eye.x+eye.width <= box.x+box.width+1, id+' eye aligned inside right edge');
    }
    await toggle('#password');
    await page.getByText('Create an account', {exact:true}).click();
    await page.locator('#signupModal').waitFor({state:'visible'});
    await toggle('#regPass');
    check(await page.locator('#regConfirm').getAttribute('type') === 'password', 'confirmation independent');
    await toggle('#regConfirm');
    await page.locator('#signupModal [data-bs-dismiss="modal"]').first().click();
    await page.locator('#signupModal').waitFor({state:'hidden'});
    await page.getByText('Forgot Password?',{exact:true}).click();
    await page.locator('#forgotPasswordModal').waitFor({state:'visible'});
    check(await page.locator('#forgotPasswordModal input[type=password]').count() === 0, 'forgot identity modal has no password input');
    await page.locator('#forgotPasswordModal [data-bs-dismiss="modal"]').first().click();
    await page.locator('#forgotPasswordModal').waitFor({state:'hidden'});
    await page.goto(base+'reset_password.php?token=ui-test-token');
    await page.waitForSelector('.password-toggle');
    check(await page.locator('input[type=password]').count() === 2, 'reset page has two hidden fields');
    await toggle('input[name=password]');
    await toggle('input[name=confirm_password]');
    await page.setViewportSize({width:375,height:812});
    await page.screenshot({path:path.join(os.tmpdir(),'gslip-auth-reset-mobile.png'),fullPage:true});
    check(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), 'reset mobile has no horizontal overflow');
    await page.goto(base+'index.php');
    await page.waitForSelector('.password-toggle');
    await toggle('#password');
    await page.screenshot({path:path.join(os.tmpdir(),'gslip-auth-login-mobile.png'),fullPage:true});
    check(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), 'login mobile has no horizontal overflow');
    await page.getByText('Create an account',{exact:true}).click();
    await page.locator('#signupModal').waitFor({state:'visible'});
    await toggle('#regPass'); await toggle('#regConfirm');
    await page.screenshot({path:path.join(os.tmpdir(),'gslip-auth-signup-mobile.png'),fullPage:true});
    check(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), 'signup mobile has no horizontal overflow');
    // Real checked login followed by a new browser process, retaining only
    // persistent cookies (no PHP session restore).
    await page.goto(base+'index.php');
    await page.locator('#username').fill('auth-user');
    await page.locator('#password').fill('Auth-test-123!');
    await page.locator('#rememberMe').check();
    await Promise.all([page.waitForURL('**/dashboard.php'),page.locator('form[novalidate] button[type=submit]').click()]);
    const retainedResponse = await page.goto(base+'index.php');
    check(page.url().endsWith('/dashboard.php') && retainedResponse.status()===200, 'reopening login with retained PHP session returns real dashboard');
    const allCookies = await context.cookies();
    const persistent = allCookies.filter(c => c.name === 'gslip_remember');
    check(persistent.length === 1 && persistent[0].httpOnly && persistent[0].sameSite === 'Lax', 'browser receives protected persistent cookie');
    check(allCookies.find(c => c.name === 'PHPSESSID').expires === -1, 'PHP session cookie remains session-only');
    await browser.close();
    // Browsers can restore session cookies too. That must redirect a still-valid
    // PHP session rather than rendering Login or consuming the remember token.
    browser = await chromium.launch(launchOptions);
    context = await browser.newContext(); await context.addCookies(allCookies);
    page = await context.newPage();
    const retainedRestart = await page.goto(base+'index.php');
    check(retainedRestart.status()===200 && page.url().endsWith('/dashboard.php'), 'browser restart retaining PHP session cookies returns dashboard');
    check((await context.cookies()).find(c => c.name==='gslip_remember').value===persistent[0].value, 'retained-session restart does not rotate an unused remember token');
    await browser.close();
    browser = await chromium.launch(launchOptions);
    context = await browser.newContext(); await context.addCookies(persistent);
    page = await context.newPage();
    const restoredResponse = await page.goto(base+'dashboard.php');
    check(page.url().endsWith('/dashboard.php') && restoredResponse.status()===200, 'checked login restores before actual dashboard guards after browser process restart');
    const rotated = (await context.cookies()).find(c => c.name === 'gslip_remember');
    check(rotated.value !== persistent[0].value && Math.abs(rotated.expires - persistent[0].expires) < 1.1, 'browser cookie rotates with absolute expiry');
    await context.request.get(base+'expire.php');
    const expiredResponse = await page.goto(base+'dashboard.php');
    check(expiredResponse.status()===200 && page.url().endsWith('/dashboard.php'), 'expired server-side PHP session restores using the browser cookie');
    const afterExpiry = (await context.cookies()).find(c => c.name === 'gslip_remember');
    check(afterExpiry.value!==rotated.value, 'expired-session restoration rotates browser credential again');
    await page.goto(base+'index.php');
    check(page.url().endsWith('/dashboard.php'), 'remembered authenticated user opening Login is redirected to dashboard');
    await page.addScriptTag({url:base+'assets/js/logout.js'});
    await Promise.all([page.waitForURL('**/index.php'),page.evaluate(() => window.gslipLogout())]);
    check(!(await context.cookies()).some(c => c.name === 'gslip_remember'), 'existing confirmation helper posts CSRF logout and clears cookie');
    check(errors.length === 0, 'authentication pages have no JavaScript errors: '+errors.join('; '));
    console.log(`${channel || 'chromium'}: ${checks} authentication browser checks passed, desktop and 375px mobile.`);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode=1; });
