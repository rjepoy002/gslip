<?php

if (empty($dateFrom) || empty($dateTo)) {
    echo '
        <div class="alert alert-warning">
            Please select a date range.
        </div>
    ';
    return;
}
$sql = "
SELECT
    r.id,
    r.origin,
    r.destination,

    COUNT(DISTINCT gs.id) AS total_gas_slips,

    (
        COUNT(DISTINCT gs.id) * r.distance_km
    ) AS accumulated_distance,

    COALESCE(
        SUM(
            CASE
                WHEN fi.name IN ('Diesel', 'Unleaded')
                THEN CAST(fr.quantity AS DECIMAL(10,2))
                ELSE 0
            END
        ),
        0
    ) AS actual_fuel

FROM routes r

LEFT JOIN gas_slip_routes gsr
    ON gsr.route_id = r.id

LEFT JOIN gas_slips gs
    ON gs.id = gsr.gas_slip_id

    AND gs.status = 'approved'

    AND DATE(gs.date_issued)
        BETWEEN ? AND ?

    AND gsr.id = (
        SELECT MAX(gsr2.id)
        FROM gas_slip_routes gsr2
        WHERE gsr2.gas_slip_id = gs.id
    )

LEFT JOIN users u
    ON u.id = gs.user_id

LEFT JOIN fuel_requests fr
    ON fr.gas_slip_id = gs.id

LEFT JOIN fuel_items fi
    ON fi.id = fr.fuel_item_id

WHERE 1=1
";

$types = 'ss';
$params = [$dateFrom, $dateTo];

if (!$isAdmin && !$isPrivateApprover) {

    $sql .= "
    AND u.department_id = ?
    ";

    $types .= 'i';
    $params[] = $department;
}

$sql .= "
GROUP BY r.id

HAVING total_gas_slips > 0

ORDER BY total_gas_slips DESC,
         r.origin ASC,
         r.destination ASC
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    $types,
    ...$params
);

$stmt->execute();

$result = $stmt->get_result();

?>

<table class="styled-table excel-table">

    <thead>
        <tr class="group-header">
            <th>No.</th>
            <th>Origin</th>
            <th>Destination</th>
            <th>Gas Slips Used</th>
            <th>Accumulated Distance (KM)</th>
            <th>Actual Fuel Issued (L)</th>
        </tr>
    </thead>

    <tbody>

    <?php if ($result->num_rows > 0): ?>

        <?php $no = 1; ?>

        <?php while ($row = $result->fetch_assoc()): ?>

        <tr>

            <td><?= $no++; ?></td>

            <td>
                <?= htmlspecialchars($row['origin']); ?>
            </td>

            <td>
                <?= htmlspecialchars($row['destination']); ?>
            </td>

            <td>
                <?= number_format($row['total_gas_slips']); ?>
            </td>

            <td>
                <?= number_format(
                    $row['accumulated_distance'],
                    2
                ); ?>
            </td>

            <td>
                <?= number_format(
                    $row['actual_fuel'],
                    2
                ); ?>
            </td>

        </tr>

        <?php endwhile; ?>

    <?php else: ?>

        <tr>
            <td colspan="6" class="text-center text-muted py-4">
                No route utilization records found for the selected date range.
            </td>
        </tr>

    <?php endif; ?>

    </tbody>

</table>