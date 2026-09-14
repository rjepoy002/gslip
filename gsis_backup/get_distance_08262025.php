<?php
require_once 'includes/config.php';
$conn = getDBConnection();

$origin = $_GET['origin'] ?? '';
$destination = $_GET['destination'] ?? '';

$route_id = null;
$distance = null;
$fuel_allocation = null; // 👈 Add this

if ($origin && $destination) {
    $stmt = $conn->prepare("SELECT id, distance_km, fuel_allocation FROM routes WHERE origin = ? AND destination = ? AND status = 'active' LIMIT 1");
    $stmt->bind_param("ss", $origin, $destination);
    $stmt->execute();
    $stmt->bind_result($route_id, $distance, $fuel_allocation); // 👈 Bind both values
    $stmt->fetch();
    $stmt->close();
}

// 👇 Return both in JSON
echo json_encode([
    'route_id' => $route_id,
    'distance_km' => $distance,
    'fuel_allocation' => $fuel_allocation
]);
?>
