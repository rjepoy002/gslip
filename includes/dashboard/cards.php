 <!-- =========================
    STAT CARDS
========================== -->

<div class="row g-4 mb-2">

    <!-- TOTAL CARD -->
    <div class="col-md-3">
        <a href="create_gas_slip.php" class="text-decoration-none">
            <div class="card text-bg-primary text-white shadow" 
                style="border: 4px solid #ffffff;">
                <div class="card-body p-3">
                    <div class="fw-semibold">
                        <?php if ($isRecommender): ?>

                            <?php
                            if (in_array($department_name, ['ASOD', 'ANOD'])) {
                                echo $area_name . ' ';
                            } else {
                                echo $department_name;
                            }
                            ?>

                        <?php endif; ?>
                        Total Gas Slips
                        <?php if (!$isRecommender && !$isApprover && !$isAdmin): ?>
                            Created
                        <?php endif; ?>
                    </div>
                    <!-- <hr class="my-2"> -->
                    <div class="text-center">
                        <span class="fw-bold" style="font-size: 4rem;">
                            <?php if ($isApprover || $isAdmin) {
                                echo $area_total;
                            } else {
                                echo $total ?? 0;
                            } ?>
                        </span>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <!-- STATUS SECTION -->
    <div class="col-md-9 mb-3">
        
        <div class="fw-semibold mb-2 p-1">Active Gas Slip Status Overview</div>
        <!-- <hr class="border-primary border-2 opacity-50 my-2"> -->
        <hr class="hr-modern my-2">

        <div class="row g-3">

            <?php if (!$isApprover): ?>
                <div class="col-md-4">
                    <a href="draft_gas_slips.php" class="text-decoration-none">
                        <div class="card text-bg-secondary shadow" 
                            style="border: 4px solid #ffffff;">
                            <div class="card-body p-3">
                                <div class="small fw-semibold">Draft</div>
                                <!-- <hr class="my-2"> -->
                                <div class="fs-2 fw-bold text-center">
                                    <?= $counts['draft'] ?? 0 ?>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endif; ?>

            <div class="col-md-4">
                <a href="pending_gas_slips.php" class="text-decoration-none">
                    <div class="card text-bg-info text-white shadow" 
                        style="border: 4px solid #ffffff;">
                        <div class="card-body p-3">
                            <div class="small fw-semibold">Pending</div>
                            <!-- <hr class="my-2"> -->
                            <!-- <div class="fs-2 fw-bold text-center">
                                <!?= $counts['pending'] ?? 0 ?>
                            </div> -->
                            <div class="fs-2 fw-bold text-center">
                                <?=
                                    ($counts['pending'] ?? 0) +
                                    ((!$isAdmin && !$isApprover && !$isRecommender)
                                        ? ($counts['recommended'] ?? 0)
                                        : 0)
                                ?>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-md-4">
                <a href="approved_slips.php" class="text-decoration-none">
                    <div class="card text-bg-success shadow" 
                        style="border: 4px solid #ffffff;">
                        <div class="card-body p-3">
                            <div class="small fw-semibold">Approved</div>
                            <!-- <hr class="my-2"> -->
                            <div class="fs-2 fw-bold text-center">
                                <?= $counts['approved'] ?? 0 ?>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

        </div>
    </div>

</div>