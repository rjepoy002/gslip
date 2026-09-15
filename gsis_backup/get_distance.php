<?php

require_once 'includes/config.php';

header('Content-Type: application/json');

// Ensure we always return valid JSON
function jsonResponse($data) {
    echo json_encode($data);
    exit;
}

$conn = getDBConnection();

try {

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $data = json_decode(file_get_contents('php://input'), true);

        if (!is_array($data)) {
            $data = [];
        }

        $origin = $data['origin'] ?? '';
        $destinations = $data['destinations'] ?? [];

        // New fields coming from add_slip.php
        $vehicle_category = strtolower(trim($data['vehicle_category'] ?? ''));
        $vehicle_kmpl = isset($data['vehicle_kmpl'])
            ? (float)$data['vehicle_kmpl']
            : 0;

        $truck_idling_rate = isset($data['truck_idling_rate'])
            ? (float)$data['truck_idling_rate']
            : 0;

        $longest_distance = 0.0;
        $fuel_for_longest = 0.0;

        $farthest_route_id = null;
        $farthest_is_fixed_fuel = 0;

        $missing_routes = [];

        if ($origin && !empty($destinations)) {

            foreach ($destinations as $dest) {

                $origin_trim = trim($origin);
                $dest_trim = trim($dest);

                $stmt = $conn->prepare("
                    SELECT 
                        id,
                        distance_km,
                        COALESCE(fuel_allocation, 0) AS fuel_allocation,
                        COALESCE(is_fixed_fuel, 0) AS is_fixed_fuel
                    FROM routes
                    WHERE status = 'active'
                    AND LOWER(TRIM(origin)) = LOWER(?)
                    AND LOWER(TRIM(destination)) = LOWER(?)
                    LIMIT 1
                ");

                if (!$stmt) {
                    throw new Exception($conn->error);
                }

                $stmt->bind_param(
                    "ss",
                    $origin_trim,
                    $dest_trim
                );

                $stmt->execute();

                $res = $stmt->get_result();

                if ($row = $res->fetch_assoc()) {

                    $distance = (float)$row['distance_km'];
                    $fuel = (float)$row['fuel_allocation'];
                    $is_fixed_fuel = (int)$row['is_fixed_fuel'];

                    // Keep only the longest route
                    if ($distance > $longest_distance) {

                        $longest_distance = $distance;
                        $fuel_for_longest = $fuel;

                        $farthest_route_id = (int)$row['id'];
                        $farthest_is_fixed_fuel = $is_fixed_fuel;
                    }

                } else {

                    $missing_routes[] = [
                        'origin' => $origin_trim,
                        'destination' => $dest_trim
                    ];

                    error_log(
                        "Missing route: '$origin_trim' -> '$dest_trim'"
                    );
                }

                $stmt->close();
            }

            // Existing rule:
            // longest fuel + (0.6 × additional destinations)
            $extra = max(count($destinations) - 1, 0) * 0.6;

            $total_fuel = $fuel_for_longest + $extra;
        }

        jsonResponse([
            'total_distance' => round($longest_distance, 2),
            'total_fuel' => round($total_fuel ?? 0, 2),
            'route_ids' => $farthest_route_id
                ? [$farthest_route_id]
                : [],
            'is_fixed_fuel' => $farthest_is_fixed_fuel ?? 0,
            'missing_routes' => $missing_routes
        ]);
    }


    // GET request fallback (single origin-destination)

    $origin = $_GET['origin'] ?? '';
    $destination = $_GET['destination'] ?? '';

    $route_id = null;
    $distance = null;
    $fuel_allocation = null;
    $is_fixed_fuel = 0;

    if ($origin && $destination) {

        $stmt = $conn->prepare("
            SELECT
                id,
                distance_km,
                COALESCE(fuel_allocation, 0) AS fuel_allocation,
                COALESCE(is_fixed_fuel, 0) AS is_fixed_fuel
            FROM routes
            WHERE status = 'active'
            AND origin = ?
            AND destination = ?
            LIMIT 1
        ");

        if ($stmt) {

            $stmt->bind_param(
                "ss",
                $origin,
                $destination
            );

            $stmt->execute();

            $stmt->bind_result(
                $route_id,
                $distance,
                $fuel_allocation,
                $is_fixed_fuel
            );

            $stmt->fetch();

            $stmt->close();
        }
    }

    jsonResponse([
        'route_id' => $route_id,
        'distance_km' => $distance !== null
            ? (float)$distance
            : 0,
        'fuel_allocation' => $fuel_allocation !== null
            ? (float)$fuel_allocation
            : 0,
        'is_fixed_fuel' => (int)$is_fixed_fuel
    ]);

} catch (Exception $e) {

    jsonResponse([
        'error' => true,
        'message' => $e->getMessage(),
        'total_distance' => 0,
        'total_fuel' => 0,
        'route_ids' => [],
        'is_fixed_fuel' => 0,
        'missing_routes' => []
    ]);
}