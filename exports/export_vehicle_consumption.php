<?php
$userId = $_SESSION['user_id'];
$role     = $_SESSION['role'] ?? '';
$department = $_SESSION['department_id'];
$area = $_SESSION['area'];

$isAdmin        = ($role === 'admin');
$isRecommender  = !empty($_SESSION['is_recommender']);
$isApprover     = !empty($_SESSION['is_approver']);
$isPrivateApprover     = !empty($_SESSION['is_private_approver']);

header('Content-Type: text/csv; charset=utf-8');
header(
    'Content-Disposition: attachment; filename="vehicle_consumption_' .
    date('Ymd_His') .
    '.csv"'
);

echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

fputcsv($output, [
    'Plate No.',
    'Vehicle',
    'Category',
    'Ownership',
    'Gas Slips',
    'Total Fuel (Liters)'
]);
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
            WHEN fi.name IN ('Diesel','Unleaded')
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

while ($row = $result->fetch_assoc()) {

    fputcsv($output, [

        $row['plate_no'],

        trim(
            ($row['brand'] ?? '') .
            ' ' .
            ($row['model'] ?? '')
        ),

        $row['category'],
        $row['ownership'],

        $row['total_gas_slips'],

        number_format(
            $row['total_fuel_liters'] ?? 0,
            2,
            '.',
            ''
        )
    ]);
}

fclose($output);
exit;