<!-- =========================================================
    RECOMMENDER SETTINGS
========================================================= -->
<div class="col-md-6">

    <div class="card shadow-sm border-0 h-100">

        <div class="card-body d-flex flex-column">

            <!-- HEADER -->
            <div class="d-flex justify-content-between align-items-center mb-3">

                <div>
                    <h5 class="mb-1">
                        <i class="fas fa-user-edit text-primary me-2"></i>
                        Department Recommenders
                    </h5>

                    <small class="text-muted">
                        Users allowed to recommend gas slips.
                    </small>
                </div>

                <?php if ($role === 'admin'): ?>
                <form method="get" class="d-flex gap-1 align-items-center">
                    <select
                        name="recommender_department_id"
                        class="form-select form-select-sm"
                        aria-label="Recommender department"
                        onchange="this.form.submit()"
                    >
                        <?php foreach ($departments as $departmentRow): ?>
                            <option
                                value="<?= (int) $departmentRow['id']; ?>"
                                <?= (int) $departmentRow['id'] === (int) $recommenderDepartment
                                    ? 'selected'
                                    : ''; ?>
                            >
                                <?= htmlspecialchars($departmentRow['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                </form>
                <?php endif; ?>

                <button
                    type="button"
                    class="btn btn-primary btn-sm"
                    data-bs-toggle="modal"
                    data-bs-target="#recommenderModal"
                    data-recommender-department-id="<?= (int) $recommenderDepartment; ?>"
                    <?= !$canManageRecommenders ? 'disabled' : ''; ?>
                >
                    <i class="fas fa-plus me-1"></i>
                    Add
                </button>

            </div>

            <!-- TABLE -->
            <div class="table-responsive flex-grow-1">

                <table class="styled-table excel-table approval-table mb-0">

                    <thead>
                        <tr class="group-header">
                            <th width="60">#</th>
                            <th>Recommender</th>
                            <th width="90">Action</th>
                        </tr>
                    </thead>

                    <tbody id="recommenderTableBody">

                        <?php if (!empty($currentRecommenders)): ?>

                            <?php
                            $counter = 1;

                            foreach ($currentRecommenders as $userRow):

                                $middleInitial = '';

                                if (!empty($userRow['middle_name'])) {
                                    $middleInitial =
                                        strtoupper(substr($userRow['middle_name'], 0, 1)) . '.';
                                }

                                $fullName = trim(
                                    $userRow['last_name'] . ', ' .
                                    $userRow['first_name'] . ' ' .
                                    $middleInitial
                                );
                            ?>

                            <tr
                                data-user-id="<?= $userRow['id']; ?>"
                                data-area="<?= htmlspecialchars($userRow['area_name'] ?? '-'); ?>"
                            >

                                <td class="text-center fw-semibold">
                                    <?= $counter++; ?>
                                </td>

                                <td>

                                    <div class="fw-semibold text-uppercase">
                                        <?= htmlspecialchars($fullName); ?>
                                    </div>

                                    <?php if (!empty($userRow['designation'])): ?>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($userRow['designation']); ?>
                                        </small>
                                    <?php endif; ?>

                                    <?php if ($multiAreaRecommenders): ?>
                                    <div class="mt-2">
                                        <div class="small text-muted mb-1">Assigned Areas</div>
                                        <div class="recommender-area-badges d-flex flex-wrap gap-1" aria-live="polite">
                                            <?php $hasAssignedArea = false; ?>
                                            <?php foreach ($recommenderAreas as $areaOption): ?>
                                                <?php if (in_array((int) $areaOption['id'], $assignedAreas[(int) $userRow['id']] ?? [])): $hasAssignedArea = true; ?>
                                                <span class="badge rounded-pill bg-primary text-wrap"><?= htmlspecialchars($areaOption['area_name']); ?></span>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            <?php if (!$hasAssignedArea): ?><span class="small text-muted">No assigned areas</span><?php endif; ?>
                                        </div>
                                        <?php if ($canManageRecommenders): ?>
                                        <button type="button" class="btn btn-outline-primary btn-sm mt-2 edit-recommender-areas"
                                            data-bs-toggle="modal" data-bs-target="#recommenderAreasModal"
                                            data-user-id="<?= (int) $userRow['id']; ?>"
                                            data-user-name="<?= htmlspecialchars($fullName, ENT_QUOTES); ?>"
                                            data-primary-area-name="<?= htmlspecialchars($userRow['area_name'] ?? '', ENT_QUOTES); ?>"
                                            data-area-ids="<?= htmlspecialchars(json_encode($assignedAreas[(int) $userRow['id']] ?? []), ENT_QUOTES); ?>">
                                            <i class="fas fa-pen me-1" aria-hidden="true"></i>Edit Areas
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </td>

                                <td class="text-center">

                                    <button
                                        type="button"
                                        class="btn btn-outline-danger btn-sm remove-row"
                                        <?= !$canManageRecommenders ? 'disabled' : ''; ?>
                                        title="Remove"
                                    >
                                        <i class="fas fa-trash-alt"></i>
                                    </button>

                                </td>

                            </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr class="empty-row">

                                <td colspan="3" class="text-center text-muted py-4">

                                    <div class="mb-2 text-center">
                                        <i class="fas fa-user-slash fs-3 opacity-50"></i>
                                    </div>

                                    No recommenders assigned.

                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>
