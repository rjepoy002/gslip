# Multi-area gas slip authorization correction

## Confirmed root cause

`includes/pending/query.php` used multi-area assignments and the preparer's selected recommender. `view_gas_slip_modal.php` independently authorized recommenders in hardcoded department IDs 1/2 with `gas_slips.area_id == $_SESSION['area']`. That single-area check ignored `recommender_area_assignments`. It also lacked a historical `recommended_by`/`approved_by` relationship check. Dashboard scopes had the same single-area restriction, and some area-only queries omitted a department constraint.

Read-only inspection of `gsccxzp_pal_db_test` confirmed the actual normalized schema, department registrations, approver configuration, and assignments:

| Internal slip ID | Stored slip code | Status | Preparer | Slip/preparer area | Selected recommender | Recommender primary area |
|---|---|---|---|---|---|---|
| 138 | 2026025000138 | pending | 25 | 12, San Vicente | 7 | 11, Roxas |
| 139 | 2026026000139 | pending | 26 | 11, Roxas | 7 | 11, Roxas |

The stored codes include an additional zero compared with the reported examples. Recommender 7 is active, registered in ANOD (department 2), and mapped to both areas 11 and 12. The old detail check denies slip 138 with the current profile. Slip 139 passes the old check with the current profile; its reported denial could not be reproduced from those database values alone. A different/stale session area would affect the old check. The replacement policy reads the current database actor and no longer depends on session department/area for record authorization.

Read-only checks using the corrected policy allow recommender 7 to view and recommend both records and deny approval authority. Both records remain pending and their assignments were preserved. No application database writes or schema migrations were performed.

## Policy boundaries

`includes/gas_slip_access.php` defines separate `view`, `recommend`, `approve`, `reject`, and `edit` predicates. The same predicates work in single-record checks, list/count queries, and conditional UPDATE statements. Actor IDs are cast to integers and record lookup IDs are bound parameters. The only required outer alias is `gas_slips gs`; role checks and related entities are resolved inside the policy from current database configuration.

- View: active actors can read their own slips; administrators retain existing read access. Recorded recommenders, approvers and rejectors retain non-draft historical read access even after area removal or recommender deregistration. Configured department/private approvers retain their workflow-specific view scope. Registered primary recommenders can view approved/printed slips in their department's current assigned areas; non-area departments retain their department-based approved view. The Approved tab still uses its existing participant-specific recommender filter, while Recent/analytics may show the broader authorized approved scope.
- Recommend: the slip must be pending. A cooperative vehicle requires the preparer's selected primary (or valid current delegate), active registration/status, matching department and current area eligibility. Additional assigned areas never grant access to unrelated preparers. Private vehicles keep configured department-approver recommendation and separate final private approval.
- Approve: the slip must be recommended and the actor must have the corresponding configured department/private approver authority. Recommender-only settings still reserve final approval for the existing recommendation action. Admin/recommender/area membership alone never grants ordinary approval.
- Reject: requires current permission to recommend or approve that specific slip, with an atomic status/relationship predicate. The old role-only reject endpoint could otherwise act on unrelated records.
- Edit: remains owner-only for drafts. Draft listing, edit, withdrawal, duplicate-detail and cancellation ownership paths were inspected; their existing ownership guards remain unchanged. Assignment management remains with the existing `canManageDepartmentRecommenders()` policy and CSRF-protected endpoints.

`assignedRecommenderAccessSql()` in `includes/recommender_assignment.php` is shared by pending listing, record-view authorization and the existing `canAccessAssignedRecommender()` action helper. It reuses the dropdown's `recommenderAreaSql()` predicate and retains same-area/date/status restrictions and single-current-secondary resolution for delegation.

Recommendation and approval UPDATEs retain their pending/recommended status guards and now include the corresponding policy at execution. Detail action buttons also require the action policy. Viewing historical records never grants permission to act on them.

## Affected paths

New:

- `includes/gas_slip_access.php`: shared view/action SQL policies and single-record check.
- `tests/gas_slip_access.php`: disposable MySQL and HTTP regression suite.
- This document.

Updated:

- `view_gas_slip_modal.php`: JSON and HTML detail access, action visibility.
- `includes/recommender_assignment.php`: shared selected-primary/delegate predicate.
- `includes/pending/query.php`, `includes/sidebar.php`: shared actionable queue and counters.
- `recommended_gas_slips.php`, `approved_slips.php`: common view guard alongside existing tab-specific filters, dates, printing and historical participation.
- `includes/dashboard/counts.php`, `recent.php`, `approved.php`, `fuel.php`, `vehicles.php`, `routes.php`, `drivers.php`, `fuel_items.php`: authorized record scope for totals, recent approved records and aggregates.
- `recommend_gas_slip.php`, `approve_gas_slip.php`, `reject_gas_slip.php`: action-policy enforcement in conditional writes.

Notifications remain recipient-owned (`ajax/get_notifications.php`); notification receipt is never an authorization grant. Authorized notification records can open through the same detail policy. A stale pending notification is denied after an area is removed, while historical participation remains readable. Notification routing code was not changed. Printing already uses explicit owner/recommender/approver relationships (`includes/print_access.php`) and remains unchanged, as do public signed verification, preparer selection, saved assignment defaults, and area management.

## Verification

Run:

```powershell
php tests/gas_slip_access.php
php tests/recommender_areas.php
```

The new suite creates a unique disposable database and HTTP sandbox, then removes both. It requires local PHP/mysqli and MySQL root database-create privileges. The HTTP sandbox copies the actual controllers/policies and replaces only database configuration, session setup, and outbound web push. No external push is sent. Tab probes execute actual controller/query prefixes without unrelated page rendering.

43 authorization checks passed: single/multiple areas; opening and recommending each assigned area; unassigned/foreign areas; unrelated preparers; owner-only drafts; historical access after reassignment/deregistration; recommender and approver approved visibility; direct JSON and HTML URLs; stale session profile values; pending counts/list/detail consistency; dashboard totals, aggregates and Recent records; recipient-owned notifications; stale notification revocation; delegation; private/recommender-only restrictions; actual recommendation endpoint success; and actual approval rejection/success by configured role and department. The existing 39 area-assignment checks also passed. Changed PHP files passed syntax checks and the diff passed whitespace checks.

No schema or data migration is required. Deploy the shared policy and changed PHP consumers together. Browser UI rendering, live signed printing, and real outbound push delivery were not retested for this server-side correction. Existing dashboard date/status/printed filters remain; their totals need not equal tabs with different filters. Performance should be observed on production-sized datasets because the policy uses indexed correlated existence checks. No production benchmark was run.
