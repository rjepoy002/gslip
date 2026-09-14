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
    v.id,
    v.plate_no,
    v.brand,
    v.model,
    v.category,
    v.ownership,

    COUNT(DISTINCT gs.id) AS total_gas_slips,

    SUM(
        CASE
            WHEN fi.name IN ('Diesel', 'Unleaded')
            THEN CAST(fr.quantity AS DECIMAL(10,2))
            ELSE 0
        END
    ) AS total_fuel_liters

FROM vehicles v

LEFT JOIN gas_slips gs
    ON gs.vehicle_id = v.id
    AND gs.status = 'approved'
    AND DATE(gs.date_issued) BETWEEN ? AND ?

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
GROUP BY v.id

HAVING total_gas_slips > 0

ORDER BY total_fuel_liters DESC,
         v.plate_no ASC
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
            <th>Plate No.</th>
            <th>Vehicle</th>
            <th>Category</th>
            <th>Ownership</th>
            <th>Gas Slips</th>
            <th>Total Fuel (L)</th>
        </tr>
    </thead>

    <tbody>

    <?php if ($result->num_rows > 0): ?>

        <?php $no = 1; ?>

        <?php while ($row = $result->fetch_assoc()): ?>

        <tr>

            <td><?= $no++; ?></td>

            <td>
                <?= htmlspecialchars($row['plate_no']); ?>
            </td>

            <td>
                <?= htmlspecialchars(
                    trim(
                        ($row['brand'] ?? '') . ' ' .
                        ($row['model'] ?? '')
                    )
                ); ?>
            </td>

            <td>
                <?= htmlspecialchars($row['category']); ?>
            </td>

            <td>
                <?= htmlspecialchars($row['ownership']); ?>
            </td>

            <td>
                <?= number_format($row['total_gas_slips']); ?>
            </td>

            <td>
                <?= number_format(
                    $row['total_fuel_liters'] ?? 0,
                    2
                ); ?>
            </td>

        </tr>

        <?php endwhile; ?>

    <?php else: ?>

        <tr>
            <td colspan="7" class="text-center text-muted py-4">
                No vehicle fuel consumption records found.
            </td>
        </tr>

    <?php endif; ?>

    </tbody>

</table>