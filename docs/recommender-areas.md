# Multi-area recommender assignments

ANOD and ASOD primary department approvers manage Assigned Areas in the existing Settings > Department Recommenders card. The Add Recommender modal only lists eligible users (Name, Designation, Area). Initial registration automatically assigns the user?s valid primary area; each existing card row displays compact area badges and an Edit Areas button. The Bootstrap editor prechecks saved assignments and provides search, Select All, Clear All, a selected count, and a scrollable checkbox list. Bulk actions include filtered-out areas. Save Changes uses the existing endpoint, shows a loading state, and updates badges without a reload. Cancel discards unsaved edits. Saving zero areas restores the recommender's validated primary area on the server and shows a success message, without a confirmation prompt. Nonempty selections may exclude the primary area. Missing or invalid default areas reject the save and preserve existing assignments. A missing, nonexistent, or foreign-department primary area blocks initial registration with a validation message. Repeated Add requests preserve all saved assignments, including removal of the primary area when other areas remain. The Add endpoint ignores submitted area selections; multiple-area changes belong exclusively to Edit Areas.

Authorization reuses `department_approvers.is_primary`, active user status, department membership, and the approver's configured `department_areas` scope. The admin role alone grants no management authority in ANOD/ASOD. Every area mutation checks the authenticated approver configuration, recommender registration and department membership, area membership, and a session CSRF token. Existing registration/removal endpoints also require that token. Other departments retain their previous manager rules and single-area eligibility.

Eligibility uses an indexed mapping existence check plus active user status, same user department, and department recommender registration. Area assignments never replace `users.assigned_recommender_id`. Existing valid selections are reused, and invalid selections follow the existing reselection behavior. No selection popup was changed. Primary access, pending lists/counts, and submission use the updated rules. Secondary delegation retains its original primary/secondary/preparer area and department restrictions; new primary areas do not expand an existing delegate's access.

Removing an area revokes both new eligibility and access to affected pending slips, following existing current-eligibility authorization. Slips are never transferred. Saved selections and recommendation/approval history remain intact. Restoring an area can restore access; any reassignment requires the existing explicit preparer selection action. Department row locks serialize area edits, recommender registration/removal, submission validation, and recommendation execution. Recommendation updates retain the conditional `status = 'pending'` check.

## Deployment

1. Back up the database and take the application offline for coordinated migration and code deployment. Do not deploy eligibility code before the migration is applied.
2. Use PHP CLI with mysqli and explicit target database environment variables. The migration does not load the application's database config. `GSLIP_MIGRATION_HOST`, `GSLIP_MIGRATION_USER`, and `GSLIP_MIGRATION_PASSWORD` are optional (defaults: localhost/root/empty).
3. First run against a restored database copy:

```powershell
$env:GSLIP_MIGRATION_DATABASE = 'your_restored_copy'
php migrations/20261103_recommender_areas.php
php migrations/20261103_recommender_areas.php --apply
php migrations/20261103_recommender_areas.php --apply
```

The first command is read-only preflight and prints schema-derived SQL. It checks required tables and InnoDB and derives foreign key types from actual parent IDs. The apply command creates `recommender_area_assignments` and a `gslip_schema_migrations` ledger. The ledger and initial seed commit together. Initial seeding preserves registered recommenders' primary-area eligibility (including inactive registrations); reruns never re-add deliberately removed assignments. No users, department recommender registrations, or gas slips are rewritten. If a table with the same mapping/ledger name already exists, inspect its structure before applying; `CREATE TABLE IF NOT EXISTS` does not repair incompatible tables.

4. Review the copy's eligibility and UI using the scenarios below. After approval under your normal deployment process, repeat with the explicitly selected deployment database, deploy these files together, and reopen the app. Reload existing Settings pages to obtain the CSRF token.
5. Rollback: restore the previous application code; retain the additive mapping and ledger tables. Mapping changes do not rewrite `users.area_id`, so old code resumes its original primary-area policy.

## Validation

`php tests/recommender_areas.php` creates a uniquely named disposable MySQL database, runs migrations twice and exercises real shared SQL/functions, then drops only that fixture database. It requires local root database-create privileges; it never uses the configured application database. Tests cover ANOD multi-area, ASOD isolation, multiple choices without duplicate rows, selected-primary-only access, approver scope, admin-without-authority rejection, saved assignment reuse, same-area delegation, removed-area access, empty-selection primary fallback, invalid foreign areas, deduplication, migration preservation/reruns, and unaffected ISD primary-area behavior.

Executed successfully: integration assertions, read-only preflight against the configured local database, PHP syntax checks, JavaScript syntax check, and whitespace diff review. Application database migration was not executed.

Before deployment, perform these authenticated browser/HTTP checks on a restored copy:

- A/B/D: assign Roxas/Taytay/El Nido to A, Roxas to B; confirm the existing popup shows both only when selection is required; save A and submit again without reselection. Only A can see/recommend the pending slip.
- C: use an ANOD approver session to POST an ASOD department/recommender/area to all three mutation endpoints; expect rejection. Repeat as a recommender, an admin without approver authority, and with a missing/incorrect CSRF token. Repeat valid edits on mobile and desktop.
- E: remove Taytay; confirm new selection and pending recommendation fail, history and saved IDs remain unchanged, and no slip transfers. Try simultaneous area removal and slip submission/recommendation; one operation must wait for the department lock.
- F: verify active, expired, cancelled, wrong-area and wrong-department delegates. A primary's additional areas must not expand same-area delegation access.
- G: exercise recommender-only departments, private vehicles, normal approval/rejection, notification recipients, printing and QR verification. These end-to-end workflows and browser layout were not automated or executed in this implementation session.
- H: apply twice to the restored database, compare user/slip/history counts and contents, remove a primary-area mapping, and apply again; it must remain removed.

## Changed files

New: `includes/recommender_areas.php`, `save_recommender_areas.php`, `migrations/20261103_recommender_areas.php`, `tests/recommender_areas.php`, this document.

Updated: `settings.php`, `assets/js/settings.js`, `includes/settings/assignment_scope.php`, `includes/settings/load_recommenders.php`, `includes/settings/cards/recommender_card.php`, `includes/modals/recommender_modal.php`, `save_recommender.php`, `delete_recommender.php`, `includes/recommender_assignment.php`, `includes/pending/query.php`, `includes/sidebar.php`, `save_gas_slip.php`, `recommend_gas_slip.php`.

`recommender_settings.php` was inspected and remains the existing primary-managed secondary-delegation page; area management belongs to the approver's existing Settings card.

## Area editor UI validation (November 3, 2026)

UI-only follow-up files: `includes/settings/cards/recommender_card.php`, new `includes/modals/recommender_areas_modal.php`, new `assets/js/recommender-areas.js`, `assets/js/settings.js` (removed the old inline form handler), `settings.php` (modal/script includes), new `tests/recommender_areas_ui.cjs`, and this document. The assignment endpoint, authorization helpers, schema, preparer selection, recommendation, delegation, and approval workflows were not changed in this follow-up.

Executed `php tests/recommender_areas.php`: all 17 integration checks passed, including approver department isolation, invalid department area rejection, deduplication and multi-area persistence. PHP syntax checks for all changed PHP files and JavaScript syntax checks passed.

Executed `node tests/recommender_areas_ui.cjs` using Playwright installed outside the repository and system Chrome. The fixture renders the actual PHP card/modal and loads the existing Bootstrap/application styles and scripts. Browser assertions passed for existing badges/prechecks, search/filter and count, multi-area submission, loading state, immediate badge updates, reopening, cancel, empty-selection fallback, saved badges/count and reopen, Select All, no-results feedback, rejected-save feedback, and scrollable layouts at 375px and 320px. No browser JavaScript errors occurred. The 320px screenshot was visually inspected. Saves in this browser fixture are intercepted; actual authorization and persistence are covered separately by the isolated MySQL integration suite. A complete authenticated live-application browser run was not performed.

To rerun browser checks, install Playwright in a temporary external directory, set `GSLIP_UI_PLAYWRIGHT` to its `node_modules/playwright` directory, and run `node tests/recommender_areas_ui.cjs`. Chrome must be installed. No runtime dependencies were added to GSLIP.

## Add Recommender workflow correction

Removed the area selector/helper from `includes/modals/recommender_modal.php` and area submission from `assets/js/settings.js`. `save_recommender.php` now calls `registerDepartmentRecommender()` in `includes/settings/assignment_scope.php` after the existing CSRF, department authorization, transaction and department lock checks. Registration locks the selected user, confirms department membership, and returns without writes for an existing registration. A new ANOD/ASOD registration validates the primary area against the department?s existing area configuration, then inserts the registration and unique default mapping atomically. Failures roll back. Other departments retain their existing registration behavior. No migration or schema change is needed. Existing saved area loading, preparer selection, delegation and recommendation/approval logic are unchanged.

Validation: the isolated MySQL integration suite now includes new-registration primary seeding, duplicate submissions, additional saved areas, removed-primary preservation and empty-selection fallback, missing/foreign/nonexistent primary area errors, foreign-user rejection, and rollback comparison of existing records. Browser fixtures also check the Add modal retains all three user columns without a selector and Edit Areas prechecks a single saved default area. Browser saves remain intercepted; integration checks exercise the real registration and area persistence functions on a disposable database.

## Empty-selection fallback

`replaceRecommenderAreas()` now validates and restores `users.area_id` only for an empty submitted selection. It rejects a missing, nonexistent, or foreign-department primary area before deleting existing mappings. The existing department/user locks and transaction keep the replacement atomic. This applies to every save through the existing area endpoint; no schema migration or bulk rewrite of pre-existing assignments is performed.

The save endpoint returns actual saved areas, a fallback flag, and a message naming the default area. The editor uses this response to update badges, checkbox state, selected count, and reopening state. The old remove-all confirmation was removed. Database authorization, CSRF, nonempty area validation, recommendation/delegation/approval, and historical slip handling are unchanged.

Validation: 39 isolated database integration assertions passed, including persisted fallback, eligibility after fallback, primary exclusion with other selected areas, invalid-default rejection without data loss, and prior department restrictions. Browser fixtures passed multiple-area saving, clear-and-save with no confirmation, default-area success message, updated badge/count, reopening with the default checked, rejected saves, and 320px/375px layouts. Browser responses are intercepted; database persistence is exercised separately by the isolated MySQL suite.
