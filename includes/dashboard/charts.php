<!-- =========================
    MONTHLY + DEPARTMENT CHARTS (SEPARATE CARDS)
========================== -->

<div class="row g-4 mb-4">

    <!-- LINE CHART CARD -->
    <div class="<?php echo ($isApprover || $isAdmin) ? 'col-md-6' : 'col-12'; ?>">
        <div class="card p-0 h-100">

            <div class="fw-semibold mb-0 px-3" style="padding-top: 10px; color: #64748b;">
                <?php //if (!$isApprover): ?>

                    <?php
                    if (in_array($department_name, ['ASOD', 'ANOD']) && !$isApprover) {
                        if ($isRecommender){
                            echo $area_name . ' ';
                        }
                    } elseif(!$isPrivateApprover && !$isAdmin) {
                        echo $department_name;
                    }
                    ?>

                <?php //endif; ?>
                Monthly Approved Gas Slips (<?= date('Y'); ?>)
            </div>

            <hr class="hr-modern my-2">

            <div class="px-3 pb-3">
                <div style="position: relative; height:350px;">
                    <canvas id="approvedLineChart"></canvas>
                </div>
            </div>

        </div>
    </div>

    <!-- FUEL CONSUMPTION CHART CARD (APPROVER ONLY) -->
    <?php if ($isApprover || $isAdmin): ?>
    <div class="col-md-6">
        <div class="card p-0 h-100">

            <div class="fw-semibold mb-0 px-3" style="padding-top: 10px; color: #64748b;">
                Monthly Fuel Consumption (<?= date('Y'); ?>)
            </div>

            <hr class="hr-modern my-2">

            <div class="px-3 pb-3">
                <div style="position: relative; height:350px;">
                    <canvas id="fuelBarChart"></canvas>
                </div>
            </div>

        </div>
    </div>
    <?php endif; ?>

</div>

<div class="row g-4 mb-4">
    <!-- bar CHART CARD (APPROVER ONLY) -->
    <?php if ($isPrivateApprover || $isAdmin): ?>
    <div class="col-12">
        <div class="card p-0 h-100">
            <div class="fw-semibold mb-0 px-3" style="padding-top: 10px; color: #64748b;">
                Department Gas Slip Distribution (<?= date('Y'); ?>)
            </div>

            <hr class="hr-modern my-2">

            <div class="px-3 pb-3">
                <div style="position: relative; height:350px;">
                    <canvas id="departmentPieChart"></canvas>
                </div>
            </div>

        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($isAdmin || $isApprover || $isRecommender): ?>
<div class="row">

<!-- =========================
    TOP 10 VEHICLES
========================== -->

    <?php
    $vehicleCount = count($topVehicleLabels);
    $chartHeight = max(150, $vehicleCount * 50);
    ?>

    <div class="col-lg-6 mb-4">
        <div class="card p-0 h-100">

            <div class="fw-semibold mb-0 px-3"
                style="padding-top:10px; color:#64748b;">
                Top 10 Vehicles by Fuel Consumption
            </div>

            <hr class="hr-modern my-2">

            <div style="height:<?= $chartHeight ?>px;">
                <canvas id="topVehiclesChart"></canvas>
            </div>

        </div>
    </div>

    <!-- =========================
        TOP FUEL ITEMS
    ========================= -->
    <div class="col-lg-6 mb-4">

        <div class="card p-0 h-100">

            <div class="fw-semibold mb-0 px-3"
                style="padding-top:10px; color:#64748b;">
                Top 10 Fuel Items
            </div>

            <hr class="hr-modern my-2">

            <div class="table-responsive">

                <table class="styled-table excel-table mt-0">

                    <thead>
                        <tr>
                            <th width="60">#</th>
                            <th>Fuel Item</th>
                            <th class="text-end">Requests</th>
                            <th class="text-end">Quantity (L)</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php
                    $rank = 1;

                    if ($topFuelItemResult && $topFuelItemResult->num_rows > 0):

                        while ($row = $topFuelItemResult->fetch_assoc()):
                    ?>

                        <tr>
                            <td><?= $rank++ ?></td>

                            <td><?= htmlspecialchars($row['name']) ?></td>

                            <td class="text-end">
                                <?= number_format($row['total_requests']) ?>
                            </td>

                            <td class="text-end fw-semibold">
                                <?= number_format($row['total_quantity'], 2) ?>
                            </td>
                        </tr>

                    <?php
                        endwhile;
                    else:
                    ?>

                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                No fuel item data available.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

<div class="row">
<!-- =========================
    TOP 10 MOST USED ROUTES
========================= -->
<div class="col-lg-6 mb-4">

    <div class="card p-0 h-100">

        <div class="fw-semibold mb-0 px-3"
            style="padding-top:10px; color:#64748b;">
            Top 10 Most Used Routes
        </div>

        <hr class="hr-modern my-2">

        <div class="table-responsive">
            <table class="styled-table excel-table mt-0">

                <thead>
                    <tr>
                        <th width="60">#</th>
                        <th>Origin</th>
                        <th>Destination</th>
                        <th class="text-end">Gas Slips</th>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    $rank = 1;

                    if ($topRouteResult && $topRouteResult->num_rows > 0):

                        while ($row = $topRouteResult->fetch_assoc()):
                    ?>
                            <tr>
                                <td><?= $rank++ ?></td>
                                <td><?= htmlspecialchars($row['origin']) ?></td>
                                <td><?= htmlspecialchars($row['destination']) ?></td>
                                <td class="text-end fw-semibold">
                                    <?= number_format($row['total_slips']) ?>
                                </td>
                            </tr>
                    <?php
                        endwhile;
                    else:
                    ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                No route data available.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>

            </table>
        </div>

    </div>

</div>

<!-- =========================
    TOP 10 DRIVERS
========================= -->

<div class="col-lg-6 mb-4">

    <div class="card p-0 h-100">

        <div class="fw-semibold mb-0 px-3"
             style="padding-top:10px; color:#64748b;">
            Top 10 Drivers
        </div>

        <hr class="hr-modern my-2">

        <div class="table-responsive">

            <table class="styled-table excel-table mt-0">

                <thead>
                    <tr>
                        <th width="60">#</th>
                        <th>Driver</th>
                        <th class="text-end">Gas Slips</th>

                    </tr>
                </thead>

                <tbody>

                <?php
                $rank = 1;

                if ($topDriverResult && $topDriverResult->num_rows > 0):

                    while ($row = $topDriverResult->fetch_assoc()):
                ?>

                    <tr>
                        <td><?= $rank++ ?></td>

                        <td><?= htmlspecialchars($row['driver']) ?></td>

                        <td class="text-end">
                            <?= number_format($row['total_slips']) ?>
                        </td>

                    </tr>

                <?php
                    endwhile;
                else:
                ?>

                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">
                            No driver data available.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>
</div>
<?php endif; ?>

<!-- =========================
    RECENT GAS SLIPS
========================== -->

<div class="card p-0 mb-4">

    <div class="fw-semibold mb-0 px-3" style="padding-top: 10px; color: #64748b;">Recent Gas Slips</div>
    <hr class="hr-modern my-2">

    <table class="styled-table excel-table mt-0">
        <thead>
            <tr>
                <th>No.</th>
                <th>Gas Slip No.</th>
                <th>Date Issued</th>
                <th>Requested By</th>
                <th>Purpose</th>
                <th>Route</th>
            </tr>
        </thead>
        <tbody>

        <?php if ($recentResult && $recentResult->num_rows > 0): ?>
            <?php $no = 1; ?>
            <?php while ($row = $recentResult->fetch_assoc()): ?>
                <tr>
                    <td><?= $no++; ?> </td>
                    <td>
                        <strong><?= htmlspecialchars($row['gas_slip_id']); ?></strong>
                        <?php
                        if (!empty($row['printed_at'])) {
                        echo '<span class="badge bg-primary" title="Approved"><i class="bi bi-check-lg"></i></span>';
                        }else{
                        switch ($row['status']) {
                            case 'recommended':
                                echo '<span class="badge bg-warning ms-1" title="Recommended">R</span>';
                                break;
                            case 'approved':
                                echo '<span class="badge bg-success ms-1" title="Approved">A</span>';
                                break;
                            case 'pending':
                                echo '<span class="badge bg-info ms-1" title="Pending">P</span>';
                                break;
                            case 'draft':
                                echo '<span class="badge bg-secondary ms-1" title="Draft">D</span>';
                                break;
                            case 'rejected':
                                echo '<span class="badge bg-danger ms-1" title="Rejected">X</span>';
                                break;
                            default:
                                echo '<span class="badge bg-light ms-1 text-black" title="Unknown">?</span>';
                        }
                        }
                        ?>
                    </td>

                    <td>
                        <?= date('M d, Y · h:i A', strtotime($row['date_issued'])); ?>
                    </td>
                    
                    <td>
                        <?= htmlspecialchars($row['requested_by']); ?>
                    </td>

                    <td><?= htmlspecialchars($row['purpose']) ?></td>

                    <td>
                        <?= htmlspecialchars($row['route_path']) ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="5" class="text-center text-muted">
                    No recent records found
                </td>
            </tr>
        <?php endif; ?>

        </tbody>
    </table>

</div>