# Browser Notifications investigation

The Enable Notifications flow uses `assets/js/push-notifications.js`,
`api/push_public_key.php`, `api/save_push_subscription.php`,
`api/remove_push_subscription.php`, `sw.js` and `includes/web_push.php`.
There is no separate boolean preference endpoint: the current device's
`push_subscriptions` row is the server-side preference.

## Root cause and resolution

`includes/web_push_config.php` was missing. The public-key endpoint returns HTTP
503 when that file is absent, but the old JavaScript replaced its useful message
with “Unable to update notification setting”. PHP's existing logs contained
`[WEB PUSH] CONFIG ERROR` / `Config exists: NO` entries predating Remember Me.
The subscription table and Composer dependencies were already installed.

The owner restored the original ignored configuration file. The three settings
are present; VAPID key format and the mathematical public/private pairing were
validated without printing keys. No new production keys were generated. The owner
subsequently confirmed notifications are working. The private configuration remains
Git-ignored and must be deployed separately with the matching original key pair.

Remember Me was not the source of this configuration failure. Authenticated fetches
use same-origin PHP session cookies. After a remembered navigation restores the
normal PHP session, the push endpoints can validate it exactly as a password login.
Expired sessions return an actionable 401; background API requests deliberately
do not consume remember tokens or silently reauthenticate.

## Focused changes

- `assets/js/push-notifications.js`: show configuration/server/expired-session/CSRF,
  denied-permission, unsupported/insecure-browser, service-worker and network
  messages. Send JSON Accept and session-bound CSRF headers, preserve subscription
  payloads, wait for active service-worker registration and allow transient failures
  to be retried. Request browser permission before asynchronous registration work
  when invoked directly by the user. Diagnostics omit subscription endpoints/keys.
- `includes/push_api.php`: shared JSON response/authentication boundary. Validate
  the database's current session token and active account, require CSRF for writes,
  retain maintenance restrictions, and sanitize database failures.
- The three `api/*push*` endpoints use that boundary. The key endpoint also returns
  a CSRF token and identifies missing/incomplete configuration. Public key retrieval
  never returns the private key.
- `includes/sidebar.php`: supply the CSRF token, announce status changes accessibly,
  and version the script URL so a refreshed page loads the updated client.
- Tests: `tests/push_notifications_http.py`, `tests/push_notifications_ui.cjs`,
  `tests/push_notifications_browser.cjs`, and optional push-test integration in
  `tests/authentication_lifecycle.py`.

No database schema change or SQL migration is required. Subscription ownership,
normal in-app notifications, maintenance push rules and gas-slip delivery payloads
remain unchanged. No Git commit/push is part of this work.

## Verification

`GSLIP_TEST_PUSH=1 python tests/authentication_lifecycle.py` creates a disposable
database and test accounts. It intentionally excludes the production private
configuration and creates independent fixture keys. It tests the real HTTP routes
with normal and restored sessions, enable/disable persistence, invalid/stale/inactive
sessions, CSRF, input validation, missing configuration, sanitized database failures
and maintenance restrictions. 56 push HTTP checks passed.

`node tests/push_notifications_ui.cjs`: 29 JavaScript checks passed for permission,
service worker, enable/disable, CSRF headers, and distinct failure messages.
Existing maintenance lifecycle/push and 119 Monitoring-access checks passed.
Changed PHP/JavaScript syntax and whitespace checks passed.

The optional installed-browser suite runs with an external Playwright installation,
`GSLIP_UI_PLAYWRIGHT`, and `GSLIP_UI_CHANNELS=msedge,chrome`. It uses the real worker,
real authenticated fixture endpoints, and a stub remote subscription transport.
It tests local service-worker `showNotification` delivery separately from remote
push. Edge and Chrome each passed 11 push-browser checks and 68 authentication
browser checks; the full lifecycle suite passed 137 checks. These automated checks do not claim delivery through a real external push
provider to a production device. The owner's live confirmation is separate.
