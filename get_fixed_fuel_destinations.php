<?php
require_once 'includes/config.php';

$conn = getDBConnection();

$origin = $_GET['origin'] ?? '';
$destinations = [];

if ($origin !== '') {

    $stmt = $conn->prepare("
        SELECT DISTINCT
            id,
            destination,
            fuel_allocation
        FROM routes
        WHERE origin = ?
          AND is_fixed_fuel = 1
          AND status = 'active'
        ORDER BY destination ASC
    ");

    $stmt->bind_param('s', $origin);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $destinations[] = [
            'id' => $row['id'],
            'name' => $row['destination'],
            'fuel_allocation' => $row['fuel_allocation']
        ];
    }
}

echo json_encode([
    'debug_origin' => $origin,
    'destinations' => $destinations
]);
?>