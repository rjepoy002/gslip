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
      r.id AS route_id,
      r.destination AS name, 
      r.distance_km AS km,
      r.fuel_allocation
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

  $sql = "
    SELECT 
      r.id AS route_id,
      r.destination AS name, 
      r.distance_km AS km,
      r.fuel_allocation
    FROM routes r
    INNER JOIN areas a
      ON a.area_name = r.area
    WHERE (r.route IS NULL OR r.route = '')
      AND a.id = ?
      AND r.status = 'active'
    ORDER BY r.id ASC
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
