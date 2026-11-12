# Inspection: Missing Creator-Owned Approved Gas Slips

Inspected November 12, 2026 (Asia/Manila), in the current local working tree, including the uncommitted Pending visibility correction. **Inspection only; no fix implemented.**

## A. Executive Summary

**Both reported bugs have the same confirmed cause:** when `is_recommender` is true, `approved_slips.php` requires `gs.recommended_by = current_user_id` in both the count and row query. It does not also admit `gs.user_id = current_user_id`. The existing shared view policy already permits an active creator to view their record, but that permission is an additional AND condition and cannot override the restrictive author predicate.

- Private slip: `user_id=A`, `recommended_by=department approver`, `approved_by=global private approver`. A fails the recommender list's author test.
- Coop slip assigned to B: `user_id=A`, `recommended_by=B`, `approved_by=department approver`. A fails the same test.
- Self-recommended and self-executed recommender-only slips pass because `recommended_by=A`.
- Printed creator-owned slips recommended by someone else fail the same test, even with Include Printed enabled.

Executed read-only SQL using the actual count and row query strings extracted from the current page, with synthetic CTE fixtures. A's Approved page returned only the self-recommended and automatic-approval fixtures, while the shared view permission admitted all six of A's approved/printed fixtures. Both defects are confirmed at query level. No browser session, workflow mutation or production record change was performed.

The minimum correction would add guarded creator ownership alongside existing recommendation authorship in a shared Approved display helper and reuse it in the page, relevant badge and history-group counts. Existing recommendation, approval, rejection, print and bulk-action permissions can remain unchanged. **No database change is needed.**

## B. Files and Functions Inspected

| Current path and line | Responsibility |
|---|---|
| `approved_slips.php:14`, `:36`, `:62` | Session guard, capability branches, shared view/group filter |
| `approved_slips.php:138`, `:148`, `:196` | Recommender branch, count and row author predicates |
| `approved_slips.php:227`, `:337`, `:406` | Approver queries and normal-user ownership branch |
| `approved_slips.php:73`, `:87`, `:95`, `:108` | Printed timestamp, approval-date and search predicates |
| `approved_slips.php:581` | Print checkbox uses status and Print Once setting |
| `includes/pagination_setup.php:2` | 15 rows per page, page and offset |
| `save_filters.php:23`, `:27`, `:33`, `:37` | Stores Include Printed, search and dates in session |
| `includes/gas_slip_access.php:6`, `:14`, `:44`, `:60`, `:71` | Independent approval, shared permissions/view, single-record check, corrected Pending scope |
| `includes/pending/query.php:32` | Shared Pending display helper used for count/rows |
| `includes/sidebar.php:74`, `:91`, `:98`, `:115` | Shared view scope, restrictive Approved badge, corrected Pending badge |
| `includes/dashboard/cards.php:95`, `:102` | Approved card displays sidebar `$counts['approved']` |
| `includes/dashboard/counts.php:22`, `:29`, `:122` | Shared view scope, graph statuses, recommender total |
| `includes/dashboard/approved.php:22`, `:29`, `:67` | Monthly approved graph scope |
| `includes/dashboard/recent.php:74`, `:80` | Shared view plus approved/printed for recent list |
| `includes/bulk_processing.php:39`, `:50`, `:54`, `:90`, `:101` | Group filtering, action eligibility, history records/count author restriction |
| `bulk_processing.php:69`, `:85` | Approved context is group-filter-only; history records feed tab counts |
| `includes/bulk_processing_ui.php:16`, `assets/js/bulk-processing.js:64` | History/group controls and All Gas Slips tab |
| `view_gas_slip_modal.php:70`, `:86`, `:147`, `:174`, `:502`, `:563` | Detail authorization, labels and action-specific buttons |
| `assets/js/gas-slip.js:37`, `:52`, `:97`, `:115` | JSON/body modal requests and current action endpoints |
| `includes/gas_slip_workflow.php:309`, `:377`, `:427`, `:486` | Automatic approval, recommendation and independent approval updates |
| `includes/gas_slip_rejection.php:3` | Server-side rejection permission and status update |
| `includes/print_access.php:7` | `canAccessPrintGasSlip`: separate relationship permission |
| `print_gas_slip.php:14`, `:107`, `:116` | Print session, status/Print Once and relationship checks |
| `update_printed_at.php:18`, `:49`, `:79`, `:95` | Print update guard, locked record, permission and state change |
| `draft_gas_slips.php:34`, `recommended_gas_slips.php:45` | Draft owner query and separate recommendation-author history |
| `docs/recommender-pending-visibility-fix.md`, `docs/recommender-workflow-inspection.md` | Previous correction and separate security findings, read as context |

## C. Current Approved Visibility SQL

The recommender branch runs before the approver branch. A user with both session capabilities therefore gets the recommender list restriction too.

Actual recommender count query, with PHP interpolation left visible:

```sql
SELECT COUNT(*) AS total
FROM gas_slips gs
WHERE gs.recommended_by = ?
AND gs.status IN ('approved', 'printed')
{$whereExtra}
```

Actual recommender records query:

```sql
SELECT gs.id AS gs_id, gs.gas_slip_id, gs.date_issued,
       gs.requested_by, gs.purpose, gs.status, gs.printed_at, gs.approved_at,
       CONCAT(MIN(r.origin), ' → ',
         GROUP_CONCAT(r.destination ORDER BY gsr.id SEPARATOR ' → ')) AS route_path
FROM gas_slips gs
LEFT JOIN gas_slip_routes gsr ON gsr.gas_slip_id = gs.id
LEFT JOIN routes r ON r.id = gsr.route_id
WHERE gs.recommended_by = ?
AND gs.status IN ('approved', 'printed')
{$whereExtra}
GROUP BY gs.id
ORDER BY gs.date_issued DESC
LIMIT ?, ?
```

The first parameter is the logged-in user ID; record parameters then include offset and page size. `whereExtra` begins with:

```php
$viewScope = gasSlipPermissionSql('view', $userId)
    . ' AND (' . bulkHistoryGroupSql($conn, (int)$userId) . ')';
$whereExtra = " AND $viewScope ";
```

The decisive exclusion is **`approved_slips.php:148` and `:196`: `gs.recommended_by = ?`**. No amount of matching search, group membership or creator-view permission can overcome that base condition. Count and row queries agree on the exclusion; this is not a pagination-only or count-only bug.

### Filters, groups and pagination

| View/filter | Actual effect |
|---|---|
| Default All Gas Slips | Group filter is `1=1`; restrictive recommender-author base query remains |
| Custom Requested By group | Owner-scoped group and normalized name match narrow view-authorized IDs; the page still ANDs the same author predicate |
| Search | Matches slip number, free-text Requested By, purpose, or related route origin/destination through EXISTS |
| Dates | Inclusive `DATE(gs.approved_at)` from/to; these are approval dates, not issuance dates |
| Include Printed | Defaults enabled; disabling adds `gs.printed_at IS NULL`, rather than removing the `printed` status from the status set |
| Department/area | No separate department/area selector in this recommender query; shared view policy supplies existing scope. Approver branches have their own department-area restrictions |
| Status | Fixed `approved`/`printed` set; no arbitrary status filter in this query |
| Pagination | Existing 15-row offset/limit after filtering and grouping, ordered by issuance date descending |
| History tab counts | `bulkHistoryCountRecords(...,'approved',...)` repeats the recommender author restriction at `includes/bulk_processing.php:101`, plus view, printed/date/search filters |

Custom history tabs are not Pending action queues. `bulk_processing.php:69` disallows workflow processing in Approved context. The page currently presents an “All Gas Slips” label over an author-history subset, rather than all shared-view-authorized or creator-owned records. Those categories are mixed in the UI meaning, although the implemented predicates are explicit.

## D. Private-Vehicle Workflow Findings

The existing private workflow remains correct in its routing: a creator submits a pending slip; a creator-department approver who is not the configured final private approver recommends; the configured global private approver with required department-approver membership performs independent final approval.

| Field | Persisted meaning after regular private approval |
|---|---|
| `gas_slips.user_id` | Creator A; recommendation/approval do not replace it |
| `vehicles.ownership` | `private`, joined through `gas_slips.vehicle_id`; no separate slip privacy flag |
| `gas_slips.status` | `approved` after final approval |
| `gas_slips.recommended_by` | Department approver who performed recommendation |
| `gas_slips.approved_by` | Global private approver who performed final approval |
| `gas_slips.approver_type` | `primary` or `secondary` from that final actor's department-approver membership; not necessarily the literal word “private” |
| `gas_slips.printed_at` | Not set by recommendation/approval; printing later sets it and status `printed` |

The Approved page incorrectly requires creator A to also be the recommendation author whenever A has recommender capability. The shared view condition recognizes A through actual `gs.user_id` and active database actor status, so detail-view permission is present even while the list hides the record.

Read-only fixture 1 reproduced this: A had `view=1`, all recommendation/approval/rejection permissions `0`, and no Approved list entry. Department approver D and global private approver P retained their Approved page entries under their existing approver branches. No routing correction is proposed.

## E. Coop-Owned Workflow Findings

For a coop slip A creates and assigns to B, recommendation stores B in `recommended_by`; regular approval stores the authorized department approver in `approved_by`. A remains `user_id`. The same author-only predicate excludes A.

Executed fixture 2 results: creator A had shared `view=1` but was absent from the actual Approved count/row queries. B's actual recommender query included the slip. Department approver D's actual approver query also included it.

Self-assigned/self-recommended fixture 3 was present for A because `recommended_by=A`. Recommender-only fixture 4, with `recommended_by=A`, `approved_by=A`, `approver_type='recommender'`, was also present. This is expected existing behavior. If B executes recommender-only approval for A's slip instead, the author predicate would still exclude A; the setting is not a creator visibility policy.

The regular and automatic workflow implementations were inspected, not executed this turn. Synthetic fixtures represent their persisted outcomes; this does not prove a new end-to-end create/recommend/approve run.

## F. Approved and Printed Status Handling

The previous Pending correction is present at `includes/gas_slip_access.php:71`: it retains authorized recommendation/approval and creator-rejected branches and adds creator-owned pending/recommended records guarded by the shared view policy.

| Stage | Current recommender creator visibility |
|---|---|
| Pending | Included in default Pending by guarded creator branch |
| Recommended | Included in default Pending by guarded creator branch; separate Recommended history page still requires recommendation authorship |
| Approved | Shared details allow ownership; Approved list only includes records personally recommended |
| Printed | Same author restriction as approved; Include Printed and other filters additionally apply |
| Rejected | Existing creator-owned rejected branch remains in default Pending |
| Draft | Existing draft list uses creator identity; the Pending addition does not include drafts |

Synthetic pending/recommended private fixtures 9 and 10 both returned Pending permission `1` for creator A. All six approved/printed creator fixtures returned Pending `0`, as appropriate; they are not moved into Pending by the previous fix.

Fixtures 5 and 6 (A's printed private and coop slips recommended by D/B) were absent from A's Approved list despite shared `view=1` and Include Printed enabled. B retained fixture 6. Changing only the printed filter cannot fix the ownership omission.

The printed toggle tests `printed_at`, while list inclusion also tests status. For internally inconsistent legacy records, a `printed` row with null timestamp could pass “hide printed,” and an `approved` row with a timestamp could be hidden. This is a separate semantic distinction; no data repair or status change is proposed during inspection.

## G. Sidebar and Dashboard Counts

1. **Approved sidebar badge:** recommender branch at `includes/sidebar.php:98` requires `gs.recommended_by=actor`, `gs.status='approved'` and shared view authorization. Creator-only approved slips are omitted here too.
2. **Dashboard Approved card:** `includes/dashboard/cards.php:102` reuses `$counts['approved']`, so it inherits the same author omission and approved-only semantics.
3. **Approved page:** includes both approved and printed, unless printed timestamp/date/search/group filters remove entries. Thus a page count can exceed the sidebar badge even with no ownership bug.
4. **Group-tab counts:** `bulkHistoryCountRecords` mirrors author history and includes both statuses and active filters. Updating only the main page would leave these counters inconsistent.
5. **Monthly approved graph:** `includes/dashboard/approved.php:67` uses shared view, issuance year, and `$graphStatusScope`; for a recommender without approver/admin capability this is `status='approved'`. It does not require `recommended_by=actor`. Creator-owned approved records can contribute to this graph even while the Approved card/list omit them. Dual approver/admin flags broaden graph statuses to include printed.
6. **Dashboard total:** non-admin recommender total in `includes/dashboard/counts.php:122` counts shared-view-accessible records across stages, not an Approved-only total.
7. **Recent dashboard list:** shared view plus approved/printed, newest ten by issuance date, no author requirement. A missing Approved-list slip may already appear here if recent enough.

A later creator-display correction would need explicit reuse in the badge and history count consumers; the current author-only counters would not automatically change. Preserve or deliberately document the existing badge-versus-printed distinction. A boolean `(recommended_by=actor OR user_id=actor)` on each row cannot double-count an overlap by itself; avoid additive counts or UNION ALL. Route joins must retain grouping/deduplication and matching count semantics.

## H. Security and Authorization Findings

### Existing separation

- Shared view permission requires an existing active database actor; creator ownership uses **`gs.user_id`**, not free-text `requested_by`.
- `view_gas_slip_modal.php:70` checks shared view before returning details. Its action capabilities are separate `recommend` and `approve` checks. The current JS fetches JSON/header and HTML/body from that endpoint.
- Recommendation requires pending status and the existing assigned-recommender/delegate or private department-approver rule. Approval requires recommended status, current approval scope and a different positive persisted recommendation author. Rejection is constrained by recommendation/approval scope.
- Approved and printed fixtures returned recommendation/approval/rejection `0` even for their creator. Visibility alone does not make a completed slip actionable. Editor access is separately draft/owner scoped.
- `bulkRecords()` uses action permissions, not Approved display membership. Approved-page API context permits group filtering, not bulk workflow execution.

### Print permission is separate, and already recognizes creators

`canAccessPrintGasSlip()` permits admin, actual creator, recommendation author or approval author. A read-only PHP invocation confirmed creator relationship permission returns true for A's B-recommended slip. This is an existing right, not a proposed grant.

`print_gas_slip.php` and `update_printed_at.php` additionally require session fields and eligible status: approved, or printed when Print Once is disabled. The update handler locks rows and changes approved to printed while setting `printed_at`. These endpoints were inspected only; no print/status update request was sent.

The page's print checkbox uses status/Print Once rather than the complete relationship helper. Making an actual creator's approved row visible would expose a checkbox for an already permitted creator print operation. Broader view-only audiences must not be treated as print-authorized merely because they can see a row. Preserve the independent print endpoint checks.

### Privacy limits and existing risks

An unrelated actor must still pass existing view authorization. However, that authorization is broader than creator or workflow participant: the current-recommender approved/printed view branch can admit a current department/area recommender **even for a private vehicle**, because that final branch has no ownership exclusion. In the synthetic fixtures, same-area recommenders A/B could view some other creators' completed private records under that existing rule; foreign-department C could not. Do not describe all unrelated recommenders as automatically denied, or silently narrow that policy in a creator-list correction.

A proposed new list branch should require shared view and actual ownership, while leaving existing authorized history intact. Keep session authentication at the entry point; the shared SQL's active-user requirement does not substitute for session validation. Separately documented legacy endpoint and settings/save security issues remain in `recommender-workflow-inspection.md`; no security refactoring or permission changes were made here.

## I. Scenario Test Matrix

### Executed read-only method

Connected directly via mysqli to the configured local database and began `START TRANSACTION READ ONLY`; no application bootstrap or mutation endpoint was invoked. Synthetic CTE tables shadowed the policy and query tables; no fixture rows were inserted. The test extracted the actual current page's prepared count/main SQL strings, substituted the actual shared view expression and default group `1=1`, and bound actor/offset/limit normally. It also executed current view/action/Pending policy expressions.

Actors: A=10 and B=11, same-department/area active recommenders; C=12, foreign department/area recommender; D=30, department approver; P=40, configured private approver with required membership. The transaction was rolled back. No real slip identities/content were exported.

Actual query results:

```text
A recommender Approved count=2, IDs=[3,4]
B recommender Approved count=3, IDs=[2,6,8]
C recommender Approved count=0, IDs=[]
D approver Approved count=8, IDs=[1,2,3,4,5,6,7,8]
P private approver Approved count=3, IDs=[1,5,7]
```

Fixture IDs are synthetic and do not identify production slips. IDs 1–6 belong to A: private approved, coop approved by B, self-recommended coop approved, self-automatic coop approved, printed private, printed coop. IDs 7–8 belong to B; 9–10 are A's private pending/recommended.

**PASS is limited to executed SQL permission/list assertions, not an unexecuted workflow or browser test.**

| Scenario | Classification | Evidence / boundary |
|---|---|---|
| A private, D recommends, P approves: A sees Approved | **FAIL — SQL confirmed** | Fixture 1: A view=1 but absent from actual list/count |
| A coop, B recommends, D approves: A sees Approved | **FAIL — SQL confirmed** | Fixture 2: A view=1 but absent from actual list/count |
| A creates and personally recommends coop | **PASS — SQL list visibility** | Fixture 3 included for A; lifecycle not executed |
| A executes recommender-only automatic approval on own coop | **PASS — SQL list visibility** | Fixture 4 included for A; automatic transition inspected, not executed |
| A private becomes printed | **FAIL — SQL confirmed** | Fixture 5 absent for A despite view=1 and default Include Printed |
| A coop becomes printed | **FAIL — SQL confirmed** | Fixture 6 absent for A despite view=1 |
| B recommends A's coop: B retains visibility | **PASS — SQL list visibility** | B actual list includes approved fixture 2 and printed fixture 6 |
| Authorized approvers retain visibility | **PASS — SQL list visibility** | D includes fixture 2; P includes fixture 1, using actual approver queries |
| Unrelated C views A's private without independent entitlement | **PASS — SQL denial** | C view=0 and list count=0. Same-area current-recommender independent view entitlement is separately noted above |
| A opens own approved detail modal | **NEEDS VERIFICATION — HTTP/UI** | Executed shared view=1; actual endpoint and JS source inspected. No HTTP/modal render executed this turn |
| Actor views another creator's slip without authorization | **PASS — SQL denial** | Foreign-department C view=0; actual HTTP denial not exercised |
| Previous Pending creator correction still present | **PASS — SQL scope** | Actual helper gives A Pending=1 for private pending/recommended fixtures 9–10 |
| Creator display grants no completed-stage workflow authority | **PASS — SQL scope** | A recommend/approve/reject=0 for all six own approved/printed fixtures |
| Count and main query agree | **PASS — SQL execution** | Extracted count and list queries returned matching totals for all five actors |
| Active search/date/printed/custom-group combinations and pagination | **NEEDS VERIFICATION — execution** | Relevant current conditions inspected; only default group/no extra filters, offset 0/limit 100 executed |
| Physical printing, Print Once and status update | **NEEDS VERIFICATION — endpoint execution** | Relationship helper executed; remaining endpoint conditions inspected only |

No write-based integration tests were run. A later authorized disposable-database regression should create both vehicle types, execute regular and automatic approval, exercise actual modal/print authorization, paginate overlaps and compare All/custom/search/date/printed counters, with normal-preparer and inactive/foreign actors. Do not run these mutations against production.

## J. Root Cause and Recommended Correction

**Proposed only; no implementation performed.**

Introduce a shared recommender Approved display predicate consistent with the current helper architecture:

```text
status IN (approved, printed)
AND shared_view_permission(active_actor, record)
AND (recommended_by = actor OR user_id = actor)
```

Retain existing filters as additional AND conditions and keep normal-preparer/approver branches unchanged unless a separately authorized requirement says otherwise. The ownership alternative must be grouped; a bare OR appended after filters could leak records or bypass status/filter constraints. Preserve existing recommendation-author history even when the actor is not the creator. No new assignment/ownership field is needed.

Minimum consumers to review/correct together:

1. `includes/gas_slip_access.php`: proposed reusable Approved display scope; leave action permissions and corrected Pending scope unchanged.
2. `approved_slips.php:148` and `:196`: replace identical author-only restrictions in count and main query with the shared creator-or-author display scope.
3. `includes/sidebar.php:98`: include creator ownership using the same relationship/view rule while preserving explicitly chosen badge statuses.
4. `includes/bulk_processing.php:101`: align Approved history/tab counts and group intersections with the corrected list, without changing `bulkRecords()` or workflow operations.
5. Tests and documentation: verify completed-stage creator and author visibility, filter/count parity and independent action/print checks. Dashboard card wiring needs no separate patch when sidebar counts are corrected; monthly/recent statistics should not be broadly rewritten.

The separate Recommended history page also distinguishes authorship from ownership. A consistent creator-owned list policy is warranted for the reported Approved stage, but do not automatically change other pages' meaning or move records between tabs. Private routing must remain department recommendation → configured private final approval.

### Explicit answers

1. **Private slips disappear** because their department approver, not creator A, is `recommended_by`, and A's recommender list requires its own ID there.
2. **Coop slips disappear** because B is `recommended_by` while A is only `user_id`.
3. **The root cause is shared**: the same ownership-independent recommender author predicate in Approved count/list and related counters.
4. **Printed slips are affected** by that predicate too, with an additional printed timestamp toggle and an existing badge status mismatch.
5. **Correction targets** are the shared display policy, both Approved page author predicates, sidebar author count and Approved history-count author restriction described above.
6. **No database modifications are required.** Existing creator and recommendation-author fields are sufficient.
7. **Recommendation, approval and print permissions can remain unchanged.** Print already recognizes creators independently; list inclusion must not replace any action permission.

## K. Regression Risks

| Area | Risk to control in a future fix |
|---|---|
| Existing recommender history | Preserve authors' visibility to records they recommended for other creators |
| Creator records | Admit only actual `user_id` ownership with shared active-user view permission, not Requested By text |
| Groups | Apply creator-inclusive history visibility before existing owner-scoped name intersection; keep Approved groups filter-only and Pending bulk action scope unchanged |
| Pagination | Match count and row predicates; avoid duplicate joins/UNION ALL/additive author-plus-creator counts; retain existing sort/limits |
| Counts | Update page, sidebar/card and history tab consumers coherently; explicitly preserve or separately decide approved-only badge versus printed-inclusive page semantics |
| Private routing | Do not change ownership detection, recommendation actors, final approver or recommender-only exclusion for private vehicles |
| Printing | Newly visible creator rows expose existing permitted controls; retain relationship/status/Print Once checks for direct print and update requests |
| Other roles | Recommender-first branch includes dual-capability users; avoid widening unrelated approver/admin/normal-preparer list branches |
| Privacy | Existing completed-private current-recommender view entitlement is broader than creator/participant; preserve and document rather than accidentally treating every visible record as action-authorized |

Only this report was created during the inspection. Application files, tests, permissions, data and schemas were not changed. Existing uncommitted Pending-fix files were left intact. No migrations, commits or pushes were performed. Await approval before implementing any correction.
