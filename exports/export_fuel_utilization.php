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
    'Content-Disposition: attachment; filename="fuel_utilization_' .
    date('Ymd_His') .
    '.csv"'
);

echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

fputcsv($output, [
    'Fuel Item',
    'Unit',
    'Times Availed',
    'Total Quantity Issued'
]);
$sql = "
SELECT
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

while ($row = $result->fetch_assoc()) {

    fputcsv($output, [

        $row['name'],

        $row['unit'],

        $row['total_availed'],

        number_format(
            $row['total_quantity'],
            2,
            '.',
            ''
        )

    ]);
}

fclose($output);
exit;