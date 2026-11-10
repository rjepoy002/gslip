# Authentication enhancements

## Deploy before enabling Remember Me

The inspected connection in `includes/config.php` uses MySQL database
`gsccxzp_pal_db_test`. Its `users.id` is signed `INT(11)` and its users table is
InnoDB. Application database records have not been migrated by this change.

Run `migrations/20261110_remember_tokens.sql` with a MySQL/MariaDB client that
supports `DELIMITER`. It creates an InnoDB `remember_tokens` table and an
application-owned BEFORE UPDATE trigger on users. It does not rewrite existing
records or add users columns. No foreign key is required. The deployment account
needs CREATE TABLE and TRIGGER privileges. Re-running creates no duplicate table
and replaces only `gslip_revoke_remember_security`; deploy without concurrent
account edits while replacing the trigger.

The trigger is the minimal additional invalidation mechanism: changes to password,
status, username, role, department or area revoke all of that user's remember
tokens and clear their normal session token atomically. This covers the existing
reset-password handler, legacy administrator password resets, account approval,
deactivation/rejection and direct SQL edits. The runtime account must be able to
read/insert/update remember_tokens and update users. Deploy the complete SQL,
including the trigger, before offering this feature. Without the table, ordinary
unchecked login still works; opted-in login fails safely with a generic error.

## Behavior and security

Login remains `index.php`; `index_09042026.php` delegates to the same implementation.
The existing workflow/delegation session fields are hydrated by
`includes/login_session.php` for password and remembered authentication alike.
Passwords and account eligibility are rechecked under a users row lock. The
existing single-active-login rule remains: a new password login revokes older
remembered devices as well as replacing the normal session token.

Remember Me starts unchecked. Its host-only `gslip_remember` cookie contains a
128-bit random selector and 256-bit random verifier. Only a SHA-256 verifier hash
is stored in the database. The cookie is HttpOnly, SameSite=Lax, Secure on HTTPS,
and scoped to the application directory. HTTPS detection uses the web server's
HTTPS flag/port, never an untrusted forwarded header; TLS-terminating proxies must
configure these trusted server values. Use HTTPS in production.

Auto-login runs during document-navigation GETs before the protected routes'
authentication guards. The shared configuration bootstrap also covers routes whose
guards precede their first query. A valid existing session opening Login redirects
to Dashboard without consuming a token. POSTs, background API/AJAX requests,
password recovery, maintenance polling and logout never consume a token. Success
regenerates the PHP session ID and creates an ordinary server-validated session.
A new selector/verifier row is saved under transactional row locks; its cookie
header is queued before the old row is revoked and the transaction commits. Both
rows record last use, and the original UTC expiry is retained, never extended
beyond 30 days. Concurrent requests presenting the retired token fail closed
without invalidating its legitimate replacement. A mismatch on an active token
revokes that selector and invalidates the user
session; a stolen *current* bearer cookie used before its owner cannot be
distinguished from the owner's cookie. Rotation/replay detection limits reuse,
but cannot prevent that first use. No grace window accepts old verifiers.

Login/rotation share the existing maintenance advisory lock. Active maintenance
never permits remembered authentication, including administrators. Existing
explicit administrator password login remains allowed; it issues no persistent
token during the warning or active maintenance phases. Starting maintenance revokes every remembered
device (also offline devices). Existing normal-session countdown, admin exemption,
CSRF, event-id and stale-tab checks remain authoritative. Maintenance logout only
revokes and clears; ending maintenance never initiates login. A restoration
availability check only blocks while active; it does not itself revoke a token.
See `remember-session-restoration.md` for the subsequent investigation and tests.

Logout is now POST plus CSRF. Existing GET links render a confirmation form,
without logging anyone out. Existing JavaScript confirmations use the same-origin
form token to POST directly, preserving the existing single confirmation. Login,
Create Account, Forgot Password and Reset
Password also use session-bound CSRF tokens. Existing maintenance CSRF is separate
and unchanged. Password-debug output was removed. Password validation, recovery
identity checks and the existing short-lived recovery-token design are otherwise
preserved; the username-plus-middle-name recovery method is an existing weakness
and should be upgraded separately to an authenticated recovery channel.

All authentication password fields get independent keyboard-accessible local SVG
toggles. They start hidden and re-hide on form reset/modal close. Existing values,
required attributes, form selectors and validation remain intact. The Forgot
Password modal has no password field; its resulting Reset Password page has two.

Expired/revoked token rows can be periodically purged after an appropriate audit
retention period. Do not log cookies or verifiers. Back up the database, deploy PHP,
JS and CSS together, and invalidate cached JS/CSS on deployment. No push is needed.

## Verification

`tests/authentication_lifecycle.py` runs actual PHP HTTP routes against a uniquely
named disposable database, tests the migration twice, and leaves configured
application data unchanged. `tests/authentication_ui.cjs` checks real rendered
authentication forms and responsive password controls in Chromium. See the final
delivery for executed results and any remaining manual checks.

Initial implementation verification on November 10, 2026 (superseded for
restoration coverage by `remember-session-restoration.md`):

- Authentication lifecycle: 100 checks passed, including concurrent PHP-process
  rotation, absent migration, recovery/reset, revoked/malformed/mismatched/expired
  tokens, original absolute expiry, single-login behavior and maintenance lifecycle.
- Chromium: 62 checks passed, including an actual browser-process restart with
  persistent cookies only, independent password controls, keyboard/focus/selection,
  responsive 375px layouts and CSRF POST logout through the existing UI helper.
- Existing maintenance lifecycle, clock, UI and push suites passed.
- Existing Monitoring: 183 checks; Monitoring access: 119 checks; display formatting:
  53 checks. Gas-slip access and bulk-processing/workflow suites passed.
- All 12 changed/new PHP files passed `php -l`; changed/new JavaScript files passed
  `node --check`; `git diff --check` passed. Mobile screenshots were visually checked.
- The application database was checked read-only afterward: neither the new table
  nor the new trigger is installed there. Migration execution was confined to
  disposable databases.

To reproduce browser verification, install Playwright outside the repository and
set `GSLIP_UI_PLAYWRIGHT` to its module directory, then run
`python tests/authentication_lifecycle.py`. The suite expects the existing local
XAMPP MySQL configuration; `GSLIP_TEST_PHP` and `GSLIP_TEST_MYSQL` override executable
paths. Check HTTPS cookie transmission and trusted proxy configuration on the real
deployment; the automated suite verifies Secure cookie options, not a production
TLS endpoint. Expiry is tested by advancing database fixture timestamps rather
than waiting 30 days.

## Changed files

- Authentication routes: `index.php`, `index_09042026.php`, `logout.php`,
  `signup_handler.php`, `forgotpass_handler.php`, `reset_password.php`.
- Shared authentication: `includes/auth_security.php`,
  `includes/login_session.php`, `includes/remember_login.php`.
- Maintenance integration: `maintenance.php`.
- Logout UI integration: `includes/sidebar.php`, `includes/admin_sidebar.php`,
  `assets/js/logout.js`, `assets/js/app-ui.js`, `assets/js/sidebar.js`,
  `assets/js/gas-slip.js`.
- Password controls: `assets/js/password-toggle.js`,
  `assets/css/password-toggle.css`.
- Database: `migrations/20261110_remember_tokens.sql`.
- Tests: `tests/authentication_lifecycle.py`, `tests/authentication_ui.cjs`,
  `tests/maintenance_lifecycle.py` (copy the new helper into its isolated fixture).
- Documentation: this file.

## Existing schema issue found during inspection

The local `users.role` definition is `ENUM('admin') NULL`, while the existing
`signup_handler.php` inserts `'user'` for a new registration. On a strict MySQL
connection this already prevents new-account insertion. This change does not
silently widen that enum or change existing roles. The test suite checks Create
Account CSRF/password validation and UI, but does not claim successful new-account
creation against that incompatible schema. Resolve the existing role definition
in a separately reviewed deployment migration before testing account creation
end to end. This is independent of the required remember-token migration.

For the inspected local definition only, the separate schema correction is:

```sql
-- Optional: inspect SHOW COLUMNS FROM users LIKE 'role' on the deployment first.
-- Preserve any additional roles if a different installation already has them.
ALTER TABLE gsccxzp_pal_db_test.users
    MODIFY COLUMN role ENUM('admin','user') NULL DEFAULT NULL;
```

This optional SQL is not part of `20261110_remember_tokens.sql` and was not run
against the application database.
