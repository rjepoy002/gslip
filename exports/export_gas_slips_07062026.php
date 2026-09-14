<?php

$userId = $_SESSION['user_id'];
$role     = $_SESSION['role'] ?? '';
$department = $_SESSION['department_id'];
$area = $_SESSION['area'];

$isAdmin        = ($role === 'admin');
$isRecommender  = !empty($_SESSION['is_recommender']);
$isApprover     = !empty($_SESSION['is_approver']);
$isPrivateApprover     = !empty($_SESSION['is_private_approver']);
$isFullReport         = !empty($_SESSION['is_full_report']);

$fullReportDepartments = [
    4, // FSD
    5, // Audit
];

$canViewAllReports = in_array($department, $fullReportDepartments);
$viewAllDepartments = $canViewAllReports && isset($_GET['view_all']);

header('Content-Type: text/csv; charset=utf-8');
header(
    'Content-Disposition: attachment; filename="gas_slips_' .
    date('Ymd_His') .
    '.csv"'
);

/* UTF-8 BOM for Excel */
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

/* =========================================================
   CSV HEADERS
========================================================= */

fputcsv($output, [
    'Gas Slip No.',
    'Date Issued',
    'Validity Until',
    'Vehicle',
    'Route',

    'Fuel Items',
    'Fuel Quantities',

    'Purpose',
    'Requested By',

    'Recommended By',
    'Recommended At',

    'Approved By',
    'Approved At',

    'Rejected By',
    'Rejected At',
    'Rejection Reason',

    'Printed At',
    'Status'
]);

/* =========================================================
   QUERY
========================================================= */
$sql = "
SELECT
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
        COALESCE(rej.first_name,''),
        ' ',
        COALESCE(rej.last_name,'')
    ) AS rejector_name,

    CONCAT(
        MIN(r.origin),
        ' -> ',
        GROUP_CONCAT(
            r.destination
            ORDER BY gsr.id
            SEPARATOR ' -> '
        )
    ) AS route_path,

    GROUP_CONCAT(
        DISTINCT fi.name
        ORDER BY fi.name
        SEPARATOR ', '
    ) AS fuel_items,

    GROUP_CONCAT(
        fr.quantity
        ORDER BY fi.name
        SEPARATOR ', '
    ) AS fuel_quantities

FROM gas_slips gs

LEFT JOIN vehicles v
    ON v.id = gs.vehicle_id

LEFT JOIN gas_slip_routes gsr
    ON gsr.gas_slip_id = gs.id

LEFT JOIN routes r
    ON r.id = gsr.route_id

LEFT JOIN fuel_requests fr
    ON fr.gas_slip_id = gs.id

LEFT JOIN fuel_items fi
    ON fi.id = fr.fuel_item_id

LEFT JOIN users u
    ON u.id = gs.user_id

LEFT JOIN users rec
    ON rec.id = gs.recommended_by

LEFT JOIN users app
    ON app.id = gs.approved_by

LEFT JOIN users rej
    ON rej.id = gs.rejected_by

WHERE DATE(gs.date_issued)
    BETWEEN ? AND ?
";

$types = 'ss';
$params = [$dateFrom, $dateTo];

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

/* =========================================================
   DATA
========================================================= */

while ($row = $result->fetch_assoc()) {

    fputcsv($output, [

        $row['gas_slip_id'],

        $row['date_issued'],

        $row['validity_until'],

        $row['plate_no'],

        $row['route_path'],

        $row['fuel_items'],

        $row['fuel_quantities'],

        $row['purpose'],

        $row['requested_by'],

        trim($row['recommender_name']),
        $row['recommended_at'],

        trim($row['approver_name']),
        $row['approved_at'],

        trim($row['rejector_name']),
        $row['rejected_at'],

        $row['rejection_reason'],

        $row['printed_at'],

        ucfirst($row['status'])

    ]);
}

fclose($output);
exit;