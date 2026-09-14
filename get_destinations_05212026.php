<?php
require_once 'includes/config.php';
session_start();

header('Content-Type: application/json');

$conn = getDBConnection();

/* REQUIRED INPUTS */
$area      = $_SESSION['area'] ?? '';
$useRoutes = isset($_GET['useRoutes']) && $_GET['useRoutes'] == '1';

if ($area === '') {
  echo json_encode(['destinations' => []]);
  exit;
}

/*
|--------------------------------------------------------------------------
| ROUTES QUERY
|--------------------------------------------------------------------------
| useRoutes = 1 → routes WITH destination
| useRoutes = 0 → routes WITHOUT destination
*/
if ($useRoutes) {
  $sql = "
    SELECT 
      id AS route_id,
      destination AS name, 
      distance_km AS km,
      fuel_allocation
    FROM routes
    WHERE route IS NOT NULL
      AND route <> ''
      AND area_id = ?
      AND status = 'active'
    ORDER BY destination ASC
  ";
} else {
  $sql = "
    SELECT 
      id AS route_id,
      destination AS name, 
      distance_km AS km,
      fuel_allocation
    FROM routes
    WHERE (route IS NULL OR route = '')
      AND area_id = ?
      AND status = 'active'
    ORDER BY id ASC
  ";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $area);
$stmt->execute();
$result = $stmt->get_result();

$destinations = [];

while ($row = $result->fetch_assoc()) {
  $destinations[] = [
  'route_id'        => (int)$row['route_id'],
  'name'            => $row['name'],
  'km'              => (float)$row['km'],
  'fuel_allocation' => (float)$row['fuel_allocation']
  ];
}

echo json_encode([
  'destinations' => $destinations
]);
