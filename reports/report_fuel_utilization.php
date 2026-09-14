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
    fi.id,
    fi.name,
    fi.unit,

    COUNT(fr.id) AS total_availed,

    SUM(
        CAST(fr.quantity AS DECIMAL(10,2))
    ) AS total_quantity

FROM fuel_items fi

LEFT JOIN fuel_requests fr
    ON fr.fuel_item_id = fi.id

LEFT JOIN gas_slips gs
    ON gs.id = fr.gas_slip_id
    AND gs.status = 'approved'
    AND DATE(gs.date_issued) BETWEEN ? AND ?

LEFT JOIN users u
    ON u.id = gs.user_id

WHERE gs.id IS NOT NULL
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
GROUP BY fi.id

ORDER BY total_quantity DESC,
         fi.name ASC
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
            <th>Fuel Item</th>
            <th>Unit</th>
            <th>Times Availed</th>
            <th>Total Quantity Issued</th>
        </tr>
    </thead>

    <tbody>

    <?php if ($result->num_rows > 0): ?>

        <?php $no = 1; ?>

        <?php while ($row = $result->fetch_assoc()): ?>

        <tr>

            <td><?= $no++; ?></td>

            <td>
                <?= htmlspecialchars($row['name']); ?>
            </td>

            <td>
                <?= htmlspecialchars($row['unit']); ?>
            </td>

            <td>
                <?= number_format($row['total_availed']); ?>
            </td>

            <td>
                <?= number_format(
                    $row['total_quantity'],
                    2
                ); ?>
            </td>

        </tr>

        <?php endwhile; ?>

    <?php else: ?>

        <tr>
            <td colspan="5" class="text-center text-muted py-4">
                No fuel utilization records found.
            </td>
        </tr>

    <?php endif; ?>

    </tbody>

</table>