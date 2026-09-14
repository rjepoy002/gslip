<!-- =========================================================
    APPROVER SETTINGS
========================================================= -->
<div class="col-md-6">

    <div class="card shadow-sm border-0 h-100">

        <div class="card-body">

            <!-- HEADER -->
            <div class="mb-4">

                <h5 class="mb-1">
                    <i class="fas fa-user-check text-success me-2"></i>
                    Department Approver
                </h5>

                <small class="text-muted">
                    Assign primary and backup approvers.
                </small>

            </div>

            <!-- =====================================================
                PRIMARY APPROVER
            ====================================================== -->

            <div class="border rounded p-3 mb-3">

                <div class="d-flex justify-content-between align-items-start">

                    <div>

                        <div class="fw-semibold text-success mb-1">
                            Primary Approver
                        </div>

                        <?php
                        $primaryApprover = null;

                        foreach ($users as $userRow) {

                            foreach ($currentApprovers as $approver) {

                                if (
                                    (int)$approver['user_id'] === (int)$userRow['id']
                                    && (int)$approver['is_primary'] === 1
                                ) {

                                    $primaryApprover = $userRow;
                                    break 2;
                                }
                            }
                        }
                        ?>

                        <?php if ($primaryApprover): ?>

                            <?php
                            $fullName = formatUserDisplayName(
                                $primaryApprover
                            );
                            ?>

                            <div class="fw-bold primary-approver-name">
                                <?= htmlspecialchars($fullName); ?>
                            </div>

                        <small class="text-muted primary-approver-designation">
                            <?= htmlspecialchars(
                                $primaryApprover['designation'] ?? '-'
                            ); ?>
                        </small>

                        <input
                            type="hidden"
                            name="primary_approver_id"
                            id="primaryApproverInput"
                            value="<?= $primaryApprover['id']; ?>"
                        >

                        <?php else: ?>

                            <div class="text-muted primary-approver-name">
                                No primary approver assigned.
                            </div>

                            <small class="text-muted primary-approver-designation"></small>

                            <input
                                type="hidden"
                                name="primary_approver_id"
                                id="primaryApproverInput"
                                value=""
                            >

                        <?php endif; ?>

                    </div>

                    <button
                        type="button"
                        class="btn btn-success btn-sm"
                        data-bs-toggle="modal"
                        data-bs-target="#primaryApproverModal"
                    >
                        <i class="fas fa-edit me-1"></i>
                        Change
                    </button>

                </div>

            </div>

            <!-- =====================================================
                SECONDARY APPROVER
            ===================================================== -->

            <div class="border rounded p-3 mb-3">

                <div class="d-flex justify-content-between align-items-start">

                    <div>

                        <div class="fw-semibold text-success mb-1">
                            Secondary Approver
                        </div>

                        <?php
                        $secondaryApprover = null;

                        foreach ($users as $userRow) {

                            if (
                                $secondaryApproverId !== null &&
                                (int)$userRow['id'] === (int)$secondaryApproverId
                            ) {

                                $secondaryApprover = $userRow;
                                break;
                            }
                        }
                        ?>

                        <?php if ($secondaryApprover): ?>

                            <?php
                            $fullName = formatUserDisplayName(
                                $secondaryApprover
                            );
                            ?>

                            <div class="fw-bold secondary-approver-name">
                                <?= htmlspecialchars($fullName); ?>
                            </div>

                            <small class="text-muted secondary-approver-designation">
                                <?= htmlspecialchars(
                                    $secondaryApprover['designation'] ?? '-'
                                ); ?>
                            </small>

                            <input
                                type="hidden"
                                id="secondaryApproverInput"
                                value="<?= $secondaryApprover['id']; ?>"
                            >

                        <?php else: ?>

                            <div class="text-muted secondary-approver-name">
                                No secondary approver assigned.
                            </div>

                            <small class="text-muted secondary-approver-designation"></small>

                            <input
                                type="hidden"
                                id="secondaryApproverInput"
                                value=""
                            >

                        <?php endif; ?>

                    </div>

                    <div class="d-flex gap-2">

                        <button
                            type="button"
                            class="btn btn-success btn-sm"
                            data-bs-toggle="modal"
                            data-bs-target="#secondaryApproverModal"
                        >
                            <i class="fas fa-edit me-1"></i>
                            Change
                        </button>

                        <?php if ($secondaryApprover): ?>

                            <button
                                type="button"
                                class="btn btn-outline-danger btn-sm"
                                id="clearSecondaryApproverBtn"
                            >
                                <i class="fas fa-times me-1"></i>
                                Clear
                            </button>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

            <!-- =====================================================
                RECOMMENDER ONLY APPROVAL
            ====================================================== -->

            <div class="border border-danger rounded p-3 mb-3">

                <div class="mb-3">

                    <div class="fw-semibold text-danger mb-1">
                        Recommender Only Approval
                    </div>

                    <small class="text-muted">
                        When enabled for an area, any recommender can provide
                        the final approval. The approver step will be skipped.
                        Private vehicle gas slips are not affected.
                    </small>

                </div>

                <?php if (!empty($departmentAreas)): ?>

                    <div class="border rounded">

                        <?php foreach (
                            $departmentAreas as $index => $departmentArea
                        ): ?>

                            <div
                                class="
                                    d-flex
                                    justify-content-between
                                    align-items-center
                                    p-3
                                    <?= $index > 0 ? 'border-top' : ''; ?>
                                "
                            >

                                <div class="fw-semibold">

                                    <?= htmlspecialchars(
                                        $departmentArea['area_name']
                                    ); ?>

                                </div>

                                <div class="form-check form-switch mb-0">

                                    <input
                                        class="
                                            form-check-input
                                            recommender-only-toggle
                                        "
                                        type="checkbox"
                                        role="switch"
                                        data-area-id="<?= $departmentArea['id']; ?>"
                                        <?= !empty($departmentArea['recommender_only'])
                                            ? 'checked'
                                            : ''; ?>
                                    >

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="text-muted small">
                        No areas found for this department.
                    </div>

                <?php endif; ?>

            </div>



            <!-- =====================================================
                PRIVATE VEHICLE APPROVER
            ===================================================== -->

            <?php if ($role === 'admin'): ?>

            <div class="border rounded p-3 mb-3">

                <div class="d-flex justify-content-between align-items-start">

                    <div>

                        <div class="fw-semibold text-primary mb-1">
                            Private Vehicle Approver
                        </div>

                        <?php
                        $privateVehicleApprover = null;

                        foreach ($allApprovers as $userRow) {

                            if (
                                (int)$userRow['id'] ===
                                (int)$privateVehicleApproverId
                            ) {

                                $privateVehicleApprover = $userRow;
                                break;
                            }
                        }
                        ?>

                        <?php if ($privateVehicleApprover): ?>

                            <?php
                            $fullName = formatUserDisplayName(
                                $privateVehicleApprover
                            );
                            ?>

                            <div class="fw-bold private-vehicle-approver-name">
                                <?= htmlspecialchars($fullName); ?>
                            </div>

                            <small class="text-muted private-vehicle-approver-designation">
                                <?= htmlspecialchars(
                                    $privateVehicleApprover['designation'] ?? '-'
                                ); ?>
                            </small>

                            <input
                                type="hidden"
                                id="privateVehicleApproverInput"
                                value="<?= $privateVehicleApprover['id']; ?>"
                            >

                        <?php else: ?>

                            <div class="text-muted private-vehicle-approver-name">
                                No private vehicle approver assigned.
                            </div>

                            <small class="text-muted private-vehicle-approver-designation"></small>

                            <input
                                type="hidden"
                                id="privateVehicleApproverInput"
                                value=""
                            >

                        <?php endif; ?>

                    </div>

                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        data-bs-toggle="modal"
                        data-bs-target="#privateVehicleApproverModal"
                    >
                        <i class="fas fa-edit me-1"></i>
                        Change
                    </button>

                </div>

            </div>

            <!-- =====================================================
                DEPARTMENT APPROVER
            ====================================================== -->

            <div class="border rounded p-3 mb-3">

                <!-- Department Dropdown -->
                <div class="fw-semibold text-primary mb-1">
                    Department Approver
                </div>
                <div class="mb-3">

                    <label class="form-label fw-semibold">
                        Department
                    </label>

                    <select
                        class="form-select"
                        id="departmentSelector"
                    >

                        <?php foreach ($departments as $dept): ?>

                            <?php
                            if (
                                (int)$dept['id'] ===
                                (int)$_SESSION['department_id']
                            ) {
                                continue;
                            }
                            ?>

                            <option value="<?= $dept['id']; ?>">

                                <?= htmlspecialchars(
                                    $dept['name']
                                ); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <!-- Approver Display -->
                <div class="d-flex justify-content-between align-items-start">

                    <div>

                        <div class="fw-bold department-approver-name">
                            No approver assigned.
                        </div>

                        <small class="text-muted department-approver-designation">
                        </small>

                        <input
                            type="hidden"
                            id="departmentApproverInput"
                            value=""
                        >

                    </div>

                    <!-- Change Button -->
                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        id="changeDepartmentApproverBtn"
                        data-bs-toggle="modal"
                        data-bs-target="#departmentApproverModal"
                    >

                        <i class="fas fa-edit me-1"></i>
                        Change

                    </button>

                </div>

            </div>

            <?php endif; ?>

        </div>

    </div>

</div>