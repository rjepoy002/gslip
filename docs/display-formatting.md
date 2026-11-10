# Listing and export display formatting

Requested By uses Unicode uppercase. Purpose and route/location values use
Unicode proper case, with lowercase connecting words (`at`, `of`, `in`, `on`,
`to`, `for`, `and`, `the`) except at the beginning. Whitespace, arrows, punctuation,
hyphens, apostrophes and route ordering remain intact. Null values become an empty
string, leaving each view's existing placeholder handling in control.

Shared helpers are in `includes/display_format.php`: `formatRequestedBy`,
`formatPurpose`, and `formatRoute`. The proper-case implementation preserves an
explicit acronym list (including ICT, GPS, PALECO and existing department names)
and numeric/alphanumeric route codes. Unknown words are proper-cased; additional
special acronyms can be added to this one shared list when needed.

## Covered presentation paths

- Pending, Recommended, Approved/Printed, Draft and their routable dated copies.
- Dashboard recent slips and route tables, including legacy dashboard variants.
- The legacy main listing and visible route-management cells.
- Monitoring's dynamically refreshed table, including filtered/paginated rows.
- Reports gas-slip and route-utilization tables.
- Existing Reports/Monitoring gas-slip CSV and route-utilization CSV exports,
  including the older routable gas-slip CSV endpoint.
- Bulk/group-filtered slips, which use the same Pending/Recommended/Approved rows.

No new columns, pages or export formats were introduced. No standalone Excel/PDF
export generator for these fields was found; existing CSV exports retain their
BOM/header layout and Monitoring spreadsheet-formula protection.

PHP table views format before their existing HTML escaping. Monitoring adds a
`display` object next to unchanged raw fields; JavaScript uses `textContent` to
render it. The shared Reports CSV value builder formats a local array of cells,
never the source record. Search, sort, permission, group membership and report
calculation queries are unchanged. Raw group-editor names and form inputs remain
unchanged. Tests confirm case-insensitive search against the existing schema.

## Exclusions and data safety

Printable gas slip documents, creation/editing form inputs and saved values are
untouched. The later modal extension below supersedes the original exclusion of
the Gas Slip Details modal. Route edit `data-*` attributes keep the
original values; only their visible listing cells are formatted. Files under
`gsis_backup` and the inactive `create_gas_slip - backup.php` are backup artifacts,
not application listing paths, and were not edited. No other active applicable
listing/export path identified during inspection was left unformatted.

No application schema, setting, stored text, migration, approval/recommendation,
printing, route calculation or notification behavior was changed. Tests use the
existing disposable database-copy suites; no configured application data is
modified. No commit or push is performed.

## Validation

- `php tests/display_format.php`: 53 checks for the provided examples, Unicode
  names, connecting words, punctuation, acronyms/codes, null/empty values,
  idempotence, HTML escaping and both CSV value layouts.
- `php tests/monitoring.php`: 183 checks, including raw/display JSON separation,
  case-insensitive filtered search, formatted CSV and unchanged stored values.
- `php tests/monitoring_access.php`: 75 access checks, including unchanged original
  detail-modal markup and permissions.
- Existing bulk-processing/workflow suite passes.
- Monitoring browser suite passes with formatted AJAX display fields, escaped
  text, refreshed rows, row clicks/keyboard navigation, filtering, sorting,
  pagination, exports and mobile layout.
- PHP syntax checks for all touched PHP files and whitespace checks pass.

## Changed files

- `approved_slips.php`
- `approved_slips_05292026.php`
- `approved_slips_06032026.php`
- `approved_slips_07132026.php`
- `approved_slips_09022026.php`
- `assets/js/monitoring.js`
- `dashboard_05282026_2datachart.php`
- `dashboard_06052026.php`
- `dashboard_06052026_v2.php`
- `draft_gas_slips.php`
- `draft_gas_slips_05222026.php`
- `draft_gas_slips_05292026.php`
- `draft_gas_slips_06102026.php`
- `exports/export_gas_slips_07062026.php`
- `exports/export_route_utilization.php`
- `includes/dashboard/charts.php`
- `includes/dashboard/charts_06252026.php`
- `includes/gas_slip_report.php`
- `includes/monitoring.php`
- `main.php`
- `pending_gas_slips.php`
- `pending_gas_slips_05212026.php`
- `pending_gas_slips_05292026.php`
- `pending_gas_slips_06032026.php`
- `pending_gas_slips_07132026.php`
- `recommended_gas_slips.php`
- `recommended_gas_slips_05292026.php`
- `reports/report_gas_slips.php`
- `reports/report_route_utilization.php`
- `routes.php`
- `tests/gas_slip_access.php`
- `tests/monitoring.php`
- `tests/monitoring_access.php`
- `tests/monitoring_ui.cjs`
- `includes/display_format.php`
- `tests/display_format.php`
- `docs/display-formatting.md`

## View Gas Slip Details extension

The shared `view_gas_slip_modal.php` renderer now imports the same presentation
helpers. Requested By and the Created timeline name use Unicode uppercase.
Recommended, Approved and Rejected personnel names use that same helper instead
of ASCII-only `strtoupper`. Purpose and Destination use proper case before their
existing escaping/newline rendering. Brackets, route separators, punctuation,
location characters and known acronyms are preserved. Fuel labels, reasons,
dates, status badges, modal structure, action controls and permissions are not
changed.

All pages loading the shared modal body inherit this extension, including
Pending, Recommended, Approved/Printed, Draft, Dashboard and Monitoring. The
header JSON retains its raw fields; only rendered HTML is formatted. No stored
values, schema or printable document is changed. Existing and future records
are formatted identically when opened.

Files changed for this extension: `view_gas_slip_modal.php`,
`tests/monitoring_access.php`, `tests/monitoring_ui.cjs`, and this document.

Validation: 119 real-HTTP Monitoring/access checks pass, including uppercase
Unicode requester/Created names, proper purpose with acronyms/newlines, normal
and read-only contexts, unchanged header JSON/stored values and original action
availability. The real browser suite dynamically opens Pending, Recommended,
Approved, Printed and Rejected bodies and checks Unicode personnel, purpose,
bracketed routes, arrows, acronyms, fuel tables, mobile layout and the existing
row-click/keyboard/filter/export behavior. Shared formatting unit tests (53)
and existing bulk/workflow regressions pass; syntax and whitespace checks pass.
No commit or push was made.
