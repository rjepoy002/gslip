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

                                    <div class="fw-semibold">
                                        <?= htmlspecialchars($fullName); ?>
                                    </div>

                                    <?php if (!empty($userRow['designation'])): ?>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($userRow['designation']); ?>
                                        </small>
                                    <?php endif; ?>

                                </td>

                                <td class="text-center">

                                    <button
                                        type="button"
                                        class="btn btn-outline-danger btn-sm remove-row"
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
