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
    'Content-Disposition: attachment; filename="route_utilization_' .
    date('Ymd_His') .
    '.csv"'
);

echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

/* =========================================================
   CSV HEADERS
========================================================= */

fputcsv($output, [
    'Origin',
    'Destination',
    'Gas Slips Used',
    'Accumulated Distance (KM)',
    'Actual Fuel Issued (L)'
]);

/* =========================================================
   QUERY
========================================================= */
$sql = "
SELECT
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

/* =========================================================
   DATA
========================================================= */

while ($row = $result->fetch_assoc()) {

    fputcsv($output, [

        $row['origin'],

        $row['destination'],

        $row['total_gas_slips'],

        number_format(
            $row['accumulated_distance'],
            2,
            '.',
            ''
        ),

        number_format(
            $row['actual_fuel'],
            2,
            '.',
            ''
        )

    ]);
}

fclose($output);
exit;