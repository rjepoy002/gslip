<?php
session_start();
require_once 'includes/config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];
$department = $_SESSION['department_id'];
$area = $_SESSION['area'];

$areaName = '';

if (!empty($area)) {

    $stmtArea = $conn->prepare("
        SELECT area_name
        FROM areas
        WHERE id = ?
        LIMIT 1
    ");

    $stmtArea->bind_param("i", $area);
    $stmtArea->execute();

    $areaResultUser =
        $stmtArea->get_result()->fetch_assoc();

    $areaName =
        $areaResultUser['area_name'] ?? '';
}

$hasDates = !empty($_SESSION['date_from']) || !empty($_SESSION['date_to']);

/* =========================================================
   GET SAVED ROUTE LOCATIONS
========================================================= */

if (
    isset($_GET['action']) &&
    $_GET['action'] === 'get_route_locations'
) {

    header('Content-Type: application/json');

    $routeId = isset($_GET['route_id'])
        ? (int) $_GET['route_id']
        : 0;

    if ($routeId <= 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid route ID.'
        ]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT
            id,
            route_id,
            location_name,
            latitude,
            longitude,
            location_type,
            sequence
        FROM route_locations
        WHERE route_id = ?
        ORDER BY sequence ASC
    ");

    $stmt->bind_param("i", $routeId);
    $stmt->execute();

    $result = $stmt->get_result();
    $locations = [];

    while ($row = $result->fetch_assoc()) {
        $locations[] = $row;
    }

    echo json_encode([
        'status' => 'success',
        'locations' => $locations
    ]);

    exit;
}


/* =========================================================
   POST SAVE SECTION
========================================================= */

// Add or update Routes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    header('Content-Type: application/json');

    $form_mode = $_POST['form_mode'] ?? 'add';

    $routes_id = isset($_POST['routes_id'])
        ? (int) $_POST['routes_id']
        : 0;

    $area = trim($_POST['area'] ?? '');
    $route = trim($_POST['route'] ?? '');
    $origin = trim($_POST['origin'] ?? '');
    $destination = trim($_POST['destination'] ?? '');
    $distance_km = trim($_POST['distance_km'] ?? '');
    $fuel_allocation = trim($_POST['fuel_allocation'] ?? '');

    $is_fixed_fuel =
        isset($_POST['is_fixed_fuel']) &&
        $_POST['is_fixed_fuel'] === '1'
            ? 1
            : 0;

    /* Fixed Fuel always uses 0.1 km */
    if ($is_fixed_fuel === 1) {
        $distance_km = '0.1';
    }

    $status = trim($_POST['status'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    $route_data = $_POST['route_data'] ?? '';

    /* ================= VALIDATION ================= */

    $missingFields = [];

    if (!$origin) {
        $missingFields[] = 'Origin';
    }

    if (!$destination) {
        $missingFields[] = 'Destination';
    }

    /*
     * Distance is required, but 0 is valid
     * for Fixed Fuel.
     */
    if ($distance_km === '') {
        $missingFields[] = 'Distance';
    }

    if (!$status) {
        $missingFields[] = 'Status';
    }

    /*
     * Fixed Fuel requires a manually entered
     * fuel allocation.
     */
    if (
        $is_fixed_fuel === 1 &&
        $fuel_allocation === ''
    ) {
        $missingFields[] =
            'Fuel Allocation for Fixed Fuel';
    }

    if (!empty($missingFields)) {

        echo json_encode([
            'status' => 'error',
            'message' =>
                'Missing: ' .
                implode(', ', $missingFields)
        ]);

        exit;
    }


    /* =====================================================
       ROUTE DATA VALIDATION

       Normal routes:
       - Map data is required.

       Fixed Fuel:
       - Map data is NOT required.
       - Distance is 0.
    ===================================================== */

    $routeData = null;

    if (
        $form_mode === 'add' &&
        $is_fixed_fuel === 0
    ) {

        if (empty($route_data)) {

            echo json_encode([
                'status' => 'error',
                'message' =>
                    'Please finalize the route on the map first.'
            ]);

            exit;
        }

        $routeData =
            json_decode(
                $route_data,
                true
            );

        if (
            !is_array($routeData) ||
            empty($routeData['origin']) ||
            empty($routeData['destinations']) ||
            !is_array($routeData['destinations'])
        ) {

            echo json_encode([
                'status' => 'error',
                'message' =>
                    'Invalid route map data.'
            ]);

            exit;
        }
    }


    /* ================= DUPLICATE CHECK ================= */

    if ($form_mode === 'edit') {

        $check = $conn->prepare(
            "SELECT id
             FROM routes
             WHERE origin = ?
             AND destination = ?
             AND id != ?"
        );

        $check->bind_param(
            "ssi",
            $origin,
            $destination,
            $routes_id
        );

    } else {

        $check = $conn->prepare(
            "SELECT id
             FROM routes
             WHERE origin = ?
             AND destination = ?"
        );

        $check->bind_param(
            "ss",
            $origin,
            $destination
        );
    }


    $check->execute();
    $check->store_result();


    if ($check->num_rows > 0) {

        echo json_encode([
            'status' => 'error',
            'message' =>
                'Another route with the same origin and destination already exists.'
        ]);

        exit;
    }


    /* =====================================================
       START TRANSACTION
    ===================================================== */

    $conn->begin_transaction();


    try {

        /* ================= UPDATE ================= */

        if (
            $form_mode === 'edit' &&
            $routes_id > 0
        ) {

            $stmt = $conn->prepare(
                "UPDATE routes
                 SET
                    route = ?,
                    origin = ?,
                    destination = ?,
                    distance_km = ?,
                    fuel_allocation = ?,
                    is_fixed_fuel = ?,
                    status = ?,
                    remarks = ?
                 WHERE id = ?"
            );


            if (!$stmt) {
                throw new Exception(
                    $conn->error
                );
            }


            $stmt->bind_param(
                "sssddissi",
                $route,
                $origin,
                $destination,
                $distance_km,
                $fuel_allocation,
                $is_fixed_fuel,
                $status,
                $remarks,
                $routes_id
            );


            if (!$stmt->execute()) {
                throw new Exception(
                    $stmt->error
                );
            }


            $savedRouteId = $routes_id;


            /* =================================================
               REMOVE MAP LOCATIONS WHEN FIXED FUEL IS ENABLED
            ================================================= */

            if ($is_fixed_fuel === 1) {

                $deleteLocations =
                    $conn->prepare(
                        "DELETE FROM route_locations
                         WHERE route_id = ?"
                    );

                if (!$deleteLocations) {
                    throw new Exception(
                        $conn->error
                    );
                }

                $deleteLocations->bind_param(
                    "i",
                    $savedRouteId
                );

                if (!$deleteLocations->execute()) {
                    throw new Exception(
                        $deleteLocations->error
                    );
                }
            }


        } else {

            /* ================= INSERT ROUTE ================= */

            $stmt = $conn->prepare(
                "INSERT INTO routes
                (
                    area,
                    route,
                    origin,
                    destination,
                    distance_km,
                    fuel_allocation,
                    is_fixed_fuel,
                    status,
                    remarks
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );


            if (!$stmt) {
                throw new Exception(
                    $conn->error
                );
            }


            $stmt->bind_param(
                "ssssddiss",
                $area,
                $route,
                $origin,
                $destination,
                $distance_km,
                $fuel_allocation,
                $is_fixed_fuel,
                $status,
                $remarks
            );


            if (!$stmt->execute()) {
                throw new Exception(
                    $stmt->error
                );
            }


            /*
             * Get newly created route ID
             */

            $savedRouteId =
                $conn->insert_id;


            /* =================================================
               SAVE ORIGIN + DESTINATION PINS
               
               Fixed Fuel routes do NOT use map locations.
            ================================================= */

            if ($is_fixed_fuel === 0) {

                $locationStmt =
                    $conn->prepare(
                        "INSERT INTO route_locations
                        (
                            route_id,
                            location_name,
                            latitude,
                            longitude,
                            location_type,
                            sequence
                        )
                        VALUES (?, ?, ?, ?, ?, ?)"
                    );


                if (!$locationStmt) {
                    throw new Exception(
                        $conn->error
                    );
                }


                /* =============================================
                   SAVE ORIGIN
                   Sequence = 1
                ============================================= */

                $mapOrigin =
                    $routeData['origin'];

                $originLat =
                    (float) $mapOrigin['lat'];

                $originLng =
                    (float) $mapOrigin['lng'];

                $originLocationName =
                    $origin;

                $originType =
                    'origin';

                $originSequence =
                    1;


                $locationStmt->bind_param(
                    "isddsi",
                    $savedRouteId,
                    $originLocationName,
                    $originLat,
                    $originLng,
                    $originType,
                    $originSequence
                );


                if (!$locationStmt->execute()) {
                    throw new Exception(
                        $locationStmt->error
                    );
                }


                /* =============================================
                   SAVE DESTINATIONS
                   Sequence = 2, 3, 4...
                ============================================= */

                $destinationType =
                    'destination';

                $sequence =
                    2;


                foreach (
                    $routeData['destinations']
                    as $mapDestination
                ) {

                    if (
                        !isset($mapDestination['lat']) ||
                        !isset($mapDestination['lng'])
                    ) {

                        throw new Exception(
                            'Invalid destination coordinates.'
                        );
                    }


                    $destinationLat =
                        (float)
                        $mapDestination['lat'];

                    $destinationLng =
                        (float)
                        $mapDestination['lng'];


                    /*
                     * Currently the map data only contains
                     * coordinates, so use the final destination
                     * field as the location name.
                     */

                    $destinationLocationName =
                        $destination;


                    $locationStmt->bind_param(
                        "isddsi",
                        $savedRouteId,
                        $destinationLocationName,
                        $destinationLat,
                        $destinationLng,
                        $destinationType,
                        $sequence
                    );


                    if (!$locationStmt->execute()) {
                        throw new Exception(
                            $locationStmt->error
                        );
                    }


                    $sequence++;
                }
            }
        }


        /* ================= COMMIT ================= */

        $conn->commit();


        echo json_encode([
            'status' => 'success',
            'message' =>
                $form_mode === 'edit'
                    ? 'Routes updated successfully.'
                    : 'Routes added successfully.'
        ]);

    } catch (Exception $e) {

        $conn->rollback();


        echo json_encode([
            'status' => 'error',
            'message' =>
                $e->getMessage()
        ]);
    }

    exit;
}


/* 🔎 DEBUG — TEMPORARY */
// var_dump($dateFrom, $dateTo);
// exit;
// var_dump($_SESSION['role'], $_SESSION['user_id'], $_SESSION['area'], $_SESSION['department_id']);
// exit;


/* =========================================================
   FETCH Routes LIST
========================================================= */

$conn->begin_transaction();

$stmt = $conn->prepare("
    SELECT
        id,
        area,
        route,
        origin,
        destination,
        distance_km,
        fuel_allocation,
        is_fixed_fuel,
        status,
        remarks
    FROM routes
    ORDER BY id DESC
");

$stmt->execute();
$result =
    $stmt->get_result();


/* =========================================================
   FETCH DISTINCT AREAS FOR FILTER DROPDOWN
========================================================= */

$areaStmt = $conn->prepare("
    SELECT DISTINCT area_name
    FROM areas
    ORDER BY area_name ASC
");

$areaStmt->execute();
$areaResult =
    $areaStmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>Dashboard | e-GSlip</title>

    <!-- CSS -->

    <link
        rel="stylesheet"
        href="assets/css/sweetalert2.min.css"
    >

    <link
        rel="stylesheet"
        href="assets/css/bootstrap.min.css"
    >

    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >

    <link
        rel="stylesheet"
        href="assets/fontawesome/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="assets/css/icons/bootstrap-icons.css"
    >

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    >

    <script src="assets/js/sweetalert2.all.min.js"></script>

</head>

<body>

<?php include 'includes/sidebar.php'; ?>

<?php include 'includes/km_modal.php'; ?>

<?php include 'includes/add_routes_modal.php'; ?>


<div class="app-content">

    <main class="main-content">

        <div class="panel-container">

            <!-- Page Header -->

            <div class="page-header mb-4">

                <h1 class="mb-1">
                    Routes Management
                </h1>

                <p class="page-subtitle mb-1">
                    Maintain route records with distance,
                    fuel computation reference, and activation
                    status for gas slip processing.
                </p>

            </div>


            <button
                class="btn btn-primary btn-sm"
                data-bs-toggle="modal"
                data-bs-target="#addRoutesModal"
                onclick="resetRoutesForm()"
            >
                + Add Routes
            </button>


            <!-- Routes Table -->

            <table
                class="styled-table excel-table"
                id="RoutesTable"
            >

                <thead>

                    <tr class="group-header">

                        <th>No.</th>

                        <th>Area</th>

                        <th>Route</th>

                        <th>Origin</th>

                        <th>Destination</th>

                        <th>Distance (km)</th>

                        <th>Fuel Allocation</th>

                        <th>Status</th>

                        <th>Remarks</th>

                    </tr>

                </thead>


                <tbody>

                <?php if ($result->num_rows > 0): ?>

                    <?php $no = 1; ?>

                    <?php while (
                        $row =
                        $result->fetch_assoc()
                    ): ?>

                        <tr
                            class="Routes-row"

                            data-id="<?= (int) $row['id'] ?>"

                            data-area="<?=
                                htmlspecialchars(
                                    $row['area'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                            ?>"

                            data-route="<?=
                                htmlspecialchars(
                                    $row['route'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                            ?>"

                            data-origin="<?=
                                htmlspecialchars(
                                    $row['origin'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                            ?>"

                            data-destination="<?=
                                htmlspecialchars(
                                    $row['destination'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                            ?>"

                            data-distance_km="<?=
                                htmlspecialchars(
                                    $row['distance_km'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                            ?>"

                            data-fuel_allocation="<?=
                                htmlspecialchars(
                                    $row['fuel_allocation'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                            ?>"

                            data-is_fixed_fuel="<?=
                                (int)
                                $row['is_fixed_fuel']
                            ?>"

                            data-status="<?=
                                htmlspecialchars(
                                    $row['status'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                            ?>"

                            data-remarks="<?=
                                htmlspecialchars(
                                    $row['remarks'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                            ?>"
                        >

                            <td>
                                <?= $no++; ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row['area']
                                ) ?>
                            </td>

                            <td>
                                <?= $row['route'] !== null
                                    ? htmlspecialchars(
                                        $row['route']
                                    )
                                    : '' ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row['origin']
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row['destination']
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row['distance_km']
                                ) ?>
                            </td>

                            <td>
                                <?= $row['fuel_allocation'] !== null
                                    ? htmlspecialchars(
                                        $row['fuel_allocation']
                                    )
                                    : '' ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row['status']
                                ) ?>
                            </td>

                            <td>
                                <?= $row['remarks'] !== null
                                    ? htmlspecialchars(
                                        $row['remarks']
                                    )
                                    : '' ?>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="9"
                            class="text-center text-muted py-4"
                        >
                            No routes found
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </main>

</div>


<script src="assets/js/jquery.min.js"></script>

<script src="assets/js/jquery.dataTables.min.js"></script>

<script src="assets/js/bootstrap.bundle.min.js"></script>

<!-- Leaflet JavaScript -->

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script src="assets/js/sweetalert2.all.min.js"></script>

<script src="assets/js/app-ui.js"></script>


<?php if (!empty($_SESSION['swal_success'])): ?>

<script>

Swal.fire({

    icon: 'success',

    title: 'Success',

    text: '<?= $_SESSION['swal_success']; ?>',

    timer: 2000,

    showConfirmButton: false

});

</script>

<?php
unset($_SESSION['swal_success']);
endif;
?>

<script>
    const userAreaName = <?= json_encode($areaName) ?>;
</script>

<script src="assets/js/routes.js"></script>
<script src="assets/js/route-planner.js"></script>


</body>
</html>