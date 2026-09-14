<?php
require_once 'includes/config.php';

$gas_slip_id = intval($_GET['id']);

$stmt = $conn->prepare("
    SELECT 
        gsr.route_id,
        r.destination,
        r.distance_km
    FROM gas_slip_routes gsr
    LEFT JOIN routes r ON r.id = gsr.route_id
    WHERE gsr.gas_slip_id = ?
");
$stmt->bind_param("i", $gas_slip_id);
$stmt->execute();

$result = $stmt->get_result();
$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);