# Remember Me restoration investigation

## Reproduced cause

The old `rememberAutoLogin()` returned immediately whenever `user_id` existed in
the PHP session. `index.php` only redirected after a *new* remembered login, so a
browser reopening Login with a retained, valid PHP session rendered Login again.
Browsers can retain session cookies as part of session restoration; closing a tab
is not evidence that PHP's authenticated session has expired. The same early return
also prevented a partial session containing only `user_id` from being restored.

The regression was executed before editing application code: the actual login
route returned HTTP 200/Login immediately after successful login, where the test
expected the authenticated dashboard redirect. The test failed as expected. After
the change, it redirects using a freshly database-validated session. A complete
retained session does not consume a remember token, so `last_used_at=NULL` is
expected until persistent authentication is actually needed.

Restoration also previously existed only in `index.php`, after other pages had
already redirected. `includes/config.php` now invokes the shared bootstrap before
page guards, including guards that run before a route's first query. The shared
`includes/auth.php` guard loads that configuration before checking identity.
Dashboard's included guards therefore receive the restored session. GETs of Login
with a valid normal or remembered PHP session redirect to Dashboard. Existing
maintenance/admin restrictions determine the destination during active maintenance.

## Cookie and token lifecycle

1. Successful opted-in login inserts a token with a random selector and SHA-256
   verifier hash. It queues the persistent cookie before committing the transaction.
   Unchecked login retains a session-only PHP cookie and clears/revokes old remember
   credentials according to the existing single-login policy.
2. `gslip_remember` is host-only (no Domain attribute), HttpOnly, SameSite=Lax,
   Secure when the server reports HTTPS/port 443, and scoped to the application
   directory (`/gslip/` in the tested installation). Initial expiry is 30 days.
   HTTP/HTTPS, host aliases and subdirectory mounting must be consistent with the
   URL used to sign in. An HTTPS cookie is intentionally unavailable over HTTP.
3. A document-navigation GET with no valid complete PHP session validates the
   cookie, active user, hash, revocation and UTC expiry. Background fetches, API/AJAX,
   POST, password recovery, logout and maintenance polling never consume it.
4. Restoration locks the user and presented token under the existing maintenance
   advisory lock. It inserts a new selector/hash row with the old absolute expiry,
   regenerates PHP's session ID, hydrates all existing identity/workflow fields,
   queues the replacement cookie, records last use and revokes the old row, then
   commits. Both old and replacement rows record successful last use. Failure to
   prepare storage/headers rolls back and leaves the original database row active.
   A queued response header cannot guarantee network delivery; the browser must
   receive and accept the response. Old-token replays are rejected without
   invalidating the legitimate replacement. A mismatched verifier on an active
   token retains the existing revocation/session-invalidation behavior.
5. Reopening a still-valid normal PHP session does not rotate or revoke a token.
   Closing tabs/browsers has no logout handler. Explicit logout remains a CSRF
   POST that revokes the current selector, clears its cookie and destroys PHP's
   session. Legacy `main.php?logout` now directs to that same confirmation route.

Active maintenance blocks restoration without revoking a token merely for reading
availability. The existing explicit policies are unchanged: enabling maintenance
revokes remembered devices; acknowledged maintenance logout revokes the browser's
credential. Therefore ending such an event cannot revive those tokens. If an
operator independently sets maintenance without revoking a credential, it remains
blocked while active and can be used on an explicit later navigation once access
is available. Ending maintenance itself never logs anyone in.

## Safe diagnostics

PHP's server error log receives structured `GSLIP remember` events:
`token_prepared`, `cookie_queued`, `restore_attempt`, `restore_success`,
`restore_rejected`, `restore_blocked`, `restore_failed`, `existing_session` and
`login_form`. Metadata is restricted to event/reason codes, cookie/identity-present
booleans (including original receipt before validation clears a rejected cookie),
numeric user/token IDs, cookie path and Secure flag. No passwords,
selectors, verifier hashes, session IDs, raw/full cookies, query URLs or raw
database exception text are logged by these diagnostics.

For a failing revisit, `login_form` with `reason=cookie_not_received` means the
server did not receive a usable cookie on that request. Check the browser's cookie
expiry, host, path and HTTPS settings. `existing_session` means PHP authentication
was still valid, so a token-use timestamp is not expected. `restore_rejected`
distinguishes malformed, unknown, expired, revoked, mismatched and ineligible
credentials. `restore_success` confirms server-side rotation and session hydration;
check subsequent browser navigation/cookie storage if that event occurs but the
browser still returns to Login.

## Database findings and deployment

The configured application database was inspected read-only. It contains the
reported three user-1 token rows (first two revoked, third active, all initially
unused); the account is active and maintenance is disabled. All required token
columns exist. No table recreation, schema migration or application-record edits
were performed or are required for this restoration fix. Existing unused valid
tokens are compatible with the new restoration code.

The earlier feature's `gslip_revoke_remember_security` users trigger was not present
in this local database. That is a separate incomplete deployment of account-change
invalidation, not the cause of the Login-page regression. Review/install the
trigger portion of the existing feature migration separately; do not recreate the
token table or rewrite existing users to fix restoration.

The user's actual browser cookie storage was not inspected. The reproduced
retained-session failure is proven; whether a particular live revisit additionally
omits a cookie is established by the new diagnostics rather than inferred from
token timestamps alone. Deploy the PHP changes together, then revisit on the same
host, application path and scheme used at login. No Git push was performed.

## Changed files for this investigation

- `includes/remember_login.php`: bootstrap, complete/session validation, safe
  diagnostics, transactional replacement rotation and non-destructive maintenance
  availability checks.
- `includes/config.php`: restoration before route guards and maintenance enforcement.
- `includes/auth.php`: initialize shared configuration before the standalone guard.
- `index.php`: redirect retained valid sessions; queue issuance before commit.
- `main.php`: route its legacy logout link through the protected logout flow.
- `tests/authentication_lifecycle.py`: regressions, real dashboard, subdirectory
  cookie scope and failure-path coverage using a disposable database only.
- `tests/authentication_ui.cjs`: real dashboard/restart/expired-session tests in
  installed browser channels.
- `docs/authentication-enhancements.md` and this document: updated behavior and findings.

## Executed verification

The suite creates a uniquely named disposable database, uses fixture accounts,
serves the real application at `/gslip/`, and drops that database afterward. It
does not log into or mutate the configured application's user accounts/tokens.

- 127 HTTP/lifecycle checks passed: retained-session Login regression, normal and
  unchecked login, persistent-cookie receipt at the directory entrypoint, direct
  real-dashboard restoration before guards, expired/partial PHP sessions, absolute
  expiry, new-row rotation/last-use, retired/malformed/mismatched/ineligible token
  rejection, logout/CSRF, concurrent rotation, maintenance policies, safe failure
  before response headers, and secret-free diagnostic content.
- Installed Edge and Chrome each passed 68 browser checks, covering persistent-cookie-only
  restart and a restart retaining PHP session cookies, real dashboard delivery,
  expired server-side session, token rotation, password controls and 375px layout.
- Existing maintenance lifecycle, Monitoring (183), Monitoring access (119), gas-slip
  access and bulk/workflow suites passed.
- All 15 pending changed/new PHP files passed syntax checks, authentication browser
  test/password-toggle JavaScript syntax checks passed, and `git diff --check` passed.

The browser suite runs with `GSLIP_UI_PLAYWRIGHT` pointing to an external Playwright
installation and `GSLIP_UI_CHANNELS=msedge,chrome`, followed by
`python tests/authentication_lifecycle.py`. Browser test totals are reported in
its output. The initial expiry and HTTPS Secure option are tested through fixtures;
no 30-day wall-clock wait or production TLS/proxy test is claimed.
