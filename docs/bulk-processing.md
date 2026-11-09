# Page-specific gas slip groups and bulk processing

Completed recommendations now enforce independent approval using persisted `recommended_by`, including bulk eligibility/counts and per-record execution. Current recommender-only area settings do not invalidate already-recommended requests. See [approval separation and stuck-record fix](approval-separation.md) for the live read-only findings, compatibility routes and regression coverage.

## Current behavior

All three gas slip pages reuse the account's private Requested By group definitions. Names are normalized by trimming, collapsing whitespace and comparing lowercase UTF-8 values. New matching slips join dynamically. Group tabs remain visible when empty; tabs scroll horizontally without vertical scrolling.

| Page | Groups | New processing controls |
| --- | --- | --- |
| Pending Gas Slips | Filter the authorized action queue and scope bulk actions | Checkboxes, Select All, automatic Recommend/Approve, group-only Reject |
| Recommended Gas Slips | Filter the original recommended historical list | None |
| Approved Gas Slips | Filter the original approved historical list | None |

There is no Workflow dropdown. The backend resolves the primary label from current database assignments: Recommend for recommenders, Approve for approvers. For multi-role accounts and private vehicles, each record keeps its own authorized recommendation or approval step. A private pending slip cannot skip recommendation just because the account's main button says Approve.

Recommended and Approved retain their original visibility queries, statuses, sorting and pagination. Approved also retains its original search, date/printed filters, and printing controls. Those existing printing checkboxes are separate from bulk workflow selection and are unchanged. Group conditions are added to the original count and list scopes; historical groups never substitute the actionable pending queue.

## Group editor

Choose **Manage Groups** to see three sections: **Create a new group**, **Your groups**, and **Selected group details**. Create is disabled for an empty name, and a successfully created group becomes selected automatically. Compact pills show account-owned groups and configured name counts; only the selected group has an editor. Rename opens an inline Save/Cancel form. The delete icon opens an inline, theme-aware confirmation explicitly stating that gas slips are kept; Cancel never sends a deletion request.

Configured Requested By names appear as removable chips with explicit remove labels and feedback. The add input supports manual names and debounced accessible suggestions from existing visible gas slips' `requested_by` values. Arrow Up/Down selects a suggestion, Enter adds it, and Escape dismisses the list. Successful additions clear/focus the input and update chips/counts; duplicate errors remain visible without losing entered text. A name may belong to only one group per owner, while different owners may use the same name. Requests reuse the existing API operations, with repeated mutations disabled while saving. Closing after changing the active group's names reloads that table's filter; deleting or emptying the active group returns to All Gas Slips.

The editor is 660px wide on desktop, constrained to the viewport, with internal scrolling as needed. Group pills wrap on desktop and scroll horizontally on narrow screens, keeping selected details nearby. Small screens stack input and action rows. Action labels do not shrink or wrap vertically. Light/dark colors, native button/checkbox keyboard access and visible focus indicators follow the application theme.

## Pending actions and safeguards

Pending shows bulk actions and a narrow checkbox column immediately before No. only when the active tab has at least two eligible records across all pages. At zero or one eligible record, the action area and the entire selection column are hidden, including the header cell; No. becomes the first visible column. The initial HTML also hides these controls until eligibility loads. Its header Select All covers the full eligible group across pagination; partial selections show an indeterminate header checkbox and change the action button to Selected. Switching groups clears selection. An eligible-set refresh that drops below two clears stored selection and hides controls; increasing back to two restores controls without selecting records automatically. Empty-state colspans track the visible columns. Approved printing checkboxes are unaffected. Checkbox clicks and keyboard events do not open details; the original row number and other data cells retain row-click details. Exclusions are no longer consulted: every currently authorized, status-eligible record participates normally.

All three pages use one compact navigation row: tabs with neutral count badges and an icon-only **Manage Groups** button immediately after them on the left, page-specific actions on the right. The page header retains only its title and subtitle. Manage Groups has a native tooltip, accessible name and users icon; there is exactly one control per page, including when no custom groups exist. Overflow navigation scrolls horizontally with keyboard-accessible controls; the selected tab returns into view after refresh. Actions may wrap below on narrow screens.

Pending retains its eligibility counts, Recommend/Approve controls and group-only Reject action. Recommended has visible-record counts and no processing actions. Approved has visible-record counts, with the original Print Selected button followed by Print Settings on the right of the same row; the standalone print toolbar is removed. Printing handlers, print checkboxes and the original settings modal remain unchanged. The common tab, badge, button and icon styling follows both themes.

Counts come from the existing `state` API across all pages and include records across pagination. Pending counts use the full permission-checked eligible set. Recommended/Approved counts use their original visible historical record scopes, including status, role/department/area visibility and (for Approved) current search, date and printed-record filters. Normalized account-owned Requested By matching supplies each group's count, independent of the active tab. State reloads refresh counts after relevant group/record changes. Historical pages remain view-only; their only selection controls are Approved's existing printing checkboxes. No database or workflow change is required.

All-tab All actions require acknowledgment and the exact phrase `RECOMMEND ALL` or `APPROVE ALL`, including when every checkbox is selected. Custom-group recommendation/approval use standard confirmation without a typed phrase. Bulk Reject exists only inside groups and requires explicit selection and a shared reason; selecting every eligible record additionally requires acknowledgment. Confirmations use the exact server-validated affected count and snapshot without displaying individual gas slip lists.

The All dialogs use the supplied expanded titles, descriptions and cautionary warnings in two short paragraphs. All Gas Slips warnings explicitly include records across all pages. The rounded warning panel follows light/dark mode automatically; the dialog grows naturally with an internal scrolling body when needed on small screens. Confirmation requirements, buttons and processing are unchanged. These presentation changes are in `assets/js/bulk-processing.js`, `assets/css/bulk-processing.css`, `tests/bulk_processing_ui.cjs` and this document.

Selected actions use that same shared warning-icon dialog and theme-aware panel, with the supplied Selected title, affected count, heading and two-paragraph warning. Their buttons read `Confirm Recommend (N)`, `Confirm Approve (N)` or `Confirm Reject (N)`. One-record bulk batches are not allowed; use the unchanged individual details for those requests. Selecting one record within a larger tab keeps the bulk buttons disabled until at least two records are selected. All supported multi-record actions require acknowledgment. The former `s.all` rendering guard caused Selected warnings to be missing; it has been removed without changing snapshots or backend rules. Browser regressions cover all three Selected dialogs, absent question-mark icons/record lists, light/dark panel colors, rejection reason validation and unchanged All safeguards.

Bulk typography follows the unchanged individual **Reject Gas Slip?** SweetAlert: the application's Roboto/Segoe UI font stack, 30px/600 title, 18px body, 16px labels and 16px/500 buttons. Warning panels inherit body typography instead of overriding it with 14px text. Bulk rejection uses SweetAlert's existing `swal2-textarea` styles with scoped font inheritance (18px text, 27px line height), preserving the reference padding, border, radius and focus appearance. Width/margin are adapted only to fit the bulk dialog's scrollable body. No individual-modal or global typography rule was changed. The browser suite compares actual computed styles against the original individual Reject configuration for both bulk rejection modes in both themes, including typed text and focused/unfocused input styling.

Warning sections now have transparent backgrounds, no colored border or shadow, and inherit the modal's theme-aware text color. Headings use centered uppercase text at 18px, weight 700, line height 1.4 and an 8px bottom margin. Main titles, paragraph typography and rejection textareas retain their existing sizes. Message paragraphs are justified with a natural final line; at 400px and below they align left to avoid excessive gaps. Titles remain centered; redundant introductory descriptions are omitted. Exact affected counts remain in the warning paragraphs and confirmation buttons. Message paragraphs, font sizes, textarea styling, icons, buttons and confirmation rules are unchanged. This appearance adjustment touches only the bulk JavaScript/CSS, browser regressions and this documentation.

Every bulk action affecting two or more snapshot IDs requires the same neutral bordered acknowledgment card. Its heading is ?I understand the consequences?; its action-specific description states that all N affected slips will be recommended, approved, or rejected using the same reason, including records not individually reviewed. The card has an 18px checkbox, 12px gap, left-aligned 16px bold heading/14px description, rounded border, theme-aware colors and keyboard focus. The entire card is clickable. Bulk batches must contain at least two records. Reject All no longer uses the old inline sentence.

Typed confirmation remains exclusive to All Gas Slips Recommend All / Approve All, when bulk processing is available. Custom Group All and Selected actions never gain typed confirmation. Reject actions always retain the mandatory shared reason. Acknowledgment requirements are enforced both in the UI and in server confirmation validation using the actual snapshot ID count, independent of supplied counts or labels. The minimum size is enforced at snapshot creation and final submission. Authorization, workflow eligibility, transitions, notifications and individual processing remain unchanged. Browser/PHP regressions cover all three multi-record Selected actions, custom-group All actions, minimum eligible/batch counts, forged client counts, rejection reasons, both themes and the existing typed-phrase safeguards.

Snapshots expire after ten minutes and are single-use. Final execution revalidates status, current per-record workflow, authorization, group membership and Requested By. The snapshot also records the exact eligible ID set. Any change to that set while confirmation is open invalidates it and final submission returns a refresh-required error before processing. Newly eligible records never enter an existing snapshot. Once a validated batch starts, per-record revalidation and independent transactions remain in place; subsequent per-record races are safely skipped and reported. Notification failures roll back only the affected record. Competing snapshots cannot process a changed workflow status twice.

Page-specific CSRF tokens separate pending actions from historical filtering. Recommended/Approved contexts reject snapshot, validation and processing requests; their tokens cannot be relabeled as Pending. Current authentication, database assignments and record permissions are still checked independently of frontend labels.

## Processing results

Normal success dialogs use the action-specific Recommended/Approved/Rejected Successfully title, success checkmark, a short count-based message and OK. Counts come from the backend's `successful` array, with singular/plural grammar. There is no visible Successful/Skipped/Failed breakdown or expandable record list. If any records are skipped, failed or missing from the confirmed batch's reported outcomes, the dialog instead warns that some gas slips could not be processed and shows the successful count out of the batch total. With no successes, it shows an action-specific error and asks the user to refresh and try again.

Backend summary arrays, detailed response reasons and existing server logs remain unchanged as internal diagnostics; result dialogs do not expose technical details. After OK, the existing refresh reloads the current page/group, clears selection, updates the table/counts and recalculates bulk-control visibility. Browser tests exercise all nine action/outcome combinations, singular/plural formatting, both themes and post-result refresh. This presentation correction changes only `assets/js/bulk-processing.js`, `tests/bulk_processing_ui.cjs` and this document.

## Removed Individual Review feature

There are no bookmark buttons, review tooltips or exclusion-related visual states. The exclusion endpoint no longer accepts changes, and bulk queries no longer read `bulk_processing_exclusions`. The table and stored data are retained unchanged for a separately authorized cleanup migration. This correction does not change any application database table.

## Individual processing

All pages retain the original details modal and individual Recommend, Approve and Reject controls, validations, confirmations, timestamps, authorization and notification helpers. Clicking table data still opens the same details. Pending checkbox interactions do not open details. No individual endpoint was changed by this page-specific correction.

## Files changed in this correction

- `pending_gas_slips.php`
- `recommended_gas_slips.php`
- `approved_slips.php`
- `includes/bulk_processing_ui.php`
- `includes/bulk_processing.php`
- `includes/bulk_table_query.php`
- `bulk_processing.php`
- `assets/js/bulk-processing.js`
- `assets/css/bulk-processing.css`
- `tests/gas_slip_access.php`
- `tests/bulk_processing_cases.php`
- `tests/bulk_processing_ui.cjs`
- `docs/bulk-processing.md`

## Validation

```powershell
php tests/bulk_processing.php
$env:GSLIP_UI_PLAYWRIGHT = 'path\to\external\node_modules\playwright'
node tests/bulk_processing_ui.cjs
```

The backend suite uses an isolated temporary MySQL database and real HTTP endpoints. It first runs the original individual access/recommendation/approval regressions, then tests private groups, normalized dynamic membership, autocomplete, legacy exclusions being ignored without deletion, page-specific action rejection, automatic private and multi-role workflows, strong confirmations, notifications, rollback, competing snapshots, historical group filters, and individual rejection.

The browser suite uses real application JavaScript, Bootstrap and SweetAlert with deterministic HTTP fixtures. It checks selection across pages, original details, clean row numbers, automatic action labels, absence of history-page bulk controls, group navigation, autocomplete, confirmation safeguards and responsive layouts at 1280/768/390/320 pixels. It also checks modal width, usable input widths, single-line action labels, internal scrolling and retained table colors/badges. Desktop and mobile screenshots are visually reviewed.

These suites and relevant syntax checks were executed successfully on November 6, 2026. Browser coverage uses fixtures rather than live production records; competing-snapshot coverage is not a multiserver load test. No GitHub push was performed.

## Pending initialization JSON fix

The failing request was `POST bulk_processing.php`, operation `state`, page `pending`. An authenticated local request reproduced HTTP 200 with this prefix before the JSON object:

```text
<br />
<b>Warning</b>: Undefined variable $includeExcluded ... includes/bulk_processing.php ... line 68
```

When exclusion handling was removed, `bulkRecords()` retained `$includeExcluded` in the filter closure's `use` list. Removing that obsolete capture fixes the warning. The actual local group tables were verified to exist (one group and one configured name); this was not a missing migration. No schema change was made.

The API now runs its own buffered JSON response/error boundary, logs detailed unexpected failures, returns sanitized JSON with appropriate statuses, and prevents warning/HTML contamination. Unexpected warnings fail the operation rather than silently proceeding. Includes/session startup also run inside that boundary. Client requests validate status, content type and JSON shape. Failed initialization shows a readable message and Retry, stops Loading, and disables processing without claiming a zero eligible count. The Retry handler is attached once and permits only one initialization request at a time.

Verification included the actual authenticated local Recommender Pending page: HTTP 200 clean JSON, Recommend, three eligible records, the existing custom group, group switching, strong confirmation, original row details and no browser runtime errors. An active local Approver browser session was not available; authenticated Approver initialization, private/multi-role workflows and individual actions were exercised by the isolated real-HTTP regression suite, and approver browser behavior by the deterministic browser suite. Tests also inject PHP warnings and SQL faults with display_errors enabled, and exercise malformed JSON, HTML, HTTP failures, network failures and a single-request Retry. No gas slip was processed during live-page verification.

Files changed for this targeted fix: `includes/bulk_processing.php`, `bulk_processing.php`, `includes/bulk_processing_ui.php`, `assets/js/bulk-processing.js`, `tests/bulk_processing_cases.php`, `tests/bulk_processing_ui.cjs`, and this document. Individual endpoints and workflow rules remain unchanged.

## Existing installation migration

Existing installations with the group tables need no new migration for this correction. For a fresh installation, the earlier additive migration remains available:

```powershell
$env:GSLIP_MIGRATION_DATABASE = 'your_database_copy'
php migrations/20261106_bulk_processing.php
php migrations/20261106_bulk_processing.php --apply
```

Preflight is read-only by default and checks signed INT IDs and InnoDB. Validate against a database copy before deployment. The original migration is repeatable and retains all existing gas slips and configuration. It creates group/name tables and the now-unused legacy exclusions table; this correction neither drops that table nor deletes its data.
