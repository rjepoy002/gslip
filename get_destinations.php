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

        ORDER BY r.destination ASC

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

            ORDER BY r.id ASC

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

            ORDER BY r.id ASC

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

echo json_encode([

    'destinations' =>
        $destinations

]);