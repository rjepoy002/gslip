# Duplicate logout confirmation

## Local inspection before changes

No production files or browser session were accessed. The local source contained
both the normal SweetAlert and a safe HTML fallback:

- `assets/js/app-ui.js` / `assets/js/sidebar.js`: SweetAlert handlers for
  `#logoutBtn`, used by the standard and creation pages respectively.
- `includes/footer.php`: a separate SweetAlert handler navigating to
  `main.php?logout=1`, which redirects to `logout.php`.
- `base_panel_container.php`: another inline SweetAlert navigating to
  `logout.php` by GET.
- `logout.php`: GET renders the HTML “Logout Confirmation” form; only a valid
  CSRF POST revokes credentials and destroys the PHP session.
- `assets/js/logout.js`: the working helper fetched the CSRF form in the
  background and POSTed it; failures navigated to the HTML confirmation page.
- `includes/sidebar.php` / `includes/admin_sidebar.php`: rendered the logout
  button and loaded the helper with an unversioned URL.

The working localhost flow was: click -> SweetAlert -> confirm -> helper GET in
the background -> hidden CSRF POST to logout.php -> 302 to index.php. The fetched
HTML form is not displayed during that flow. An existing-session Login redirect
does not interfere because logout has already destroyed that authentication.

The redundant GET branches demonstrably produce: SweetAlert -> confirm -> GET
logout.php -> visible HTML confirmation -> a second confirmation POST. That can
occur with the legacy footer/base-panel handlers, a missing helper, an older
cached app-ui/sidebar handler that always uses GET, or an old helper's failed
relative-URL/CSRF fetch. Multiple target handlers could also be registered on
one button when legacy and shared implementations are included together.

The exact production variant is unconfirmed without its assets/network trace.
The observed second HTML page establishes that the browser reached the GET
fallback, but does not establish whether that came from caching, a missing file,
an old inline handler or a failed request. A deployment synchronization/cache
refresh is necessary; the local source conflicts also warranted a focused fix.

Backup-only implementations under `gsis_backup` and `create_gas_slip - backup.php`
were inspected but not edited. No current route references the dated
`includes/sidebar_06032026.php`; current sidebars are the deployment targets.

## Change

`assets/js/logout.js` now owns the normal button confirmation and secure submit.
One capture listener intercepts `#logoutBtn` before cached/inline legacy target
listeners. An initialization guard prevents duplicate helper loads, and an
in-flight guard prevents duplicate submissions. SweetAlert's existing title,
wording, confirmation/cancel buttons and design are retained.

The endpoint is resolved relative to the helper's script URL, preserving nested
application deployment paths. On confirmation the helper retrieves the session-bound
CSRF token in the background and submits a POST to that endpoint. A failed token
fetch produces a retry error, not another HTML confirmation or an unsafe GET
logout. Cancel performs no request and does not alter remembered credentials.

The separate handlers were removed from app-ui, sidebar, footer and base-panel.
Both current sidebars and the legacy footer load a filemtime-versioned helper.
The capture listener also handles stale old target handlers while the deployment
cache is refreshed. If SweetAlert is unavailable, direct navigation to the HTML
fallback remains safe and requires its own explicit CSRF POST confirmation.

`logout.php`, `main.php`, persistent token/cookie/session revocation, PHP redirects
and maintenance forced logout were not changed by this investigation. Maintenance
keeps its existing authenticated CSRF/event-ID POST and admin restrictions.

## Deploy together

Application files modified for this fix:

1. `assets/js/logout.js`
2. `assets/js/app-ui.js`
3. `assets/js/sidebar.js`
4. `includes/sidebar.php`
5. `includes/admin_sidebar.php`
6. `includes/footer.php`
7. `base_panel_container.php`

Keep the already-deployed secure `logout.php` and Remember Me dependencies in
place. Upload these files as a coherent set, preserve the directory layout, and
purge any hosting/CDN page cache so the updated script URLs reach browsers.
Check DevTools: one SweetAlert, a background GET logout.php, then a document POST
logout.php and redirect to Login; no document GET of the HTML confirmation page.
No production deployment was performed. No application database/schema changes
or SQL migration are required, and nothing was pushed to GitHub.

Test-only changes: `tests/logout_browser.cjs` and the optional
`GSLIP_TEST_LOGOUT=1` hook in `tests/authentication_lifecycle.py`.
The fixture uses a unique disposable database, never live accounts or tokens.
The tests cover normal/remembered logout, Cancel, current sidebar button markup,
mobile layout, duplicate helper loads and stale target listeners, one secure POST,
cookie/session clearing, old-token rejection, direct/legacy GET fallback and a
network-failure retry without a second HTML confirmation. Existing HTTP coverage
checks CSRF and maintenance restrictions.

Executed results:

- Edge: 34 logout browser checks passed.
- Chrome: 34 logout browser checks passed.
- Existing authentication browser suite: 68 checks passed in each browser.
- Authentication HTTP/lifecycle suite: 127 checks passed, including forged-CSRF
  rejection, server-side revocation and maintenance state behavior.
- Existing maintenance lifecycle, maintenance UI and maintenance push tests passed.
- PHP syntax checks passed for both sidebars, footer, base panel and logout.php;
  JavaScript syntax checks passed for logout.js, app-ui.js, sidebar.js and the
  browser test. `git diff --check` passed.

The browser fixture uses real sidebar/admin button markup in an isolated test
container, including a narrow touch-capable viewport. It intentionally removes
the empty fixture dashboard's chart container; it does not claim a production
sidebar visual-layout audit. It traces actual HTTP logout/navigation requests.

Reproduce with an external Playwright installation in `GSLIP_UI_PLAYWRIGHT`,
`GSLIP_TEST_LOGOUT=1`, `GSLIP_UI_CHANNELS=msedge,chrome`, then run
`python tests/authentication_lifecycle.py`.
