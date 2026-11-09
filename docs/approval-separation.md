# Completed recommendations and independent approval

## Confirmed cause and affected records

The configured local database had four gas slips in `recommended` (shown as For Approval): IDs **155, 158, 159 and 161**, all with persisted `recommended_by=17` and nonempty recommendation timestamps. Their department/area's current `department_approval_settings.recommender_only` value was 1.

Two retroactive checks blocked them: `gasSlipPermissionSql('approve')` excluded recommended records in currently recommender-only areas, and `approveGasSlipWorkflow()` independently rejected the same setting. Consequently Pending, bulk eligibility and individual approval all lost access to a completed recommendation. The original recommender's assignment was not the join causing this local disappearance; the current area setting was. Changing the author to an approver also exposed a separate missing self-approval check.

Before correction, the read-only audit reported no authorized approvers for all four records. After correction, the actual Pending query for independent approver **15** returns all four. Original author **17** has none of those records in the actionable Pending queue. Their statuses, recommender IDs and timestamps were not changed. No production workflow action was executed during verification.

## Authorization behavior

Persisted `status='recommended'` is authoritative: a different currently authorized approver may approve/reject without requiring the original author to remain a recommender or the area's current recommender-only setting to remain unchanged. Existing department, area, vehicle ownership, private-approver and active-account restrictions still apply.

The shared approval predicate requires a positive persisted `recommended_by` different from the acting account. Approval-stage rejection shares that same predicate. This automatically covers individual actions, details buttons, Pending counts/lists, All/custom-group eligibility, checkboxes, Select All, tab counts, snapshots and final guarded updates. Per-record authorization is still checked during bulk execution. Existing pending-stage recommender-only completion is unchanged; this fix concerns requests that have already reached the separate approval stage.

The recommender Pending helper also includes independently authorized approvals for multi-role accounts. Recommended history retains records authored by the account even after all matching workflow flags are gone; view permission does not confer action permission. Count queries follow the same history scope.

Three routable legacy Approve/Reject URLs now delegate to the current endpoints, preventing bypass through obsolete unguarded status updates. They use current authentication and mandatory rejection-reason handling.

## Missing authors or independent approvers

No schema change or data migration was required: `recommended_by` and `recommended_at` already preserve the recommendation. Recommended records without a positive author ID fail closed for approval/rejection and are flagged for administrative review. Identities are never guessed from notification recipients or current assignments.

If no independent authorized approver exists, status remains recommended. Administrators retain the existing ability to view the record through the shared view policy. The CLI audit identifies unresolved records so an operator can review them and use the existing assignment settings; it never assigns authority or transitions slips.

```powershell
php tools/audit_approval_queue.php
php tools/audit_approval_queue.php --pending-user=15
```

The tool is CLI-only and read-only. Its optional queue check uses the actual Pending query, resolving current assignment flags from the database. The default report lists recommended records, current area setting, independent authorized approvers, and review reasons.

## Files changed for this fix

- `includes/gas_slip_access.php`: persisted-author separation and completed-stage eligibility; multi-role Pending scope.
- `includes/gas_slip_workflow.php`: removes the obsolete retroactive setting gate and uses shared approval authorization.
- `recommended_gas_slips.php`: retains authored recommendation history without workflow-role flags.
- `includes/bulk_processing.php`: corresponding historical-count scope.
- `approve_gas_slip_08272026.php`, `approve_gas_slip_09022026.php`, `reject_gas_slip_07132026.php`: secured compatibility routes.
- `tools/audit_approval_queue.php`: read-only oversight.
- `tests/gas_slip_access.php`, `tests/bulk_processing_cases.php`, new `tests/approval_separation_cases.php`: regression coverage.
- `docs/approval-separation.md`, `docs/bulk-processing.md`: behavior and verification notes.

## Verification and limits

The isolated MySQL/HTTP suite reproduces real recommendations, assignment/setting changes, own-action denial through current and legacy URLs, unchanged timestamps/notifications after denial, independent individual/bulk approval and rejection, group counts/forged IDs, final identity revalidation, missing authors, no-independent-approver oversight, and author department/area changes. It also runs the original detail, approval, recommendation, history, ownership and multi-area checks. UI regressions cover unchanged confirmations, selection, counts, themes, group management, row details and printing.

Executed successfully on November 6, 2026: `php tests/bulk_processing.php`, `php tests/recommender_areas.php`, and `node tests/bulk_processing_ui.cjs` using the configured external Playwright installation. PHP syntax checks passed for all eleven PHP files touched by this fix, and `git diff --check` passed. The live audit was run with `--pending-user=15` and `--pending-user=17` to verify actual queue visibility without processing records.

No historical identity was reconstructed because the affected local records already had reliable IDs. Other deployments with missing IDs require review of their own reliable persisted audit data; the tool flags those records rather than inventing a recovery. Existing requester department/area relationships remain the source of department visibility; this change does not introduce historical department snapshots. No database tables or existing slip data were modified, and nothing was pushed to GitHub.
