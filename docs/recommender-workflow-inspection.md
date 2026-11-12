# GSLIP — Recommender Workflow Inspection

Inspection date: November 12, 2026 (Asia/Manila). Repository: `C:\xampp\htdocs\gslip`, HEAD `b786364`.

## A. Executive Summary

**The missing creator-owned Pending entry is confirmed by executing the current SQL policy with read-only synthetic fixtures.** The recommender Pending query includes pending slips the actor may recommend, recommended slips the actor may approve, and rejected slips they created. It does not include creator-owned pending or recommended slips merely because they are the creator. The shared detail-view policy does allow the creator to view them. Normal coop-owned slips assigned to another recommender are affected too.

**Private means vehicle ownership, not a confidentiality flag.** The live `gas_slips` table has no private-visibility flag. Private handling follows `gas_slips.vehicle_id → vehicles.ownership = 'private'`. Its existing workflow is creator → department approver acting as recommender → global private-vehicle approver. Selecting a regular recommender does not give that person authority over a private-vehicle slip. Consequently, the request's expected private A/B workflow conflicts with the current implemented policy; correcting creator visibility alone will not make B its recommender.

Current individual and bulk handlers enforce record-level authority in SQL. Ordinary coop-owned recommendations require the creator's currently assigned primary recommender or an eligible active delegate. Normal approval requires a different persisted `recommended_by` identity and current approval authority. Recommender-only settings intentionally permit a non-private recommendation to become approved immediately, with the same person recorded in both roles.

**The repository as a whole cannot be considered secure:** approval-setting endpoints lack manager authorization, dated recommendation endpoints still contain unscoped state updates, an older detail endpoint lacks record authorization, and `save_gas_slip.php` can modify child records after an unsuccessful ownership-constrained update. These are verified source defects, not executed exploits. Actual HTTP exposure of dated files depends on web-server configuration, which was not audited outside this repository.

Inspection limits: no application PHP/JS/CSS/SQL was changed; no database records/schema were changed; no migrations, write-based test suites, workflow POSTs, commits, or pushes were run. This Markdown report is the only created artifact. Existing integration tests create databases and fixtures and were therefore inspected but not executed.

## B. Files and Functions Inspected

Line numbers refer to the inspected working tree. Dated files are called out separately because they remain executable PHP within the document-root repository; they are not assumed to be active navigation targets.

| File and line | Function / responsibility |
|---|---|
| `index.php:98`, `index.php:128` | Password validation, active-user recheck, login token and session establishment |
| `includes/login_session.php:3` | `establishLoginSession`: independent recommender/approver/private-approver flags, identity and area |
| `includes/auth.php:1`, `includes/config.php:34` | Session-presence guard; connection, remember-login and maintenance bootstrap |
| `includes/remember_login.php:40`, `:55` | `rememberSessionUser`, `rememberBootstrap`; token checking is not a universal POST guard |
| `create_gas_slip.php:1`, `:26`, `:913` | Creation page, current user assignment, required-recommender dialog |
| `includes/gas_slip_data.php:15` | Active vehicles/routes/fuel items for creation |
| `save_gas_slip.php:25`, `:40`, `:239`, `:302`, `:402` | Creator identity, assignment validation/update, edit, insert, notifications |
| `includes/recommender_assignment.php:8`, `:56`, `:77`, `:138`, `:157`, `:170` | Eligibility, delegation, creation-navigation capability, assignment access SQL |
| `includes/recommender_areas.php:3`, `:27`, `:36`, `:47` | Area policy, department lock, settings CSRF, replacement/default restoration |
| `includes/settings/assignment_scope.php:28`, `:72` | Manager authorization and recommender candidate exclusions |
| `save_recommender.php:1`, `save_recommender_areas.php:1`, `delete_recommender.php:144` | Recommender registration, area edits, removal |
| `save_primary_approver.php:71`, `save_secondary_approver.php:71` | Prevent permanent recommender selection as department approver |
| `save_department_approver.php:14`, `save_private_vehicle_approver.php:14`, `save_recommender_only_setting.php:15` | Approval assignment/settings writers; missing manager-authorization checks |
| `includes/gas_slip_access.php:6`, `:14`, `:60`, `:71` | Independent approval, shared permissions, record check, recommender Pending scope |
| `pending_gas_slips.php:7`, `includes/pending/query.php:33` | Pending page, count and list queries |
| `view_gas_slip_modal.php:73`, `:147` | Detail visibility and separate action capability checks |
| `assets/js/gas-slip.js:37`, `:94`, `:112`, `:238` | Detail fetches, recommendation/approval confirmation and POST requests |
| `recommend_gas_slip.php:47`, `approve_gas_slip.php:61` | Individual endpoints invoking shared workflow functions |
| `includes/gas_slip_workflow.php:5`, `:309`, `:377`, `:427`, `:486` | Recommendation, recommender-only approval, independent approval |
| `bulk_processing.php:39`, `:125`, `:169` | Session/CSRF validation, snapshots, locked per-record execution |
| `includes/bulk_processing.php:17`, `:39`, `:54`, `:90` | Owner-scoped groups, filters, eligible records, history counts |
| `includes/bulk_table_query.php:1`, `includes/bulk_processing_ui.php:1`, `assets/js/bulk-processing.js:17` | Custom Pending tables, page tokens, JSON API |
| `recommended_gas_slips.php:37`, `:45`, `approved_slips.php:63`, `:148` | Visibility-filtered actor history lists |
| `reject_gas_slip.php:1`, `includes/gas_slip_rejection.php:3` | Rejection guard, reason and conditional update |
| `withdraw_gas_slip.php:15`, `edit_gas_slip.php:34` | Owner withdrawal and draft-only editor |
| `includes/notifications.php:21`, `:134`, `:271`, `:341`, `:398`, `:424` | Notification insertion/context/lifecycle recipients |
| `ajax/get_notifications.php:15` | Recipient-scoped notification retrieval |
| `includes/sidebar.php:74`, `:115`, `includes/dashboard/cards.php:72` | Pending counts and dashboard Pending display |
| `includes/dashboard/counts.php:22`, `includes/dashboard/recent.php:24`, `includes/dashboard/approved.php:24` | Aggregate and recent approved/printed visibility |
| `for_approval.php:47` | Inactive-account approval list; this is not the gas-slip approval queue |
| `recommend_gas_slip_09022026.php:31`, `recommend_gas_slip_09042026.php:125` | Legacy recommendation state updates lacking current authority checks |
| `view_gas_slip_modal_08272026.php:13` | Legacy unrestricted slip lookup/details |
| `approve_gas_slip_08272026.php:1`, `approve_gas_slip_09022026.php:1`, `reject_gas_slip_07132026.php:1` | Legacy wrappers already forwarding to current handlers |
| `tests/gas_slip_access.php:1`, `tests/approval_separation_cases.php:1`, `tests/recommender_areas.php`, `tests/bulk_processing_cases.php` | Existing tests inspected only; they write fixtures |

### Database verification

Connected directly with mysqli to the configured local database, bypassing application bootstrap, and issued metadata/SELECT queries inside `START TRANSACTION READ ONLY`, then rolled back. Verified fields directly through `information_schema.COLUMNS`:

- `users`: `id`, `department_id`, `area_id`, `assigned_recommender_id`, `status`, `role`, `session_token`.
- `gas_slips`: `user_id`, `vehicle_id`, `area_id`, `status`, `recommended_by/at`, `approved_by/at`, `approver_type`, `rejected_by/at`, `rejection_reason`, `printed_at`, plus slip number, purpose, Requested By, issuance/validity fields. `recommender_only_approval` exists, but the inspected current recommendation update does not set it.
- `vehicles.ownership`: enum `coop-owned` / `private`. No `gas_slips` private-visibility flag or per-slip assigned-recommender column exists.
- `department_recommenders(department_id,user_id)`; `recommender_area_assignments(department_id,recommender_id,area_id)`; dated/status-bound `recommender_delegations`.
- `department_approvers(department_id,user_id,is_primary)`, `department_areas(department_id,area_id)`, `department_approval_settings(department_id,area_id,recommender_only)`, `approval_global_settings.private_vehicle_approver_user_id`.
- `notifications`: recipient/slip IDs, lifecycle type, title/message, read/display flags and timestamps. `bulk_processing_groups` and `bulk_processing_group_names` both carry `owner_user_id`.

Live aggregate inventory only: 3 pending, 4 recommended, 6 approved, 19 printed, 4 rejected. No identities or slip contents were exported. Schema verification does not prove every live account has valid assignments.

## C. Recommender Creation Flow

1. Login verifies credentials and active status and establishes the session. Recommender is a capability derived from `department_recommenders` or active delegation, not simply a `role='recommender'` value. Approver and private-approver flags are derived independently.
2. `create_gas_slip.php` loads the logged-in user's current assignment and eligible primary recommenders. The required-selection dialog is used when an eligible assignment is missing. A valid saved assignment can be reused. Secondary recommenders are not saved as primary assignments.
3. `save_gas_slip.php` records **`$_SESSION['user_id']` as `gas_slips.user_id`**, regardless of recommender capability. `requested_by` is separate user-entered text and is not the authenticated creator identity.
4. The handler takes posted `assigned_recommender_id`, falling back to `users.assigned_recommender_id`. It validates the candidate against the creator's database department and area and active recommender membership. ANOD/ASOD use explicit assigned areas; other departments use the primary area.
5. A changed selection updates **`users.assigned_recommender_id`**, not the slip. All unprocessed slips belonging to that creator consequently follow the new current assignment. Assignment is not a historical per-slip snapshot.
6. Each submitted row supplies vehicle, purpose, Requested By, validity, destinations and fuel JSON. The handler creates `gas_slips.status='pending'`, stores the session area as `gas_slips.area_id`, inserts `gas_slip_routes` and `fuel_requests`, generates the slip number, creates notifications and commits. No automatic recommendation occurs just because the creator is a recommender.
7. Private handling is inferred from the selected vehicle's ownership. No separate private-slip boolean is inserted. Even a private submission must pass regular assigned-recommender eligibility first, although that assignment does not govern its recommendation step.

The normal preparer and recommender use the same save path. Their post-save list branches differ: ordinary preparers see their own pending/recommended/rejected entries; recommenders receive the action-oriented query in section D. Department approvers have Create hidden through `canUserCreateGasSlip`, but the create page/save endpoint do not enforce that helper; this is a server/UI capability mismatch.

Assignment validation is server-side. Route-area selection is area-filtered by `get_destinations.php`, but the save handler extracts positive route IDs from posted JSON without validating their area/active eligibility. It also accepts posted fuel amounts without recalculation and takes slip area from the session instead of refreshing it from `users`. These are submission integrity gaps, distinct from recommender assignment validation.

### Initial visibility and notifications

| Vehicle ownership | Current detail visibility for non-drafts | Initial notification recipients |
|---|---|---|
| Coop-owned | Creator; eligible assigned primary/delegate while pending; operational approvers under the shared view rule; admin; persisted workflow participants; other qualifying completed-slip rules | Eligible assigned primary plus active eligible delegated secondary |
| Private | Creator; creator-department approvers; configured global private approver; admin; persisted workflow participants | All `department_approvers` for creator department |

Thus private slips are not restricted to creator plus selected regular recommender. The configured final private approver may view a pending private slip even though they cannot recommend it. Notification recipient selection is broader than action permission in some cases (section H).

## D. Pending Visibility Flow

For `$_SESSION['is_recommender']`, `includes/pending/query.php:33` calls `recommenderPendingSlipSql($userId)` for both pagination count and rows. The actual outer queries are:

```sql
SELECT COUNT(*) AS total
FROM gas_slips gs
WHERE <recommenderPendingSlipSql(actor)>;

SELECT gs.id AS gs_id, gs.gas_slip_id, gs.date_issued,
       gs.requested_by, gs.purpose, gs.status, gs.validity_until, gs.printed_at,
       CONCAT(MIN(r.origin), ' → ',
         GROUP_CONCAT(r.destination ORDER BY gsr.id SEPARATOR ' → ')) AS route_path
FROM gas_slips gs
LEFT JOIN gas_slip_routes gsr ON gsr.gas_slip_id = gs.id
LEFT JOIN routes r ON r.id = gsr.route_id
WHERE <recommenderPendingSlipSql(actor)>
GROUP BY gs.id
ORDER BY gs.date_issued DESC
LIMIT ?, ?;
```

The helper's exact structure (`includes/gas_slip_access.php:71`) is:

```php
$recommend = gasSlipPermissionSql('recommend', $id);
$approve = gasSlipPermissionSql('approve', $id);
return "((gs.status = 'pending' AND $recommend)
    OR (gs.status = 'recommended' AND $approve)
    OR (gs.status = 'rejected' AND gs.user_id = $id))";
```

Each permission expands to `EXISTS` over an **active database actor**, joined to the creator and vehicle by `gs.user_id` and `gs.vehicle_id`. Recommendation is:

```text
pending AND (
  ownership <> private AND eligible current assigned primary/delegate
  OR ownership = private AND creator-department approver AND NOT global-private-approver
)
```

Approval is:

```text
recommended AND recommended_by IS NOT NULL AND recommended_by > 0
AND recommended_by <> actor AND (
  private AND global-private-approver AND department-approver membership in actor's department
  OR coop-owned AND NOT global-private-approver
     AND creator-department approver AND creator area belongs to department_areas
)
```

Assignment SQL requires the creator's `users.assigned_recommender_id`, matching recommender membership/department, active primary and area eligibility. For delegation, date/status and primary/secondary same-area/department conditions also apply. Current primary eligibility uses **creator `users.area_id`**; the completed-slip current-recommender view rule uses **`gs.area_id`**. Changes to creator profile area can therefore affect stages differently.

| Input | Effect on recommender Pending |
|---|---|
| Logged-in ID | Determines actor, assignment/delegation and approval rights |
| Creator ID | Joins current creator profile; direct ownership only includes rejected slips |
| Assigned recommender | Current creator-profile assignment; controls non-private recommendation |
| Department | Required for recommender membership and department approval |
| Area / multi-area rows | Required for non-private assigned recommendation; operational approval uses creator area in `department_areas` |
| Private visibility flag | None; vehicle ownership switches workflow |
| Status | Pending/recommended action branches, plus creator-owned rejected branch |

Custom Requested By tabs replace the default rows with `bulkRecords(...,'auto',group)` through `includes/bulk_table_query.php`. They include only recommend/approve-authorized rows and group-name matches, not all creator-visible records. Foreign/invalid groups fail closed. They also exclude creator-owned rejected entries that may appear in the default Pending list.

Sidebar Pending counts use the same helper (`includes/sidebar.php:115`). The dashboard Pending card displays those counts. The creator omission therefore propagates consistently into the badge/card; fixing only the page's SELECT would create a count mismatch. Bulk tab counts are action counts and should not be treated as a total of all creator-visible Pending entries.

Recommended history for recommenders is `gs.recommended_by = actor AND status='recommended'`, plus view/group scope. Approved history is `gs.recommended_by = actor AND status IN ('approved','printed')`, plus view/search/date/group scope. Neither necessarily includes a recommender's own slips handled by someone else, even though detail-view permission permits ownership. The sidebar approved count uses status `approved` only, whereas the approved page can include printed slips; this is an existing count/list semantic difference.

## E. Recommendation Flow

`assets/js/gas-slip.js` loads the shared detail endpoint, whose `$canRecommendSlip` and `$canApproveSlip` are independent server permission queries. Confirmation POSTs invoke `recommend_gas_slip.php` and `approve_gas_slip.php`. Creator-visible details do not enable an action by themselves.

For current individual recommendation:

1. The endpoint checks session identity/role presence and a posted slip ID, then calls `recommendGasSlipWorkflow`.
2. The function starts a READ COMMITTED transaction and locks the creator department to serialize recommender eligibility changes. It verifies an active actor and loads creator/ownership context.
3. Non-private handling requires primary or active secondary capability and `canAccessAssignedRecommender` for this creator. Department/area membership alone is insufficient. A creator assigned to themselves can recommend their own non-private slip: there is no creator-versus-recommender separation rule.
4. Private handling requires a current creator-department approver who is not the global private approver. An ordinary assigned primary cannot recommend it, including their own private slip.
5. The final UPDATE repeats `status='pending' AND gasSlipPermissionSql('recommend',actor)`. Success requires affected rows. A previously recommended/approved/rejected/draft slip cannot be recommended again through this handler without an intervening permitted state change.
6. Normal handling persists `status='recommended'`, `recommended_by`, `recommended_at`, then notifies approvers. Recommender-only handling is covered in section F.

### Bulk and Requested By groups

`bulk_processing.php` validates page-specific CSRF, session token against an active database user, and database workflow membership. `bulkRecords` applies the same recommendation/approval SQL before group filtering. Groups and names are scoped to `owner_user_id`; a Requested By match cannot add authority to a slip.

Review creates an expiring session snapshot of IDs, eligible universe, names, statuses and workflow. Execution consumes the token, locks department/slip/actor as appropriate, rechecks membership/eligibility and calls the shared individual functions inside a per-record transaction. Success/skip/failure is reported separately. Bulk requires at least two records; individual details remain the path for one. External pushes are deferred until commit. Static inspection found no group filter replacing record authority. Execution and concurrency behavior remain unverified because the tests require writes.

## F. Approval Flow

**Normal primary settings prevent assigning a permanent recommender as a department approver.** `save_primary_approver.php` and `save_secondary_approver.php` reject existing department recommenders; the recommender candidate helper excludes department approvers and active secondary delegates. However, login flags are independent and the runtime permission model supports overlapping capabilities if assignments/delegations yield them. It does not universally reject an actor merely for having `is_recommender` set.

The current `approveGasSlipWorkflow` first checks the database `approve` permission, verifies the caller's department-approver record, determines primary/secondary type and executes another permission-constrained UPDATE. A regular recommender without department approval authority cannot approve by directly calling this endpoint. An actor with valid approval capability may approve a slip recommended by another actor, subject to ownership/department/area scope. A slip they already recommended fails `recommended_by <> actor`; missing/zero original recommender also fails closed. These checks use persisted original recommendation identity, not whoever is currently assigned.

However, the settings writers do not adequately protect those authoritative assignments. `save_department_approver.php` accepts a department/target user from any session with a user ID; it does not enforce the permanent-recommender exclusion. `save_private_vehicle_approver.php` likewise allows a logged-in caller to change the global private approver. `save_primary_approver.php` and `save_secondary_approver.php` validate the candidate but do not verify that the calling user may manage approvers. `save_recommender_only_setting.php` checks department-area membership but not caller authority to change approval policy. Thus a recommender can potentially grant themselves approval capability or enable the existing automatic-approval shortcut through separate unguarded requests. This is a critical authorization defect, not a legitimate dual-role policy (H12).

**Intentional exception:** when `department_approval_settings.recommender_only=1` for the creator department/slip area and ownership is non-private, recommendation immediately sets `status='approved'`, both actor IDs, both timestamps and `approver_type='recommender'`. This is existing policy, not a defect to remove. Private vehicles never use that shortcut. A slip already in `recommended` is evaluated by the independent-approval rules; changing the setting does not retroactively bypass that stage.

Rejection uses `recommend OR approve` permission in the conditional UPDATE and requires a reason; it records rejector/time/reason. Normal approval and recommendation record actor/time, but these fields are mutable state rather than an append-only event history.

The supported recovery route is creator withdrawal of pending/rejected → draft, owner-only draft editor → resubmission pending. Withdrawal clears `recommended_by`, approval identity/time and rejection identity/time, but leaves some related metadata (for example `recommended_at` and rejection reason). It deletes obsolete lifecycle notifications. A rejected recommended slip can subsequently be withdrawn/resubmitted; the current creator assignment then governs the next non-private recommendation, so the original recommender is not guaranteed to receive it again. Approval/rejection notifications go to the creator, not automatically to the former recommender.

There is no supported withdrawal from `recommended` back to Pending. **The direct save endpoint nevertheless allows an owner to reset any owned status to pending**, because its edit UPDATE lacks a status condition. It does not clear the prior recommendation/approval/rejection metadata. This bypass and the foreign-child-record problem in section H undermine the intended transitions even though the normal editor is draft-only.

## G. Private Gas Slip Bug

### Confirmed root cause

`includes/pending/query.php` selects the recommender branch first. Its shared helper at `includes/gas_slip_access.php:71` has no creator-owned pending/recommended alternative. A creator with recommender capability therefore loses the ordinary-preparer ownership list behavior. For a private slip, regular recommender assignment gives neither creator nor assignee recommendation permission, so it cannot rescue the missing row.

Executed read-only fixture results:

| Private slip created by A, creator assigned to B | View permission | Pending scope | Recommend permission |
|---|---:|---:|---:|
| A, ordinary recommender | 1 | 0 | 0 |
| B, selected ordinary recommender | 0 | 0 | 0 |
| Same-department approver | 1 | 1 | 1 |
| Global private approver with department approval membership | 1 | 0 | 0 |

For a private slip created by B and assigned to B, B similarly has view=1, Pending=0, recommend=0. For A's coop-owned slip assigned to B, A has view=1/Pending=0/recommend=0, while B has all three=1.

### Minimum proposed correction, not implemented

Broaden the shared recommender Pending **display** scope to include appropriately active, creator-owned pending/recommended entries, retaining creator-owned rejected entries and current actor-action branches. Keep `gasSlipPermissionSql('recommend'/'approve')` unchanged. Review normal-preparer-compatible status handling and count parity. Include an active-actor/view constraint rather than creating an unguarded ownership branch. Keep bulk action eligibility separate; decide explicitly whether custom tabs should also display creator-only rows without making them actionable.

Files implicated: `includes/gas_slip_access.php`, its Pending query/sidebar consumers, and custom-tab display/count logic if that display scope is included. `recommended_gas_slips.php` and `approved_slips.php` also need review if creators are intended to retain list access after moving out of Pending.

Allowing selected regular recommender B to recommend private slips would require an explicit **approval-policy change** in shared permissions, workflow and notifications. It is not part of this visibility-only correction and was not implemented or assumed authorized.

## H. Security Findings

| ID / severity | Finding and evidence | Verification boundary |
|---|---|---|
| H1 Critical | `recommend_gas_slip_09022026.php` checks session user/role presence, then updates any matching pending slip without assignment, department, ownership or actor-status authorization. `recommend_gas_slip_09042026.php` likewise has no current authority gate; its recommender-only branch can set approved with both actor IDs. | Confirmed source defect. No POST executed. HTTP exposure depends on external server rules; no repository `.htaccess` was found. |
| H2 Critical | `save_gas_slip.php:239` uses `WHERE id=? AND user_id=?` but does not verify the update matched/authorized a record before unconditionally deleting/replacing routes and fuel for the submitted slip ID (`:268`, `:279`). Another user's header can remain untouched while their children and pending notifications are modified. | Confirmed control-flow defect; exploitation requires an authenticated caller with eligible assignment and valid submission values. Not executed. |
| H3 Critical | `view_gas_slip_modal_08272026.php:13` reads a slip by ID and then its details without a session-identity or record-view guard. Session start alone is not authentication. This can bypass private-slip visibility. | Confirmed source defect. No HTTP request; external deployment restrictions unverified. Other dated variants require the same inventory review. |
| H4 High | Direct owned edit has no draft/status guard and resets recommended/approved/printed slips to pending; historical actors/timestamps/printed metadata are retained. | Confirmed source defect at `save_gas_slip.php:239`; mutation not executed. |
| H5 High | Creator-owned pending/recommended entries omitted from recommender Pending. Private assignee expectations also conflict with current ownership workflow. | SQL omission reproduced with executable read-only fixtures. Private policy mismatch is separately identified, not silently treated as a fix requirement. |
| H6 High | Individual workflow/create/withdraw endpoints lack the bulk endpoint's consistent active session-token validation and CSRF checks. Presence checks and active-actor SQL do not establish that the session token remains current. Remember bootstrap is navigation/cookie-dependent; maintenance validates actor only while maintenance is active. | Source-confirmed missing checks. Session replay/CSRF exploitation not executed; cookie/browser protections can affect exploitability. |
| H7 High | Save accepts route IDs without validating allowed area/active scope, posted fuel quantities without server recomputation, and session area without current-profile consistency. | Source-confirmed validation gaps. Cross-area and quantity tampering not submitted. |
| H8 Medium | Creator-profile assignment is mutable across all their pending slips, whereas notifications are created once for the assignment at submission. Reassignment does not reconcile old pending notification recipients. `ajax/get_notifications.php` checks recipient identity only, not current slip-view rights. | Source tracing; actual stale-notification instances were not queried. Potential exposure is notification text/slip number, even when current detail view is denied. |
| H9 Medium | Notification fan-out is broader than current action rights: private creation includes a department approver who is also the global private approver; normal recommendation can notify its own author if that person also has approver membership, or other recipients excluded by approval scope. | Source comparison with permission SQL; actual recipient sends not executed. |
| H10 Medium | Single recommendation commits state before lifecycle notification work. Single approval/rejection do not wrap state and notifications in one explicit transaction. Notification failure can leave persisted state without matching notifications. Creation can send external pushes before its overall transaction commits; bulk defers them correctly. | Static transaction tracing; failure injection prohibited by read-only scope. |
| H11 Medium | Create is hidden for department approvers but direct create/save do not enforce `canUserCreateGasSlip`. Recommended/approved recommender history is actor-based, omitting creator-only entries; printed/approved badge semantics differ. | Source-confirmed UI/server and list/count differences; browser flow not executed. |
| H12 Critical | Approval settings lack server-side manager authorization: `save_department_approver.php` can replace a posted department's approver from any logged-in session; `save_private_vehicle_approver.php` can replace the global private approver. Primary/secondary writers check candidate restrictions but not caller management rights. `save_recommender_only_setting.php` permits any session user with a department to toggle the shortcut for an area in that department. This can change the very database permissions trusted by current action handlers. | Entire writers inspected; no role changes or settings POSTs executed. `assets/js/settings.js:988` still references the department writer. Exact exploit chain depends on actor/target department and slip stage; persisted self-recommendation still fails normal independent approval. |

Current guarded UPDATEs prevent duplicate normal recommendation/approval for the same stage through affected-row checks. They do not repair legacy routes or the save bypass. No current Requested By group authorization bypass was found in source. No blanket privacy guarantee is possible: admins, workflow approvers, persisted participants, and authorized monitoring of approved/printed records are broader existing view entitlements.

### Multi-area behavior

- ANOD/ASOD primary recommender eligibility requires explicit `recommender_area_assignments` rows; the same recommender can have multiple areas. Other departments use actor primary area.
- Adding areas does not itself authorize every slip in them: creator assignment still must match, and private recommendation remains approver-based.
- Area membership removal can hide formerly actionable pending slips because assignment eligibility is evaluated live. It never supplies a creator ownership fallback to the recommender Pending helper.
- Settings submission of an empty area list restores a valid department primary area; it fails if no valid primary area exists. There is **no runtime fallback** if all rows are removed outside that supported save path. Deleting the recommender removes membership/area rows; stored creator assignments can remain invalid until a valid selection is made.
- Delegation retains stricter same-primary-area restrictions, so multi-area access of a primary is not automatically delegated to every assigned area.
- Assignment/current creator-area changes do not rewrite persisted `recommended_by`; normal independent approval remains tied to that original identity.

## I. Scenario Test Matrix

### Executed method

Ran the actual unmodified `gasSlipPermissionSql` and `recommenderPendingSlipSql` functions against MariaDB using SELECT-only CTE fixtures inside a read-only transaction. The CTEs shadowed all policy tables; no fixture was inserted. Actors: A=10, B=11, C=12 as active ANOD recommenders; D=30 as creator-department approver; P=40 as global private approver with own-department approver membership. A had explicit areas 1 and 2; B/C area 1. A's profile assignment was B; a second-area preparer was assigned A. Six synthetic slips covered private self/other creation, coop creation, unrelated private, persisted recommendation and second-area pending.

**PASS below applies only to the expressly executed SQL visibility/permission assertions.** Creation, mutation, browser delivery and concurrency were not executed and remain NEEDS VERIFICATION. FAIL can also designate a statically demonstrated defect; that evidence is expressly labeled.

| Requested scenario | Result | Executed result / limitation |
|---|---|---|
| A creates private slip assigned to B; A and B see it, B recommends | **FAIL** | Post-creation policy fixture: A detail=1/Pending=0/action=0; B detail=0/Pending=0/action=0. D is the permitted recommender. Creation POST not run. B action expectation conflicts with current private policy. |
| A creates private slip assigned to A; A sees/recommends | **FAIL** | Equivalent B self-assigned private fixture: creator detail=1/Pending=0/action=0. Existing policy denies ordinary recommender private recommendation. |
| A creates normal slip assigned to B; B recommends | **PASS — permission scope only** | A detail=1/action=0, B detail=1/Pending=1/recommend=1. Actual create/recommend transition: **NEEDS VERIFICATION**. A still misses its Pending entry. |
| A views another user's private slip assigned to C | **PASS — permission scope only** | A detail=0/Pending=0/recommend=0. C also has no private authority merely from assignment. HTTP denial and legacy URL privacy: **NEEDS VERIFICATION**; H3 is a source-confirmed bypass path. |
| A recommends assigned slip and it moves to approval | **NEEDS VERIFICATION** | A second-area assigned pending fixture grants recommendation. Source conditionally persists recommended and notifies approvers, except configured recommender-only shortcut. No UPDATE executed. |
| A tries recommending slip assigned to B, may view as creator | **PASS — permission scope only** | Own coop fixture: view=1/recommend=0. End-to-end current POST denial: **NEEDS VERIFICATION**. Legacy endpoints H1 lack this guarantee. |
| A tries approving without approval authority | **PASS — permission scope only** | A persisted-recommended fixture has approve=0; D has approve=1. Current endpoint mutation/denial not exercised. |
| A has multiple assigned areas; only assigned authorized slips | **PASS — permission scope only** | A can view/recommend assigned second-area coop slip; B/C cannot. A cannot view unrelated private slip. Removal/default/delegation mutations: **NEEDS VERIFICATION**. |
| Creator visibility does not confer recommendation | **PASS — permission scope only** | Own private and own B-assigned coop fixtures both show view=1/recommend=0. |
| Same author cannot approve normal persisted recommendation with dual capability | **NEEDS VERIFICATION** | A recommended fixture yields approve=0, D=1, but A has no approver membership in this fixture. The explicit SQL inequality was inspected; a dual-capability execution was not performed. |
| Repeated recommendation/approval | **NEEDS VERIFICATION** | Pending/recommended predicates and affected-row checks inspected; repeated/concurrent mutation tests not run. |
| Bulk/custom-group authorization and stale review | **NEEDS VERIFICATION** | Shared scope, ownership, snapshot and per-record rechecks inspected; no bulk POST/group writes run. |
| Legacy recommendation authorization | **FAIL — static defect** | Two dated handlers contain unscoped updates. External URL reachability and exploit outcome untested. |
| Foreign edit cannot replace child records | **FAIL — static defect** | Ownership-constrained header update is followed by ID-only child deletes without matched-row check. No destructive request run. |
| Recommender cannot change approval authority/policy without management permission | **FAIL — static defect** | Assignment and recommender-only setting writers lack caller-management authorization (H12). No assignment/settings write executed. |

### Tests still required in an explicitly authorized disposable environment

Execute create/view/recommend/approve/reject/withdraw/resubmit for ordinary preparer, primary/delegated recommender, department approver, global private approver and overlapping capabilities. Cover both ownership types, self and other assignment, ANOD/ASOD multiple areas, area removal/default restoration, reassignment, delegation expiry, stale/revoked tokens, CSRF, foreign edit IDs, out-of-area routes and repeated/concurrent processing. Exercise default/custom tab counts, creator-only rows, history pages, notification cleanup and delivery failures. Verify every dated route is inaccessible or forwards to current guards. Existing test suites require fixture/schema writes and were deliberately not run.

## J. Recommended Fixes

These are proposals only. No implementation or policy changes were made.

| Priority | Minimum necessary correction / files | Regression risks |
|---|---|---|
| **Critical** | Enforce current database manager authority, active session tokens, CSRF, allowed target department/area and intended candidate separation in all approval settings writers: `save_department_approver.php`, `save_private_vehicle_approver.php`, `save_primary_approver.php`, `save_secondary_approver.php`, `save_recommender_only_setting.php`. | Preserve the existing authorized management policy and recommender-only behavior; do not rely on hidden Settings controls. Protect every alias writer so an older path cannot bypass the corrected one. |
| **Critical** | Replace dated recommendation handlers with current-handler forwarding or remove their HTTP exposure: `recommend_gas_slip_09022026.php`, `recommend_gas_slip_09042026.php`. Inventory other dated/backup routes. | Preserve required old URLs, current private workflow and recommender-only shortcut; do not duplicate policy logic. |
| **Critical** | Before edit child writes, lock and authorize the target as an owned editable slip, enforce allowed status and stop on unauthorized/no-match: `save_gas_slip.php`. Keep child replacement within the authorized transaction. | Affected-row=0 can also mean unchanged values; use an explicit authorized locked lookup rather than relying only on changed-row semantics. Preserve valid owner draft resubmission. |
| **Critical** | Forward/remove exposed older detail handlers, starting `view_gas_slip_modal_08272026.php`; require current detail-view policy for every reachable detail URL. | Preserve required legacy response formats; avoid breaking current modal/print links. |
| **High** | Add creator ownership to the shared recommender Pending display policy while retaining independent action permissions: `includes/gas_slip_access.php`; review `includes/pending/query.php`, `includes/sidebar.php`, `includes/bulk_table_query.php`, `includes/bulk_processing.php` and tab UI for display/count consistency. | Do not make creator-only rows bulk actionable or expose unrelated private slips. Preserve normal-preparer queries. Private B recommendation needs a separate policy decision, not a visibility patch. |
| **High** | Enforce draft-only editable state and coherent metadata on supported resubmission: `save_gas_slip.php`, `withdraw_gas_slip.php`; review normal editor constraints. | Preserve original workflow identities/audit meaning and printed/approved immutability. Avoid changing independent approval policy. |
| **High** | Reuse a consistent active session-token guard and CSRF validation across individual actions and submission/withdrawal: action endpoints, `includes/auth.php` or a shared guard, `assets/js/gas-slip.js`, creation form. | Remember-login restoration, AJAX expiry responses and existing forms must continue working; source capability checks remain necessary. |
| **High** | Validate submitted route IDs/area/active scope and fuel values server-side and refresh area context; enforce existing creation capability server-side: `save_gas_slip.php`, creation entry point/shared helpers. | Preserve valid saved custom/fixed destinations and normal preparer behavior; define recalculation rules before rejecting valid historical values. |
| **Medium** | Reconcile or suppress obsolete notifications when assignment changes; filter action prompts by matching permission and review retrieval: `includes/notifications.php`, assignment-changing handlers, `ajax/get_notifications.php`. | Do not lose creator outcome notifications or duplicate/re-send completed events. |
| **Medium** | Make lifecycle database state and notification insertion atomic and defer external pushes until successful commit in individual/create paths, following the existing bulk pattern. | Notification failure semantics and retries must avoid duplicate action execution. |
| **Medium** | Clarify creator history access and align printed/list badge semantics where intended: `recommended_gas_slips.php`, `approved_slips.php`, `includes/sidebar.php`, bulk history counts. | History visibility and actionable queues serve different purposes; expanding a list must retain record-level view authorization. |
| **Low** | Explain the private-vehicle routing and current creator-profile assignment in the submission UI/documentation. | Avoid suggesting that ordinary selected recommenders acquire private recommendation authority. |

Inspection complete. Await authorization before implementing corrections or running tests that write data.
