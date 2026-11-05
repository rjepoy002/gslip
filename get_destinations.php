<?php

require_once 'includes/config.php';

session_start();

header('Content-Type: application/json');

$conn = getDBConnection();

/* REQUIRED INPUTS */

$area = $_SESSION['area'] ?? '';

$useRoutes = isset($_GET['useRoutes']) && $_GET['useRoutes'] == '1';

$vehicleCategory = strtolower(
    trim($_GET['vehicleCategory'] ?? '')
);

if ($area === '') {

    echo json_encode([
        'destinations' => []
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| ROUTES QUERY
|--------------------------------------------------------------------------
| useRoutes = 1
|     → approved routes
|
| useRoutes = 0 + 2-wheels
|     → Fixed Fuel destinations only
|
| useRoutes = 0 + other vehicles
|     → existing non-route + Fixed Fuel behavior
|--------------------------------------------------------------------------
*/

if ($useRoutes) {

    $sql = "

        SELECT 

            r.id AS route_id,

            r.route AS route_code,

            r.destination AS name,

            r.distance_km AS km,

            r.fuel_allocation,

            r.is_fixed_fuel

        FROM routes r

        INNER JOIN areas a
            ON a.area_name = r.area

        WHERE r.route IS NOT NULL

          AND r.route <> ''

          AND a.id = ?

          AND r.status = 'active'

        ORDER BY CASE WHEN TRIM(COALESCE(r.route, '')) = '' THEN 1 ELSE 0 END, r.route ASC, r.destination ASC, r.id ASC

    ";

} else {

    if ($vehicleCategory === '2-wheels') {

        /*
        |--------------------------------------------------------------------------
        | 2-WHEELS + UNCHECKED
        |--------------------------------------------------------------------------
        | Only Fixed Fuel destinations are allowed.
        |
        | Fixed Fuel provides the fuel allocation required
        | for 2-wheels fuel consumption.
        |--------------------------------------------------------------------------
        */

        $sql = "

            SELECT 

                r.id AS route_id,

            r.route AS route_code,

                r.destination AS name,

                r.distance_km AS km,

                r.fuel_allocation,

                r.is_fixed_fuel

            FROM routes r

            INNER JOIN areas a
                ON a.area_name = r.area

            WHERE r.is_fixed_fuel = 1

              AND a.id = ?

              AND r.status = 'active'

            ORDER BY CASE WHEN TRIM(COALESCE(r.route, '')) = '' THEN 1 ELSE 0 END, r.route ASC, r.destination ASC, r.id ASC

        ";

    } else {

        /*
        |--------------------------------------------------------------------------
        | 4-WHEELS / TRUCKS + UNCHECKED
        |--------------------------------------------------------------------------
        | Keep the existing behavior.
        |
        | Shows non-route destinations, including Fixed Fuel.
        |--------------------------------------------------------------------------
        */

        $sql = "

            SELECT 

                r.id AS route_id,

            r.route AS route_code,

                r.destination AS name,

                r.distance_km AS km,

                r.fuel_allocation,

                r.is_fixed_fuel

            FROM routes r

            INNER JOIN areas a
                ON a.area_name = r.area

            WHERE (r.route IS NULL OR r.route = '')

              AND a.id = ?

              AND r.status = 'active'

            ORDER BY CASE WHEN TRIM(COALESCE(r.route, '')) = '' THEN 1 ELSE 0 END, r.route ASC, r.destination ASC, r.id ASC

        ";

    }

}

$stmt = $conn->prepare($sql);

$stmt->bind_param('i', $area);

$stmt->execute();

$result = $stmt->get_result();

$destinations = [];

while ($row = $result->fetch_assoc()) {

    $destinations[] = [

        'route_id' =>
            (int)$row['route_id'],

        'route_code' =>
            $row['route_code'],

        'name' =>
            $row['name'],

        'km' =>
            (float)$row['km'],

        'fuel_allocation' =>
            (float)$row['fuel_allocation'],

        'is_fixed_fuel' =>
            (int)$row['is_fixed_fuel']

    ];

}

// Saved selections may be outside the current approved/fixed filter. Retrieve
// display codes for those IDs within the same existing area scope; do not add
// them to the available query or change their saved distances/fuel values.
$routeCodes = [];
$selectedIds = [];
$selectedInput = $_GET['selected_route_ids'] ?? '';
if (is_string($selectedInput)) {
    foreach (array_slice(explode(',', $selectedInput), 0, 1000) as $id) {
        if (ctype_digit($id) && (int)$id > 0) $selectedIds[(int)$id] = (int)$id;
    }
}
if ($selectedIds) {
    $ids = array_values($selectedIds);
    $metadata = $conn->prepare('SELECT r.id, r.route FROM routes r INNER JOIN areas a ON a.area_name = r.area WHERE a.id = ? AND r.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
    $params = array_merge([(int)$area], $ids);
    $metadata->bind_param(str_repeat('i', count($params)), ...$params);
    $metadata->execute();
    foreach ($metadata->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $routeCodes[$row['id']] = $row['route'];
    $metadata->close();
}

echo json_encode([

    'route_codes' => $routeCodes,

    'destinations' =>
        $destinations

]);