# GSLIP Auto-Refresh Inspection

Inspected November 12, 2026 (Asia/Manila). Source: current local working tree at `C:\xampp\htdocs\gslip`, HEAD `b786364`, including the existing uncommitted Pending/Approved visibility fixes. No remote fetch, checkout or replacement of that working tree was performed. Inspection only: this new report is the only file created or modified by this task.

## A. Executive Summary

**Auto-refresh is present on all three pages, but is PARTIAL against the requested background-update behavior.** Each page includes the same 60-second **idle timer**, not a periodic table-data poll. On expiry, it calls `includes/sync_permissions.php`, then performs a full `location.reload()`. Successful navigation reruns the initial page SQL and sidebar counts.

Verified by executing the current refresh JavaScript in a mocked Node VM: idle expiry requests synchronization/reload; activity postpones refresh; modal guards work; visible-tab return requests immediate refresh. Confirmed deficiencies include indefinite postponement during activity, missing in-flight synchronization protection, reload despite synchronization failure, and a modal/tab-return timer gap.

Notifications poll separately every five seconds. They do not update the rows or sidebar counts. A notification toast can itself prevent idle refresh on pages with bulk controls; unresolved notification records can be presented again on subsequent polls. Maintenance polls every three seconds, also independently of table refresh.

No page receives a full PASS: real browser navigation, network/server response behavior and multi-user workflows were not executed. The existing mechanisms can refresh an idle page, but do not guarantee timely, non-interrupting background updates for an active user.

## B. Page-by-Page Results

| Page | Implemented | Interval | Verification | Issue |
|---|---|---|---|---|
| Pending | Yes, shared full-page idle reload | 60 seconds after latest tracked activity; immediate visible-tab return | **PARTIAL**: shared JS executed; actual page refresh/multi-user workflow unverified | Activity/toasts can block updates; selections depend on bulk restore; no table data polling |
| Recommended | Yes, same shared idle reload | Same | **PARTIAL** | Author-history list and counts refresh only on navigation; no partial row updates |
| Approved | Yes, same shared idle reload | Same | **PARTIAL** | Print selections have no explicit restore; same idle/race/failure risks |

| Inspection item | Pending | Recommended | Approved |
|---|---|---|---|
| Timer configured and shared JS included | Yes | Yes | Yes |
| New/status-changed records appear | On successful reload, if matching current SQL/page/filter; real navigation unverified | Same | Same |
| Table content updated in place | No | No | No |
| Sidebar counts updated in place | No; recomputed on reload | Same | Same |
| Filters/pagination preserved | URL page/group and saved session filters generally survive reload; unsaved UI state not guaranteed | Same | Same; saved search/date/printed settings retained |
| Pause during interaction | Tracked activity resets idle timer; Bootstrap modal and conditional SweetAlert/bulk guards | Same | Same, but print selection alone is not a pause condition |
| AJAX failure handled | Sync catches network/JSON error, but reload still follows | Same | Same |
| Duplicate/overlapping work prevented | One pending idle timeout during normal reset; no sync in-flight/single-inclusion guard | Same | Same |

“60 seconds” is not a maximum staleness guarantee. Every mousemove, mousedown, keydown, scroll or touchstart resets the countdown. Browser timer throttling can further delay hidden-tab work.

## C. Source Code Findings

### Listing refresh chain

1. `pending_gas_slips.php:345–346`, `recommended_gas_slips.php:408–409`, `approved_slips.php:1255–1257` set `$autoRefreshTime=60000` and include `includes/autorefresh.php` once each.
2. `includes/autorefresh.php:3` renders `IDLE_TIME`; `resetIdleTimer()` at line 69 clears/replaces a single timeout. Line 74 returns without rearming if `document.hidden`.
3. Activity listeners at lines 84–91 run in capture phase and reset the timer. There is no recurring table-data `setInterval`.
4. `refreshDashboard()` at line 41 checks a bulk-root busy flag and SweetAlert visibility at lines 43–47, then `.modal.show` at lines 50–55. Blocked refresh rearms the idle timer.
5. `syncPermissions()` at line 7 fetches `includes/sync_permissions.php` using GET, `cache:'no-store'` and same-origin credentials. It returns on non-OK/logged-out responses and catches exceptions. It does not return an actionable success/failure result to the caller.
6. Line 61 awaits sync; line 63 unconditionally calls `location.reload()`. No rows, counts or timestamps are returned/merged by this path.
7. `visibilitychange` at line 97 triggers refresh immediately when visible unless a Bootstrap modal is already open. There is no `online`/`offline` recovery listener or in-flight gate.

The function name “refreshDashboard” is historical; this same function drives all three list pages.

### Backend synchronization

`includes/sync_permissions.php:12` checks session user/token presence; lines 31–58 load the current user and require active status. Subsequent queries compute primary/delegated recommender, department approver and configured private approver flags. There are four direct SELECT statements for an active authenticated actor: user, recommender UNION query, approver, private approver. It updates three session capability flags at lines 209–211 and returns a small JSON object with `success`, `logged_in`, `changed` and flags.

It returns **no gas-slip records or counts** and does not use `changed` to decide whether to reload. It does not compare the supplied session token against `users.session_token`, update session department/area, clear an inactive user's session, or return a redirect instruction consumed by the client. These are existing synchronization limitations, not changes introduced here. Connection is explicitly closed before its normal JSON response; PHP session locking can serialize simultaneous same-session requests.

### Other frontend requests

| Source | Request / behavior | Cadence and purpose |
|---|---|---|
| `assets/js/notifications.js:64`, `:169–171` | `checkNotifications()` → `ajax/get_notifications.php` | Immediate plus 5-second interval; alerts only |
| `ajax/get_notifications.php:15–31` | Recipient's `is_displayed=0` notification rows | One direct SELECT, unbounded pending notification result |
| `assets/js/notifications.js:127` | `ajax/mark_notification_displayed.php` | After SweetAlert resolves; POST updates only matching recipient ID |
| `assets/js/gas-slip.js:37`, `:52`, `:70` | Detail JSON then HTML fetch | User action only, not listing refresh |
| `assets/js/gas-slip.js:179`, `:290` | Reload after successful withdrawal/recommendation/approval feedback | Actor-local workflow completion, independent of idle timer |
| `assets/js/bulk-processing.js:85`, `:267–284` | `load()` / `initialize()` → `bulk_processing.php` state | Initial load, retry or explicit management/action paths; not continuous listing polling |
| `assets/js/bulk-processing.js:169` | Snapshot validation request | Every 3 seconds only during bulk confirmation; local `checking` prevents overlap |
| `assets/js/bulk-processing.js:183`, `:259` | Reload after bulk result or active membership change | Explicit operation, not continuous data feed |
| `assets/js/maintenance.js:146`, `:214–219` | Maintenance state fetch | Immediate, every 3 seconds, focus/visible resume; busy and single-install guards |
| `sw.js:11`, notification-click handler | Push display and click navigation | Event-driven notification/navigation; no DOM row/count update or page-data cache handler |

The three pages load `assets/js/notifications.js` at `pending_gas_slips.php:245`, `recommended_gas_slips.php:393`, `approved_slips.php:1063`. The sidebar loads maintenance and push scripts (`includes/sidebar.php:700–704`). These additional requests must not be mistaken for gas-slip table refresh.

No DataTables initialization or `ajax.reload()` table renderer was found in the scoped pages. Listings are server-rendered PHP tables; the bulk script installs selection controls and tabs but does not insert fresh listing rows after its state fetch.

## D. Workflow Findings

| Scenario | Current behavior and verification boundary |
|---|---|
| A — New slip while assigned recommender watches Pending | With no activity/blocking popup, idle expiry requests reload and current Pending query can include the new slip. Continuous mouse/keyboard activity can postpone this indefinitely. Notification alert does not add a row/count and may block reload. Actual two-user creation/browser test not run. |
| B — Recommendation | Acting user's current handler reloads after success feedback; another open listing relies on idle/visible-tab reload. Removal from Pending is policy-dependent: creator-owned recommended slips intentionally remain in default Pending after the previous fix; assigned recommender-only entries may leave. Recommended history appears only if its existing author/filter scope matches. Recommender-only approval can skip Recommended entirely. |
| C — Approval | Acting user's handler reloads; other users' Approved pages rely on the same idle mechanism. Current Approved creator-or-author helper is present, so authorized recommender creators are eligible after reload. Real cross-user navigation was not executed. |
| D — Multiple users | No server broadcast or data polling updates the three tables directly. Each tab independently waits for its own idle/visibility cycle. Five-second notification polling gives alerts to selected recipients, not freshness for every authorized viewer. |
| E — Private versus coop | Refresh reloads the same vehicle-specific initial queries. No refresh-specific alternative routing is present. Private department recommendation/global approval and coop assigned/delegate recommendation are not changed by this mechanism. Current Pending and Approved creator fixes are present; Recommended history remains author-based. |
| F — Inactivity/connectivity | Hidden timer expiry does not rearm. Normal return to visible triggers immediate sync/reload, but returning with an open modal can leave no timer. Failed sync still reloads; no explicit reconnect retry/backoff. Multiple visible events or a new timer while sync is pending can overlap requests; a hung fetch has no abort timeout. |

## E. Authorization Findings

Actual row/count authorization is performed by the **initial page SQL again after navigation**, not by the sync endpoint. Current working-tree policies include:

- `includes/gas_slip_access.php:71`: default recommender Pending permits authorized action rows plus creator pending/recommended through shared active-user view permission, and existing creator rejected rows.
- `includes/pending/query.php:32`: both recommender Pending count and records use that helper. Approver and normal-preparer branches remain distinct.
- `recommenderApprovedSlipSql()` in `includes/gas_slip_access.php`: approved/printed AND shared active-user view AND creator-or-recommendation-author; used by current recommender Approved count/list, approved-only badge and history counts.
- `recommended_gas_slips.php:45`, `:82`: recommender Recommended history requires `recommended_by=actor`, status recommended and view/group scope.
- `includes/sidebar.php:115`: Pending badge uses the shared helper; Approved badge remains approved-only. Dashboard status cards consume sidebar counts.
- `includes/bulk_processing.php:54`: action records still use recommendation/approval permissions, separately from creator display. `bulk_processing.php:52` validates active database user and current session token, with CSRF for its requests.
- Detail/action endpoints continue to enforce separate view/recommend/approve/reject rules. A row becoming visible after reload does not grant processing authority.

No new refresh-data endpoint was found that selects slips outside these list policies. Nevertheless, permission sync and notification retrieval have weaker token/session checks than the bulk endpoint. Notification retrieval filters by recipient only, not current record-view permission, so stale notification text can survive reassignment. These existing issues require a separate guarded correction; this inspection made none.

Session role flags choose initial query branches, while record permissions consult database assignments. Synchronization refreshes capability booleans but not session area/department. Profile changes can consequently leave some session-based filters stale. Do not assume that a successful sync refreshes every authorization context field.

## F. UI and Performance Findings

- **Full reload:** table/sidebar refresh together after successful navigation, with normal document repaint/network cost. No incremental reconciliation or record deduplication is needed because the whole DOM is replaced. Actual flicker was not visually tested.
- **Selections:** Pending bulk selections are saved under user/path/workflow/group in `sessionStorage` (`assets/js/bulk-processing.js:12`, `:28`, `:96`) and restored only for currently eligible records. Eligibility changes can drop selected IDs, as intended. This does not preserve Approved `.print-check` selections; no application persistence for those was found.
- **Modal/operation protection:** Bootstrap modals defer refresh. SweetAlert deferral depends on a bulk root being present; ordinary-preparer pages do not render that root. Active print checkboxes, an idle user reading rows, or a popup appearing after synchronization begins are not rechecked before reload.
- **Filters and page:** `location.reload()` retains the URL including page/group, and session-saved Approved search/date/printed preferences survive. Unsaved input, selection and scroll preservation is not guaranteed by application code. Browser-native scroll/form restoration may vary. Default pagination does not consistently clamp a now-empty page after records disappear; custom Pending override has its own clamping.
- **Notifications:** no response-status check, catch handler, request timeout or in-flight flag in `checkNotifications`. Hidden tabs still request data and play sound; the hidden path does not mark displayed. Toasts have no auto-dismiss timer, and records are marked displayed only when the toast resolves. The next poll can present the same unresolved notification again.
- **Caching:** sync fetch explicitly requests no-store; bulk API responds no-store. Sync/notification PHP does not explicitly send Cache-Control, and notification fetch uses default cache mode; actual PHP session cache headers/server/proxy behavior were not measured. The service worker has no general page-data fetch cache in the inspected file.
- **Frequency:** per open tab, notifications nominally produce 12 GETs/minute and maintenance 20 state GETs/minute, plus initial/resume requests. An idle visible list adds roughly one permission sync and one full document request/minute, before subresources/other page API calls. These are nominal ceilings/cadences subject to delay, operation guards and browser throttling, not measured traffic.
- **Database load:** notification poll has one direct SELECT plus connection/bootstrap work; sync has four direct SELECTs. Full navigation reruns count/list, sidebar status queries and settings/bootstrap; bulk initialization adds state/group/history queries and may enumerate all view-authorized IDs for group matching. Exact query count depends on role/group/settings. No fixed per-reload total or benchmark is claimed. Multiple tabs multiply this load; maintenance additionally reads state/auth as applicable.
- **N+1:** no per-slip query loop in the sync or notification SELECT path itself. Group management/state has per-group name lookups and other existing queries; no new refresh-specific optimization was attempted.

## G. Identified Bugs

| ID / severity | Page and source | Reproduce / actual versus expected | Recommended correction, not implemented |
|---|---|---|---|
| AR-01 High | All; `includes/autorefresh.php:69–91` | Keep moving mouse/typing/scrolling while another user changes a slip. Every activity restarts the 60-second timer; rows/counts may never refresh. Expected timely background freshness without interrupting interaction. Timer postponement reproduced in VM; multi-user result inferred from missing other row updates. | Separate bounded data-freshness scheduling from user-idle reload policy; preserve operation guards and existing scopes. |
| AR-02 High | All workflow-capability views; `autorefresh.php:43`, `notifications.js:106–127`, `:171` | Receive a new slip toast and leave it unresolved. With bulk root, SweetAlert blocks idle reload; the alert can be shown again every poll. Expected notification should not indefinitely freeze listing freshness. Both component behaviors executed in VM, combined browser sequence unverified. | Prevent duplicate unresolved alerts and avoid using persistent informational toasts as a blanket table-freshness blocker. |
| AR-03 Medium | All; `autorefresh.php:41–63`, `:97` | Trigger multiple visible events while sync is pending. Two fetches and two reload attempts occur. Expected one refresh in flight. Reproduced in VM. A modal opened after the initial guard is not rechecked after await (static finding). | Add single-flight/abort handling, clear competing timer and recheck operation state before navigation. |
| AR-04 Medium | All; `autorefresh.php:74`, `:101–106` | Hidden timer fires; return visible while modal remains open. No refresh and no timer remain until another captured activity. Expected safe pending refresh resumes after interaction. Reproduced in VM. | Always maintain a deferred refresh/rearm path and handle modal-close/resume explicitly. |
| AR-05 Medium | All; `autorefresh.php:20–37`, `:61–63` | Fail sync HTTP/network/JSON or return logged_in=false. Caller still reloads. Expected controlled session/offline response and retry policy. Failed HTTP/network/logged-out reloads reproduced in VM; browser offline navigation outcome unverified. | Return structured sync outcome, timeout/backoff, and handle session expiration/reconnect deliberately. |
| AR-06 Medium | Approved and ordinary-preparer alerts; `approved_slips.php:581`, `autorefresh.php:43` | Select print checkboxes then stop interacting, or show SweetAlert without bulk root. Idle refresh can navigate; application has no print-selection restore and no general SweetAlert guard. Guard omission executed; checkbox loss is source/browser-dependent. | Preserve/defer active selections and guard meaningful interactions regardless of workflow-root presence. |
| AR-07 Medium | All; `notifications.js:64–171` | Slow notification responses overlap successive polls; unresolved toast or hidden notification is fetched again. Expected bounded single-flight/error-handled polling and deduplication. Repeated sound/toasts and overlapping fetches reproduced. | Add in-flight/error/timeout handling, visibility-aware scheduling and display-state deduplication without changing recipients. |
| AR-08 Medium | All; `sync_permissions.php:12`, `:209–211` | Keep a stale-token session or change actor department/area. Sync validates token presence only and updates capability flags only. Expected current authenticated context. Source confirmed; no live session mutation/replay test run. | Reuse consistent active-token validation and deliberately synchronize applicable session context under existing policies. |

AR-01/02 are functional freshness defects against this request's expectation, not proof that the timer never runs. AR-03/04/05/07 have specific reproduced JavaScript behaviors. Endpoint/deployment consequences remain bounded by the stated test limitations.

## H. Recommended Fixes

1. **High:** Define a bounded freshness mechanism that can update scoped rows/counts without waiting for total inactivity. Preserve creator/assignee/approver/private rules and user operations. Do not add a new transport in an inspection-only task.
2. **High:** Resolve notification-repeat and persistent-toast interaction with refresh; keep notification delivery separate from table freshness.
3. **Medium:** Introduce single-flight refresh coordination, timeout/error/session handling, visible/reconnect rearming and a post-sync interaction recheck.
4. **Medium:** Preserve print/bulk/filter/page/scroll context explicitly where full navigation remains, and define behavior when the current page becomes empty.
5. **Medium:** Review permission sync's session-token/context consistency alongside the existing authentication policy. Keep action authorization independent of refresh.
6. **Low:** Measure per-role/query and multi-tab traffic before tuning intervals; document nominal cadences and suppress redundant work safely.

No code, configuration, routing, notifications or permissions were changed.

## I. Test Results

### Executed without modifying existing files or databases

| Command / method | Result | What it establishes |
|---|---|---|
| Inline `node` VM harness reading `includes/autorefresh.php` and substituting its PHP interval with the actual 60000 value | **14 assertions passed**, exit 0 | Initial cadence, idle reload, activity postponement, Bootstrap/SweetAlert guard, rootless alert gap, hidden/visible behavior, modal return stall, network/HTTP/session failure reload and concurrent sync behavior |
| Inline `node` VM harness reading `assets/js/notifications.js` | **3 behavior checks passed**, exit 0 | Hidden repeat sound/no display marking, repeated unresolved visible toast, notification request overlap |
| `node tests/push_notifications_ui.cjs` | **29 checks passed**, exit 0 | Existing push preference/permission/CSRF/worker/error UI behavior; does not prove table refresh |
| `node tests/maintenance_ui.cjs` | **PASS**, exit 0 | Existing maintenance modal/countdown/deduplication/offline safety/admin behavior; does not prove gas-slip listing refresh |

The inline harnesses used fake DOM/timers/fetch/reload functions. Passing assertions include reproductions of defects; they do not mean those defects are fixed. No real network poll, PHP session update, notification-display POST or workflow request was sent during this inspection. No dedicated auto-refresh regression suite was found in the inspected tests.

Write-based access/bulk/workflow suites were not run: they create fixture databases/records, outside this turn's strict read-only verification scope. Earlier visibility-test results are context, not newly executed auto-refresh evidence. No browser automation or simultaneous users were run; no claim of complete end-to-end auto-refresh success is made.

### Exact browser/multi-user checks still required

Use an explicitly authorized disposable environment with separate preparer, assigned recommender, department approver, global private approver and creator-recommender sessions:

1. Open Pending, Recommended and Approved tabs; log Network timings and current page/group/filter/count/selection state.
2. In another session create private and coop slips. Leave first tab idle for over 60 seconds, then repeat while continuously interacting. Record whether rows and sidebar counts change and whether a toast blocks refresh.
3. Recommend and approve using existing routing. Check each authorized viewer, including creator-owned recommended entries intentionally remaining in Pending and creator-owned completed entries appearing in Approved.
4. Test page 2+, custom Requested By groups, saved and unsaved search/dates/Include Printed, selected bulk/print boxes, scrolled position and open detail/confirmation modals.
5. Hide a tab for several minutes, let its timeout expire, return with/without modal, then close the modal without extra activity. Verify recovery.
6. Throttle sync beyond 60 seconds, switch visibility rapidly and inspect overlapping requests. Open a modal during the delayed request. Disconnect/reconnect, return HTTP 401/500/invalid JSON and test revoked-token sessions; record reload/backoff/session behavior.
7. Observe notification polling while hidden and unresolved toasts while visible, then dismiss/click View. Check duplicate alerts, marking success/failure and navigation/filter loss.
8. Compare request/query volume with one and multiple tabs. Confirm no records outside existing authorization are rendered after any refresh.

Inspection complete. Existing uncommitted visibility work was preserved. No migrations, commits or pushes were performed. Await authorization before implementing corrections.
