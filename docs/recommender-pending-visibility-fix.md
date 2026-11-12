# Recommender Pending Visibility Fix

Implemented November 12, 2026 (Asia/Manila).

## Result

Recommenders can now see their own pending and recommended gas slips in the **default Pending Gas Slips list**, for both private and coop-owned vehicles. Ownership adds visibility only; recommendation, approval and rejection authority are unchanged.

## Root cause and correction

Previously, `recommenderPendingSlipSql()` included only:

1. Pending slips the actor could recommend.
2. Recommended slips the actor could approve.
3. Rejected slips the actor created.

Creator-owned pending/recommended slips were absent unless the creator also qualified for one of the action branches. This differed from the existing detail-view ownership policy.

The helper now retains those three branches and adds:

```php
$view = gasSlipPermissionSql('view', $id);
// Added OR branch:
// gs.user_id = $id
// AND gs.status IN ('pending','recommended')
// AND $view
```

`$id` follows the existing integer-cast convention. The shared view permission requires an existing active database actor and recognizes creator ownership. The addition is not a bare ownership bypass. OR branches do not produce extra rows; no new join or UNION was introduced.

`gasSlipPermissionSql()` and `gasSlipIndependentApprovalSql()` remain unchanged. A source comparison against HEAD confirmed all permission code preceding the Pending helper is unchanged.

## Before / after

| Scenario | Before | After | Action authority |
|---|---|---|---|
| Own private pending | Missing from ordinary recommender Pending | Visible | Department approver recommends under existing rules |
| Own private recommended | Missing without approval authority | Visible | Configured private approver handles final approval |
| Own coop pending assigned to B | Missing for creator A | Visible for A; still actionable for B | A gains no action from ownership |
| Own self-assigned coop pending | Visible when authorized | Visible once | Existing self-assignment authority preserved |
| Own coop recommended | Missing without approval authority | Visible | Existing independent approval rules preserved |
| Own rejected | Visible | Visible | Existing behavior preserved |
| Unrelated private | Denied without independent authority | Same | No new access |
| Own draft / approved / printed | Outside this Pending ownership scope | Same | No expansion to other stages |

## Consumers and preserved behavior

- `includes/pending/query.php` already uses this helper for both count and record queries. Pagination, ordering, route aggregation, labels and filters were not changed.
- `includes/sidebar.php` already uses it for the recommender Pending badge. `includes/dashboard/cards.php` displays those same Pending counts. Their production code needed no changes.
- `view_gas_slip_modal.php` already authorizes creator details separately from action buttons. The modal and `assets/js/gas-slip.js` needed no changes.
- `bulkRecords()` still selects by recommendation/approval permission. Custom Requested By tabs and bulk selection remain action-only; creator-only records do not join them, even when their Requested By name matches.
- Normal preparer queries, private-vehicle routing, assignment selection, notification recipients, status transitions and recommender-only automatic approval were not changed.
- Previously documented security issues remain in [recommender-workflow-inspection.md](recommender-workflow-inspection.md) for a separate task. No legacy endpoint or unrelated security fix is included here.

## Files changed by this implementation

| File | Change |
|---|---|
| `includes/gas_slip_access.php` | Added the guarded creator visibility branch to `recommenderPendingSlipSql()` |
| `tests/gas_slip_access.php` | Invokes the focused cases within its existing disposable database/HTTP harness |
| `tests/recommender_pending_visibility_cases.php` | Added focused SQL, real query, modal and workflow HTTP regression coverage |
| `docs/recommender-pending-visibility-fix.md` | This implementation summary |

The pre-existing inspection report was not changed during this implementation.

## Executed verification

`php tests/gas_slip_access.php` completed successfully, exit code **0**, with **91 PASS assertions** (43 existing assertions and 48 focused assertions for this change). The harness creates a randomly named `gslip_access_test_*` database and serves copied application endpoints with a replacement configuration pointing only to that disposable database. Web pushes are stubbed. The successful run drops its test database and removes its HTTP sandbox.

Executed coverage includes:

- Own private pending/recommended, own coop assigned-to-other pending/recommended, self-assigned pending and own rejected visibility; exclusion of unrelated and other-stage records.
- Inactive/missing actors denied by the new ownership branch.
- Actual Pending count and record queries, two-record pagination, and absence of duplicate creator/action overlaps.
- Actual sidebar count block matching the default Pending list; dashboard count reuse inspected.
- Normal preparer Pending list/count unchanged.
- Creator JSON and HTML detail responses, correct status labels, hidden unauthorized Recommend/Approve/Reject buttons, authorized assignee button retained, unrelated private request denied.
- Actual current recommendation and approval HTTP requests denied for creator-only records without changing their statuses.
- Dual-capability creator's normal self-approval denied.
- Assignee eligibility and area removal enforced independently of creator visibility; existing suite covers multi-area and delegation scope.
- Private approver membership requirement, department recommendation HTTP success, and global private approval HTTP success.
- Self-assigned coop recommendation with recommender-only setting still automatically approves and records both actor IDs/type correctly.
- Shared bulk eligibility for recommend/approve/auto, matching custom-group exclusion, foreign group denial, forged creator-only selection rejection, and actual custom Pending table override retaining action-only results.

Also passed:

```text
php -l includes/gas_slip_access.php
php -l tests/gas_slip_access.php
php -l tests/recommender_pending_visibility_cases.php
git diff --check
```

Two initial runs exposed test-harness assumptions (status labels are in the modal JSON header, and requiring copied helpers in the same PHP process redeclared functions). Those test issues were corrected; the final full run passed. No production changes beyond the display helper were needed.

## Verification limits

- No interactive browser session was run. Modal responses/buttons were checked through real local HTTP endpoints; the unchanged JavaScript rendering and dashboard card wiring were inspected.
- The full `tests/bulk_processing.php` suite was not executed because it invokes migration scripts. Focused tests instead used disposable group fixtures and exercised unchanged bulk selection/group functions and the custom display override. Bulk snapshot/process HTTP and concurrency tests were not rerun.
- Creation-form submission, external push delivery, and unrelated legacy/security endpoints were not tested or modified.

## Database and regression risk

No application database changes, schema changes or migrations are required or were performed. Test fixtures and schema writes were confined to the disposable test database. No commits or pushes were made.

Regression risk is low: one display helper changed, with existing action predicates preserved. The intentional behavior change is that the default Pending badge/list now also includes creator-only pending/recommended entries; custom tabs and bulk action counts can be smaller because they continue to represent actionable records. The added view predicate introduces another correlated permission expression; large-queue performance was not benchmarked.
