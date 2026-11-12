# Approved Gas Slips Creator Visibility Fix

Implemented November 12, 2026 (Asia/Manila).

## Result and root cause

Recommenders now see approved and printed slips they **created or recommended**, subject to the existing active-user record-view permission and page filters.

Previously, the recommender Approved list/count, sidebar badge and history-count helper required `recommended_by=current_user`. A private slip recommended by a department approver, or a coop slip recommended by B, disappeared from creator A's list despite A retaining detail-view permission.

| Example | Before | After |
|---|---|---|
| A creates private; department approver recommends; global private approver approves | Missing for A | Visible for A |
| A creates coop; B recommends; department approver approves | Missing for A | Visible for A and existing author B |
| A creates and recommends own coop slip | Visible | Visible once |
| A executes recommender-only automatic approval | Visible | Visible once |
| A's private/coop slip recommended by someone else becomes printed | Missing for A | Visible when permitted by existing Include Printed filter |

## Shared predicate and consumers

Added `recommenderApprovedSlipSql($userId)` in `includes/gas_slip_access.php`:

```php
$id = (int) $userId;
$view = gasSlipPermissionSql('view', $id);
return "(gs.status IN ('approved','printed') AND $view
    AND (gs.recommended_by = $id OR gs.user_id = $id))";
```

Creator identity is `gs.user_id`, never free-text Requested By. The shared view expression retains active database actor validation. Grouped boolean conditions prevent ownership from bypassing statuses or filters; overlapping authorship and ownership cannot create duplicate rows.

- `approved_slips.php`: recommender count and row queries reuse this helper. Existing group/search/date/printed filters remain additional AND conditions. Sorting, route aggregation and pagination are unchanged. Parameter bindings were adjusted because the helper follows the existing integer-cast SQL convention.
- `includes/sidebar.php`: reuses the helper with `AND gs.status='approved'`. **Printed slips remain excluded from this badge.** The dashboard Approved card already uses this count and needs no code change.
- `includes/bulk_processing.php`: only the Approved recommender branch of `bulkHistoryCountRecords()` now uses the helper. Existing normalized Requested By group counts and filters follow the corrected display universe.

`bulkRecords()` and Pending custom-group eligibility are unchanged. Approved remains a group-filter-only context, without bulk workflow actions.

## Files changed in this task

1. `includes/gas_slip_access.php` — appended the Approved display helper.
2. `approved_slips.php` — reused it in recommender count/list.
3. `includes/sidebar.php` — reused it for approved-only badge.
4. `includes/bulk_processing.php` — aligned Approved history-count scope.
5. `tests/gas_slip_access.php` — added the focused suite include.
6. `tests/recommender_approved_visibility_cases.php` — added disposable query/HTTP regressions.
7. `docs/recommender-approved-visibility-fix.md` — this summary.

At task start, `includes/gas_slip_access.php` and `tests/gas_slip_access.php` already had uncommitted Pending-fix changes. The three existing inspection/implementation reports and `tests/recommender_pending_visibility_cases.php` were untracked. Those prior changes were preserved; the Pending helper and Pending test cases were not edited by this task.

## Preserved permissions and workflows

No changes were made to `gasSlipPermissionSql()`, independent approval, rejection permission, the existing Pending helper, workflow handlers, assignment selection, notifications, private routing or normal-preparer/approver query branches.

Private routing remains department recommendation → configured global private approval with existing membership/separation rules. Coop routing remains assigned primary/delegate recommendation → department approval, including the existing recommender-only shortcut and self-assignment behavior.

Print permission remains independent. `canAccessPrintGasSlip()`, `print_gas_slip.php`, `update_printed_at.php`, Print Once handling and print UI were not modified. Existing creator print rights do not arise from the new display helper. The broader existing same-area completed-private view entitlement was neither tightened nor expanded.

## Executed verification

Final combined run:

```text
php tests/bulk_processing.php
293 PASS assertions; exit code 0
```

This includes the existing access suite, previous Pending regressions, new Approved regressions, bulk processing and independent-approval separation cases. It creates a randomly named disposable `gslip_access_test_*` database and copied HTTP sandbox, then drops/removes them. Application endpoints in the sandbox use its replacement database configuration and stubbed external push sender. Existing migration checks in the bulk suite ran **only in that disposable database**, never against the configured application database. No migration files were added or changed.

The successful combined run verified:

- Creator visibility for private/coop approved and printed fixtures; existing author history; self-author overlap once; inactive/missing actor exclusion; unrelated/non-completed records excluded.
- Actual Approved controller count/list SQL across multiple pages, including multiple route rows on one slip without duplicate records.
- Actual search by slip code and related route, inclusive approval-date boundaries, Include Printed on/off, and unchanged `printed_at` semantics for inconsistent legacy fixtures.
- Default and custom history-count parity, normalized owner-scoped group membership and rejection of another owner's group.
- Actual sidebar count block admitting creator records while counting approved only; dashboard card's existing count reference inspected/asserted.
- Actual creator approved/printed detail-body responses with no Recommend/Approve/Reject controls; unrelated private detail denied.
- Actual current recommendation, approval and rejection requests cannot mutate creator completed-stage records; completed display rows remain absent from bulk action eligibility.
- Actual unchanged `update_printed_at.php`: creator first print allowed, Print Once repeat denied, unrelated relationship denied, repeat allowed when Print Once disabled.
- Actual private department recommendation/global approval and assigned-B coop recommendation/department approval, followed by creator-visible Approved query results.
- Actual self-assigned recommender-only approval appears once in Approved.
- Previous Pending creator visibility and counts, multi-area/delegation rules, normal-preparer and approver behavior, bulk eligibility, snapshots, and persisted independent-approval restrictions.

The initial focused run had one test assertion look only at B's first page for an older record; it was corrected to use the actual search filter. The final combined run has no failures.

Also passed PHP syntax checks for all six changed PHP files and `git diff --check`.

## Skipped checks and remaining risk

- No interactive browser session or visual layout check was performed. Real HTTP controller/modal responses were exercised; unchanged JS/HTML wiring was inspected.
- No physical print dialog, QR/PDF generation or `print_gas_slip.php` document-rendering run was performed. Its unchanged authorization path was inspected; the actual print-status endpoint was exercised.
- No live application records were used as test fixtures, and no production workflow requests were sent.
- No performance benchmark was run. The page retains its general shared-view filter in addition to the new helper's view guard; larger-queue query cost remains unmeasured.
- The sidebar intentionally counts approved only, while the page can include printed. Filters also intentionally affect page/tab counts without changing the unfiltered badge.
- Separate security findings remain documented in `recommender-workflow-inspection.md`; no legacy or unrelated security refactoring was included.

No application database records/schema changes or deployment migrations are required or were performed. Only disposable test data/schema were modified. No commits or pushes were made.
