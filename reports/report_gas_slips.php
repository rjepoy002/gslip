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
    gs.id,
    gs.gas_slip_id,
    gs.date_issued,
    gs.validity_until,
    gs.requested_by,
    gs.purpose,
    gs.status,
    gs.printed_at,
    gs.recommended_at,
    gs.approved_at,
    gs.rejected_at,
    gs.rejection_reason,

    v.plate_no,

    CONCAT(
        COALESCE(rec.first_name,''),
        ' ',
        COALESCE(rec.last_name,'')
    ) AS recommender_name,

    CONCAT(
        COALESCE(app.first_name,''),
        ' ',
        COALESCE(app.last_name,'')
    ) AS approver_name,

    CONCAT(
        MIN(r.origin),
        ' → ',
        GROUP_CONCAT(
            r.destination
            ORDER BY gsr.id
            SEPARATOR ' → '
        )
    ) AS route_path

FROM gas_slips gs

LEFT JOIN vehicles v
    ON v.id = gs.vehicle_id

LEFT JOIN gas_slip_routes gsr
    ON gsr.gas_slip_id = gs.id

LEFT JOIN routes r
    ON r.id = gsr.route_id

LEFT JOIN users u
    ON u.id = gs.user_id

LEFT JOIN users rec
    ON rec.id = gs.recommended_by

LEFT JOIN users app
    ON app.id = gs.approved_by

WHERE DATE(gs.date_issued)
    BETWEEN ? AND ?
";

$types = 'ss';
$params = [$dateFrom, $dateTo];
// Restrict to own department unless allowed to view all
if (
    !$isAdmin &&
    !$isPrivateApprover &&
    !$viewAllDepartments
) {
    $sql .= "
    AND u.department_id = ?
    ";

    $types .= 'i';
    $params[] = $department;
}

$sql .= "
GROUP BY gs.id

ORDER BY gs.date_issued DESC
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
            <th>Gas Slip No.</th>
            <th>Date Issued</th>
            <th>Vehicle</th>
            <th>Route</th>
            <th>Requested By</th>
            <th>Recommended By</th>
            <th>Approved By</th>
            <th>Status</th>
        </tr>

    </thead>

    <tbody>

    <?php if ($result->num_rows > 0): ?>

        <?php $no = 1; ?>

        <?php while ($row = $result->fetch_assoc()): ?>

        <tr>

            <td><?= $no++; ?></td>

            <td>
                <?= htmlspecialchars($row['gas_slip_id']); ?>
            </td>

            <td>
                <?= date('M d, Y', strtotime($row['date_issued'])); ?>
            </td>

            <td>
                <?= htmlspecialchars($row['plate_no'] ?? '-'); ?>
            </td>

            <td style="white-space: normal;">
                <?= htmlspecialchars($row['route_path'] ?? '-'); ?>
            </td>

            <td>
                <?= htmlspecialchars($row['requested_by'] ?? '-'); ?>
            </td>

            <td>
                <?= !empty(trim($row['recommender_name']))
                    ? htmlspecialchars($row['recommender_name'])
                    : '-'; ?>
            </td>

            <td>
                <?= !empty(trim($row['approver_name']))
                    ? htmlspecialchars($row['approver_name'])
                    : '-'; ?>
            </td>

            <td>
                <?= ucfirst($row['status']); ?>
            </td>

        </tr>

        <?php endwhile; ?>

    <?php else: ?>

        <tr>
            <td colspan="9" class="text-center text-muted py-4">
                No gas slips found for the selected date range.
            </td>
        </tr>

    <?php endif; ?>

    </tbody>

</table>