# Gas Slip Monitoring Dashboard

Open `http://localhost/gslip/monitoring.php` after signing in through the normal GSLIP login. No menu changes, database migration, new credentials, or deployment step is needed locally.

## Files

All files in this implementation are new; existing application files are unchanged.

- `monitoring.php`: authenticated native GSLIP page and JSON data/options/detail GET endpoints.
- `includes/monitoring.php`: prepared queries, validation, shared filter predicate, scoped summaries/chart/table/details.
- `assets/js/monitoring.js`: Chart.js rendering, filters/search, sorting/pagination, modal, idle refresh and cancellation.
- `assets/css/monitoring.css`: scoped monitoring components, scrollable chart/table and accessible native dialog styling.
- `tests/monitoring.php`: disposable MySQL integration tests.
- `tests/monitoring_http.php`: read-only local XAMPP endpoint checks using a temporary session.
- `tests/monitoring_ui.cjs`: desktop/mobile browser regression checks with controlled responses and clock.
- This document.

## Existing components and confirmed schema

The page uses `includes/config.php` and the dashboard's session keys (`user_id`, `session_token`, `role`, `department_id`, `area`), redirecting missing sessions to `index.php`. JSON requests return 401 instead of HTML. Module entry uses the department-only policy in `includes/monitoring_access.php`: active administrators always enter; other active users require their current department in the saved allowlist. Once admitted, every user can read all Approved/Printed slips organization-wide inside Monitoring, regardless of their operational department, area or recommender assignment. Operational `gasSlipPermissionSql()` restrictions remain unchanged outside Monitoring.

Inspection of the actual database was necessary: the checked-in SQL dump has an older schema. The current relationships are gas slip `user_id` → `users.department_id` → `departments.id`, gas slip `area_id` → `areas.id`, `department_areas` mappings, route links using the internal gas slip ID, vehicle ID, and fuel requests using the internal gas slip ID. The details presentation uses the fields and relationships in `view_gas_slip_modal.php`, with a dedicated text-only renderer so no mutation controls or handlers are imported.

The department chart retains the bundled Chart.js 2.9.3 library, vertical bar options, palette, department ID/area-name ordering and ASOD `S-` / ANOD `N-` labels from `assets/js/dashboard.js` and `includes/dashboard/counts.php`. ASOD/ANOD are split by area; other departments aggregate all their areas. Every admitted user receives the same complete ordered categories, including zero-count categories. Department-area mappings are augmented by Approved/Printed slips' observed areas so unmapped historical areas are counted rather than omitted. The existing dashboard aggregation is adapted locally to avoid executing its unrelated counts or changing its status behavior.

`dashboard.php` supplies a 60,000 ms interval to `includes/autorefresh.php`. Monitoring adapts its idle timeout, mouse/keyboard/scroll/touch activity resets, modal pause, and refresh on tab visibility. The full-page reload is replaced with an asynchronous data request. It does not include the original script, which would reload the page and interrupt filtering. Permissions do not depend on stale session flags, so no permission-sync call is needed. There is one idle timer; hidden tabs stop polling, and closing the modal resumes refresh. New requests abort obsolete requests and version checks reject stale responses. Temporary errors retain the last successful data and timestamp. Chart data is updated on the existing instance.

## Counting and filters

`status = approved` and `status = printed` are mutually exclusive current states. `update_printed_at.php` transitions Approved to Printed and sets `printed_at`; the timestamp alone is not an additional counting category. Total always equals Approved + Printed under the active filters. Other states are excluded, including from direct details requests.

The shared `monitoringWhere()` predicate applies access control, reporting-year bounds, optional inclusive date range, status, and literal keyword search. Dates intersect the chosen year, as stated on the page. Cards and table additionally apply department/area selections. The graph deliberately omits those two selections to retain comparison context, while retaining year/date/status/search/access filtering. Clicking ASOD/ANOD bars selects both department and area; clicking again clears both. Other bars select their department. Manual department changes reset the area. Reset restores current year, empty dates/search, all departments/areas/statuses, date-descending sort, 25 rows, and page 1.

Queries start from one gas slip row. Route searches use EXISTS; routes are loaded in one batched query for the current page rather than joined into counts or fetched per row. Fuel rows are retrieved only for the selected details record. Graph totals use COUNT(DISTINCT slip ID) and deduplicated department-area mappings. The data endpoint uses a read-only consistent-snapshot transaction so concurrent changes cannot produce mismatched counts and rows. Sorting uses a column whitelist and a stable internal-ID secondary order. Out-of-range pages clamp after deletions or status changes.

All user content is inserted using DOM textContent. IDs and filter values are bound; only validated sort identifiers and fixed module-access predicates are placed into SQL. Only GET read operations are exposed, so no mutation CSRF token is required. Responses disable caching and expose no SQL errors or credentials.

## Verification

Run from the project directory:

```powershell
php tests/monitoring.php
php tests/monitoring_http.php
php tests/gas_slip_access.php
php tests/recommender_areas.php
$env:GSLIP_UI_PLAYWRIGHT = 'path\to\external\node_modules\playwright'
node tests/monitoring_ui.cjs
```

Results: 72 monitoring SQL/validation checks, 8 real local HTTP checks, and the browser suite passed. Existing 43 gas-slip authorization and 39 area-assignment checks also passed. PHP syntax and JavaScript syntax checks passed. Browser checks cover populated/empty tables, pagination, sorting, status/search/year/reset, chart selection toggle, safe text, details, refresh pause/resume and state preservation, hidden-tab suspension, failure recovery, and desktop/mobile overflow. Screenshots at 1440px and 390px were visually reviewed. SQL tests cover multiple routes, counting, authorization for each current database user, inclusive end dates, invalid dates/sorts, and stable pagination ties. The SQL suite creates and drops a uniquely named disposable copy of the required local tables; it does not modify application records. HTTP tests create and destroy one temporary session and only read application endpoints. Both PHP tests are CLI-only.

## Limits and operational decisions

- The existing database account is reused. No SELECT-only account is configured; monitoring explicitly opens read-only transactions. Provisioning separate credentials remains an administrator deployment decision.
- No indexes or schema changes were made. The current gas-slip indexes include its primary ID, custom slip ID, and `(area_code, date_issued)`. Date/status/creator filtering and correlated permission/search queries should be profiled against production volume before adding separately reviewed indexes. No production-scale performance benchmark was run.
- Department/area relationships and reporting names come from the current master data. The Monitoring graph includes observed Approved/Printed department-area combinations alongside registered mappings; other departments aggregate independently of area mappings.
- Browser regression data is controlled; real database data and access are checked separately through SQL and local HTTP. No live record was created, approved, recommended, or printed. Existing workflow authorization regression tests passed, but physical printing and external push delivery were not exercised.
- No GitHub push was performed.

## Export Report enhancement

The filter toolbar now includes **Export Report** with a download icon. It downloads all matching records using the filters and sort captured when clicked. The current pagination page/size do not limit the download. CSV is the only format implemented by the existing Reports Module; no separate XLSX/PDF generator or dependency was added.

`includes/gas_slip_report.php` extracts the existing gas-slip Reports Module SELECT, CSV header/row formatting, UTF-8 BOM, response headers and `gas_slips_YYYYmmdd_HHMMSS.csv` naming into shared functions. `exports/export_gas_slips.php` calls those functions with its original date/department filters and column order. Monitoring calls the same functions with its existing prepared filter/authorization predicate and stable selected sorting. Its first eight columns are Gas Slip No., Date Issued, Department, Area, Requested By, Purpose, Routes, Status; the existing report's additional validity, vehicle, fuel, recommendation, approval, rejection and printing fields follow. Dates keep the Reports Module's raw database formatting. The original CSV has no separate title or report-heading rows to preserve.

Monitoring exports run in a read-only consistent snapshot, fetch 500 slips at a time and prepare a temporary stream before sending download headers. This permits header-only empty downloads and normal sanitized error responses before a download starts. Multiple route/fuel relationships do not multiply rows. Monitoring's batched route loader avoids truncation from the report query's GROUP_CONCAT length limit and preserves every linked route. All monitoring cells are checked for dangerous spreadsheet formula prefixes (including leading whitespace/BOM) and escaped as text. Legacy Reports exports retain their established output and behavior.

The button uses a separate fetch and browser download, with a disabled/loading state while preparing and a retryable message on failure. It does not navigate, reset filters, alter sorting/pagination, or stop the auto-refresh timer. Every Monitoring-authorized user can export organization-wide Approved/Printed records matching applied filters. Normal Reports Module permissions are unchanged.

Modified for this enhancement: `monitoring.php`, `includes/monitoring.php`, `assets/js/monitoring.js`, `exports/export_gas_slips.php`, `tests/monitoring.php`, `tests/monitoring_http.php`, `tests/monitoring_ui.cjs`, and this document. New shared file: `includes/gas_slip_report.php`.

Export verification adds required/additional headers, BOM, filtering/count reconciliation, allowed statuses, route duplication prevention, all-rows export beyond both UI pagination and the 500-row query batch, sorting, permission checks for every current actor, empty output, formula-injection protection, authenticated download headers and invalid/anonymous request rejection. Browser checks exercise download naming and content, current filters/sort, pagination preservation, export failure recovery, and subsequent auto-refresh. The legacy CSV was compared byte-for-byte with the pre-refactor implementation for an admin and two department scopes; all matched. Existing Reports date filtering, permissions and all other report types were left intact. Large downloads use a temporary server file and a browser Blob; production-volume resource usage was not benchmarked.

## Compact toolbar and filter dialog

Search, Filters, Reset Filters and Export Report remain visible above the records table. The existing year/date/department/area/status controls now live in a native dialog styled with the monitoring details dialog's existing header, buttons and backdrop. Native dialog behavior supplies focus trapping, Escape dismissal and modal background blocking. Focus returns to Filters after Apply, Cancel, or Close. The six controls are not duplicated.

Opening the dialog restores the currently applied values. Edits remain a draft until Apply Filters validates the date range and commits them, preserving search and resetting only the current page as before. Cancel, X and Escape discard drafts. Department changes rebuild the draft area choices. An active-filter count shows non-default year/date/department/area/status values, including graph selections. Reset clears search, all applied filters and graph selection, and restores the existing sorting, page size and page defaults. Export and polling use the same applied filter snapshot plus the visible search, so uncommitted dialog changes cannot affect either. Background refresh does not overwrite open dialog controls.

The manual Refresh button was removed after verifying the initialized 60,000 ms idle timer matches `dashboard.php`, asynchronously updates counts/chart/table, preserves filters/search/sort/page/collapse state, pauses while details are open, resumes on close, and recovers automatically after a failed request. The toolbar now shows Updating, Auto-refresh active, or Connection issue with automatic retry; it also indicates suspension while viewing details or hiding the tab. Last Updated changes only after a successful data response. The native filter dialog does not suspend polling; current applied filters continue to be used while editing.

Only monitoring markup, JavaScript, CSS, browser tests and these notes changed for the compact-toolbar enhancement. PHP syntax/JavaScript syntax and 13 read-only HTTP checks passed. Browser tests cover Apply/Cancel/X/Escape, draft isolation during automatic refresh, invalid date rejection, focus trapping/restoration, department-area dependencies, badge/reset behavior, preserved search/export, overview expansion and chart selection, pagination/sort preservation, hidden-tab suspension, details pause/resume, automatic failure recovery, no manual Refresh button, and mobile/desktop layouts. No SQL, endpoint, authentication, Reports Module, Admin Dashboard or workflow changes were needed.


## Department-only Monitoring Module Access

Repository and configured-database inspection found no existing Monitoring Module Access card, module enable flag, department-access table, or saved monitoring selections. The active Settings link points to `settings.php`; the unrelated legacy `admin_settings.php` was left unchanged. The new **Monitoring Module Access** card appears only for current database administrators. It contains department-master checkboxes and **Save Changes**, with no global enable switch.

Department IDs are saved as a JSON list in the existing `system_settings` row keyed `monitoring_departments`. Reloading Settings restores the saved checkmarks. Empty or missing selections mean administrators only. No production department selections were supplied or preselected. No table or column was created/dropped, and other system settings are unchanged. Any legacy `monitoring_enabled` row, if present in another deployment, is ignored and retained. The existing VARCHAR(255) storage accommodates the current nine departments; oversized selections are rejected rather than truncated.

`canAccessMonitoring()` resolves active status, role and department from the current users table on every request, bypasses department selection for administrators and fails closed for other users if settings cannot be read. It does not trust cached session roles/departments. The guard applies before rendering `monitoring.php` and before every data/options/detail/export action, returning 403 without a download for denied sessions. Both sidebar variants use the same function to decide whether to render Monitoring. Selection changes, user department changes, and role changes apply to subsequent requests without another login. Monitoring-specific visibility now covers every Approved/Printed slip; entry does not grant access to other statuses or operational actions. Shared non-monitoring gas-slip detail/workflow endpoints retain their original authorization.

`save_monitoring_access.php` requires an authenticated POST, a session CSRF token and current database administrator privileges. It validates and deduplicates IDs against the department master and atomically replaces only this setting. Settings uses the existing success-message/redirect convention.

Changed for this access feature: `settings.php`, `monitoring.php`, `includes/sidebar.php`, `includes/admin_sidebar.php`, the monitoring browser test's asynchronous Escape/Close waits, and these notes. Added `includes/monitoring_access.php`, `includes/settings/cards/monitoring_access_card.php`, `save_monitoring_access.php`, and `tests/monitoring_access.php`.

Verification: `php tests/monitoring_access.php` passed 40 isolated database/HTTP checks, including dynamic checkboxes, persistence, multiple/empty selections, current-role-only saves, CSRF, missing/ignored global settings, navigation, direct page/AJAX/details/export denial, record-scope preservation, same-session grant/revocation, and live department changes. It creates a disposable database and HTTP sandbox and removes them; application records/settings are not modified. Existing 170 monitoring checks, 13 local HTTP checks, 43 gas-slip authorization checks, 39 area-assignment checks and the full monitoring browser suite also passed. All changed PHP files passed syntax checks. No GitHub push was performed.


## Management navigation and global read-only visibility

Monitoring now appears once under Management, after Routes and before Reports. The current sidebar retains Accounts ? Fuel Items ? Vehicles ? Routes ? Monitoring ? Reports ? Settings order among links the user may see. The legacy alternate sidebar also orders its existing management links this way, preserving its individual link permission checks. The Monitoring icon, active class, markup and responsive styling are preserved. Its module guard remains independent of operational role flags.

SQL changes are confined to `includes/monitoring.php`. The shared filter predicate accepts the result of `canAccessMonitoring()` rather than using `gasSlipPermissionSql('view')`. Counts, table pagination, search, graph and export use that same global Approved/Printed dataset. Selected Department/Area filters still narrow cards/table/export. The graph continues omitting those selections to preserve distribution context, while retaining year/date/status/search. All authorized users receive full department, related area and reporting-year choices. Details independently require Monitoring admission and Approved/Printed status, without importing operational view/edit/action permissions. Unauthorized direct helper calls return empty datasets/options/details; the public endpoint still responds 403 before returning data or starting a download.

Existing polling, modal filter drafts, search, collapse state, sorting, pagination and export presentation are unchanged. There is no operational or normal Reports access-policy modification, and no settings or schema migration. No database records or access selections were changed to perform the live checks.

Modified for this enhancement: `includes/monitoring.php`, `monitoring.php` (global-visibility explanatory text), `includes/sidebar.php`, `includes/admin_sidebar.php`, `tests/monitoring.php`, `tests/monitoring_access.php`, `tests/monitoring_http.php`, and these notes.

Verification includes the configured active ISD recommender (user 24, department 6), whose department was already selected in Monitoring settings. Run `php tests/monitoring_http.php http://localhost/gslip/monitoring.php isd` for the read-only live check. Its 16 checks confirm global totals, cross-department Monitoring details, scoped export reconciliation, and rejection of that same foreign slip by the operational details endpoint. The isolated suite also tests ISD global graph/options/records, Printed classification, foreign department/area/search filters, export contents, repeated refresh requests, and unchanged operational view/recommend/approve denial. Requester/approver/recommender access uses the same module-entry rule, without role-specific visibility branches. The source database is untouched; temporary classification/settings changes occur only in the disposable test database.


## Native application layout

`monitoring.php` now includes the same `includes/sidebar.php`, Bootstrap, stylesheet, dark-mode preload and `app-ui.js` used by Pending/Recommended/Approved. It uses `.app-content > .main-content > .panel-container` and the native `.page-header` heading/subtitle. Those reference pages have no separate reusable top-header component; the existing sidebar brand/header and content heading are retained rather than inventing another header. The standalone main wrapper and Back to Dashboard heading link were removed. The route and early JSON/download handlers remain unchanged, with authorization completed before sidebar/profile queries are rendered.

Monitoring component CSS is scoped to `.monitoring-content`, including its native filter/details dialogs. It no longer resets document fonts, body spacing, general headings, buttons, links or tables outside Monitoring, so shared sidebar controls, profile modal, branding, icons and dark/mobile navigation retain their application styling. Both monitoring dialogs live within the content scope. Shared sidebar includes already load the existing mobile navigation, dark mode and tooltip code; Monitoring adds the standard Bootstrap/SweetAlert/app UI scripts without copying navigation markup or using an iframe.

A ResizeObserver on the chart container calls resize/update on the existing Chart.js instance during sidebar width changes and viewport changes. It also handles restored collapse preferences. Overview collapse still hides the same cards/chart and refresh continues to update their data. No extra data polling timer or chart instance is created.

Files modified for layout integration: `monitoring.php`, `assets/css/monitoring.css`, `assets/js/monitoring.js`, `tests/monitoring_ui.cjs` and these notes. No shared layout/navigation source, operational page, database schema, data query or access rule changed.

Verification: the browser suite confirms one shared sidebar/heading/wrapper, active Monitoring under Management, desktop sidebar collapse/expand with changing chart width and one chart instance, existing mobile menu open/close, navigation to Pending, all monitoring filters/search/sort/page/details/export/overview behaviors, 60-second refresh/failure retry and desktop/mobile overflow. Native desktop/mobile screenshots were inspected. The 54 isolated module-access checks and 16 live ISD recommender HTTP checks passed, including global data and operational foreign-record denial. PHP/JavaScript syntax and whitespace checks passed. No GitHub push was performed.
