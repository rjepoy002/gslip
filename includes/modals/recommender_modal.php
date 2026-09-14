<!-- =========================================================
     RECOMMENDER MODAL
========================================================= -->
<div
    class="modal fade"
    id="recommenderModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-lg modal-dialog-scrollable">

        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header">

                <h5 class="modal-title">
                    <i class="fas fa-user-plus me-2"></i>
                    Add Recommender
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>

            </div>

            <!-- BODY -->
            <div class="modal-body">

                <div class="table-responsive">

                    <table class="styled-table excel-table approval-table mb-0">
                    <!-- <table class="table table-hover align-middle mb-0"> -->

                        <thead class="table-light">

                            <tr>
                                <th>Name</th>
                                <th>Designation</th>
                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($users as $userRow): ?>

                            <?php
                                if (in_array((int)$userRow['id'], $currentRecommenders)) {
                                    continue;
                                }

                                $fullName = formatUserDisplayName($userRow);
                            ?>

                            <tr
                                class="add-recommender-row modal-select-row"

                                data-id="<?= $userRow['id']; ?>"
                                data-name="<?= htmlspecialchars($fullName); ?>"
                                data-designation="<?= htmlspecialchars($userRow['designation'] ?? ''); ?>"
                            >

                                <td class="fw-semibold">
                                    <?= htmlspecialchars($fullName); ?>
                                </td>

                                <td>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($userRow['designation'] ?? '-'); ?>
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
