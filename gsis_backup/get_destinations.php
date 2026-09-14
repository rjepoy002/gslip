<?php
require_once 'includes/config.php';
$conn = getDBConnection();

header('Content-Type: application/json'); // force JSON output

$origin = $_GET['origin'] ?? '';
$destinations = [];
$route_info = [];

if ($origin !== '') {
    $stmt = $conn->prepare("
        SELECT DISTINCT id, destination, route 
        FROM routes 
        WHERE origin = ? AND route IS NOT NULL 
        ORDER BY route ASC
    ");

    $stmt->bind_param('s', $origin);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $destinations[] = [
            'id' => $row['id'],
            'name' => $row['destination'],
            'route_info' => $row['route']
        ];
    }
}

echo json_encode([
    'debug_origin' => $origin,  // 👈 check if origin is received
    'destinations' => $destinations,
    'route_info' => $route_info
]);
