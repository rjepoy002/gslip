<?php
/**
 * gas_slip_data.php
 * Central data loader for Create Gas Slip
 * MYSQLI SAFE VERSION
 */

require_once __DIR__ . '/config.php';

$conn = getDBConnection();

/* =========================================================
   VEHICLES
========================================================= */
$vehicle_options = [];

$result = $conn->query("
  SELECT
    id,
    plate_no,
    brand,
    model,
    category,
    ownership,
    km_per_liter,
    idling_rate
  FROM vehicles
  WHERE status = 'active'
  ORDER BY plate_no
");

while ($row = $result->fetch_assoc()) {
  $vehicle_options[] = $row;
}
$result->free(); // 🚨 REQUIRED (prevents out-of-sync)

/* =========================================================
   ROUTES / DESTINATIONS
========================================================= */
$routes = [];

$result = $conn->query("
  SELECT
    id,
    origin,
    destination,
    distance_km
  FROM routes
  WHERE status = 'active'
  ORDER BY origin, destination
");

while ($row = $result->fetch_assoc()) {
  $routes[] = $row;
}
$result->free(); // 🚨 REQUIRED

/* =========================================================
   FUEL ITEMS (THIS FIXES YOUR ERROR)
========================================================= */
$fuel_items = [];

$stmt = $conn->prepare("
  SELECT
    id,
    name,
    unit,
    container
  FROM fuel_items
  WHERE status = 'active'
  ORDER BY id
");

$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
  $fuel_items[] = $row;
}

$res->free();    // 🚨 REQUIRED
$stmt->close();  // 🚨 REQUIRED

/* =========================================================
   SETTINGS (OPTIONAL)
========================================================= */
$settings = [];

$result = $conn->query("SELECT * FROM settings LIMIT 1");
if ($result) {
  $settings = $result->fetch_assoc() ?: [];
  $result->free();
}

/* =========================================================
   DO NOT ECHO ANYTHING BELOW
========================================================= */
