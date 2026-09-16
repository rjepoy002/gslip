<?php

require_once 'includes/auth.php';
require_once 'includes/config.php';

$conn = getDBConnection();

// 🔐 AUTH GUARD
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['session_token']) ||
    !isset($_SESSION['role']) ||
    !isset($_SESSION['department_id']) ||
    !isset($_SESSION['area'])
) {
    header('Location: index.php');
    exit;
}

$gas_slip_id = (int) ($_GET['id'] ?? 0);

if ($gas_slip_id <= 0) {
    header("Location: draft_gas_slips.php");
    exit;
}


/* =========================================================
   LOAD GAS SLIP
========================================================= */

$stmt = $conn->prepare("
    SELECT 
        gs.id,
        gs.user_id,
        gs.gas_slip_id,
        gs.date_issued,
        gs.validity_until,
        gs.requested_by,
        gs.vehicle_id,
        gs.purpose,
        v.plate_no,
        v.brand,
        v.model,
        v.category,
        COUNT(gsr.id) AS route_count
    FROM gas_slips gs
    LEFT JOIN gas_slip_routes gsr
        ON gsr.gas_slip_id = gs.id
    LEFT JOIN vehicles v
        ON v.id = gs.vehicle_id
    WHERE gs.id = ?
      AND gs.status = 'draft'
      AND gs.user_id = ?
    GROUP BY gs.id
");

$stmt->bind_param(
    "ii",
    $gas_slip_id,
    $_SESSION['user_id']
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: draft_gas_slips.php");
    exit;
}

$gasSlip = $result->fetch_assoc();

$editMode = true;


/* =========================================================
   LOAD SAVED DESTINATIONS
========================================================= */

$stmtRoutes = $conn->prepare("
    SELECT
        gsr.route_id,
        gsr.sequence_no,
        r.destination,
        r.distance_km,
        r.fuel_allocation,
        r.is_fixed_fuel
    FROM gas_slip_routes gsr
    LEFT JOIN routes r
        ON r.id = gsr.route_id
    WHERE gsr.gas_slip_id = ?
    ORDER BY gsr.sequence_no ASC
");

$stmtRoutes->bind_param(
    "i",
    $gas_slip_id
);

$stmtRoutes->execute();

$resultRoutes = $stmtRoutes->get_result();

$savedRouteIds = [];

$gasSlip['destinations'] = [];

while ($row = $resultRoutes->fetch_assoc()) {

    $routeId = (int) $row['route_id'];

    $savedRouteIds[] = $routeId;

    $gasSlip['destinations'][] = [
        'route_id' => $routeId,
        'name' => $row['destination'] ?? '',
        'km' => (float) ($row['distance_km'] ?? 0),
        'fuel_allocation' => (float) ($row['fuel_allocation'] ?? 0),
        'is_fixed_fuel' => (int) ($row['is_fixed_fuel'] ?? 0)
    ];
}


/* =========================================================
   LOAD SAVED FUEL REQUESTS
========================================================= */

$stmtFuel = $conn->prepare("
    SELECT
        fuel_item_id AS id,
        quantity AS qty,
        container
    FROM fuel_requests
    WHERE gas_slip_id = ?
");

$stmtFuel->bind_param(
    "i",
    $gas_slip_id
);

$stmtFuel->execute();

$resultFuel = $stmtFuel->get_result();

$fuelRows = [];

while ($row = $resultFuel->fetch_assoc()) {
    $fuelRows[] = $row;
}


/* =========================================================
   SAVED ROUTE IDS FOR destinations.js
========================================================= */

?>

<script>

window.SAVED_ROUTE_IDS =
    <?= json_encode($savedRouteIds) ?>;

</script>

<?php

require 'create_gas_slip.php';

?>
