
<!-- =========================================================
     DEPARTMENT APPROVER MODAL
========================================================= -->
<div
    class="modal fade"
    id="departmentApproverModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-lg modal-dialog-scrollable">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">
                    Select Department Approver
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>

            </div>

            <div class="modal-body">

                <div class="table-responsive">

                    <table class="styled-table excel-table approval-table mb-0">

                        <thead class="table-light">

                            <tr>
                                <th>Name</th>
                                <th>Designation</th>
                            </tr>

                        </thead>

                        <tbody id="departmentApproverTableBody">

                            <?php foreach ($allUsers as $userRow): ?>

                                <?php
                                $fullName =
                                    formatUserDisplayName(
                                        $userRow
                                    );
                                ?>

                                <tr
                                    class="select-department-approver modal-select-row"

                                    data-id="<?= $userRow['id']; ?>"
                                    data-department-id="<?= $userRow['department_id']; ?>"
                                    data-name="<?= htmlspecialchars(
                                        $fullName
                                    ); ?>"

                                    data-designation="<?= htmlspecialchars(
                                        $userRow['designation'] ?? '-'
                                    ); ?>"
                                >

                                    <td class="fw-semibold">

                                        <?= htmlspecialchars(
                                            $fullName
                                        ); ?>

                                    </td>

                                    <td>

                                        <small class="text-muted">

                                            <?= htmlspecialchars(
                                                $userRow['designation'] ?? '-'
                                            ); ?>

                                        </small>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>

</div>