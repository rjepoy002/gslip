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

$(function () {


/* =========================================
   DATATABLE INITIALIZATION
========================================= */

if (
    $.fn.DataTable.isDataTable(
        '#RoutesTable'
    )
) {

    $('#RoutesTable')
        .DataTable()
        .destroy();

}


const table =
    $('#RoutesTable').DataTable({

        paging: true,

        searching: true,

        ordering: true,

        responsive: true,

        pageLength: 15,

        pagingType: "full_numbers",

        lengthMenu: [
            [15, 25, 50, 75, 100],
            [15, 25, 50, 75, 100]
        ],

        language: {

            lengthMenu:
                'Rows per page: _MENU_',

            search: '',

            searchPlaceholder:
                'Search...',

            infoCallback:
                function (
                    settings,
                    start,
                    end,
                    max,
                    total
                ) {

                    return (
                        'Entries: ' +
                        start +
                        '–' +
                        end +
                        '  |  Total: ' +
                        total
                    );

                },

            paginate: {

                first: 'First',

                previous: '<',

                next: '>',

                last: 'Last'

            }

        },

        dom:
            '<"d-flex justify-content-between align-items-center mb-2"fl>' +
            'rt' +
            '<"d-flex justify-content-between align-items-center mt-3"ip>'

    });


/* =========================================
   ROW CLICK → EDIT MODE
========================================= */

$(document).on(
    'click',
    '.Routes-row',
    function () {

        const routeId =
            $(this).data('id');


        const modalElement =
            document.getElementById(
                'addRoutesModal'
            );


        const modal =
            new bootstrap.Modal(
                modalElement
            );


        /* Fill route form */

        $('#routes_id').val(
            routeId
        );

        $('#area').val(
            $(this).data('area')
        );


        /* Get saved Route value */

        const savedRoute =
            (
                $(this).data('route') || ''
            )
            .toString()
            .trim();


        /*
         * Automatically check 2-Wheels Route
         * when a Route value exists.
         */

        const twoWheelsCheckbox =
            document.getElementById(
                'twoWheelsRoute'
            );


        if (twoWheelsCheckbox) {

            twoWheelsCheckbox.checked =
                savedRoute !== '';

            twoWheelsCheckbox.dispatchEvent(
                new Event('change')
            );

        }


        /* Restore saved Route value */

        $('#route_input').val(
            savedRoute
        );


        /* Continue loading fields */

        $('#origin').val(
            $(this).data('origin')
        );

        $('#destination').val(
            $(this).data('destination')
        );

        $('#distance_km').val(
            $(this).data('distance_km')
        );

        $('#fuel_allocation').val(
            $(this).data('fuel_allocation')
        );


        /* Restore Fixed Fuel */

        $('#is_fixed_fuel').prop(
            'checked',
            Number(
                $(this).data('is_fixed_fuel')
            ) === 1
        );


        toggleFixedFuel();


        $('#status').val(
            $(this).data('status')
        );

        $('#remarks').val(
            $(this).data('remarks')
        );


        $('#formTitle').text(
            'Edit Routes'
        );

        $('#addBtn').addClass(
            'd-none'
        );

        $('#updateBtn').removeClass(
            'd-none'
        );

        $('#form_mode').val(
            'edit'
        );


        /* Show loading overlay */

        showRouteLoading(
            'Loading saved route...'
        );


        /* Open modal */

        modal.show();


        /*
         * Wait until modal is visible.
         */

        $(modalElement).one(
            'shown.bs.modal',
            async function () {

                /*
                 * Fixed Fuel routes do not
                 * load saved map locations.
                 */

                const fixedFuelCheckbox =
                    document.getElementById(
                        'is_fixed_fuel'
                    );


                if (
                    fixedFuelCheckbox &&
                    fixedFuelCheckbox.checked
                ) {

                    toggleFixedFuel();


                    const distanceInput =
                        document.getElementById(
                            'distance_km'
                        );


                    if (distanceInput) {
                        distanceInput.value = '0.1';
                    }


                    const statusElement =
                        document.getElementById(
                            'routePlannerStatus'
                        );


                    if (statusElement) {

                        statusElement.textContent =
                            'Fixed Fuel route. Map is disabled.';

                    }


                    hideRouteLoading();

                    return;
                }


                try {

                    const response =
                        await fetch(
                            'routes.php?action=get_route_locations&route_id=' +
                            encodeURIComponent(
                                routeId
                            )
                        );


                    const data =
                        await response.json();


                    if (
                        data.status !==
                        'success'
                    ) {

                        console.error(
                            'Unable to load route locations:',
                            data.message
                        );

                        hideRouteLoading();

                        return;
                    }


                    const locations =
                        data.locations || [];


                    if (
                        locations.length === 0
                    ) {

                        console.warn(
                            'No saved locations found for route:',
                            routeId
                        );

                        hideRouteLoading();

                        return;
                    }


                    /* Clear previous map data */

                    clearRoutePlanner();


                    /* Make sure map is ready */

                    if (!routeMap) {

                        console.error(
                            'Route map is not initialized.'
                        );

                        hideRouteLoading();

                        return;
                    }


                    /*
                     * Get origin
                     */

                    const savedOrigin =
                        locations.find(
                            location =>
                                location.location_type ===
                                'origin'
                        );


                    if (!savedOrigin) {

                        console.error(
                            'No origin found for saved route.'
                        );

                        hideRouteLoading();

                        return;
                    }


                    /*
                     * Add origin pin
                     */

                    setRouteOrigin(
                        parseFloat(
                            savedOrigin.latitude
                        ),
                        parseFloat(
                            savedOrigin.longitude
                        )
                    );


                    /*
                     * Add destination pins
                     */

                    const destinations =
                        locations
                        .filter(
                            location =>
                                location.location_type ===
                                'destination'
                        )
                        .sort(
                            (a, b) =>
                                parseInt(
                                    a.sequence
                                ) -
                                parseInt(
                                    b.sequence
                                )
                        );


                    destinations.forEach(
                        function (location) {

                            addRouteDestination(
                                parseFloat(
                                    location.latitude
                                ),
                                parseFloat(
                                    location.longitude
                                )
                            );

                        }
                    );


                    /*
                     * Generate complete route.
                     */

                    await finalizeRoute();


                    /*
                     * Zoom map to saved pins
                     */

                    const mapBounds =
                        locations.map(
                            location => [
                                parseFloat(
                                    location.latitude
                                ),
                                parseFloat(
                                    location.longitude
                                )
                            ]
                        );


                    routeMap.invalidateSize();


                    if (
                        mapBounds.length > 1
                    ) {

                        routeMap.fitBounds(
                            mapBounds,
                            {
                                padding: [60, 60],
                                maxZoom: 15
                            }
                        );

                    } else if (
                        mapBounds.length === 1
                    ) {

                        routeMap.setView(
                            mapBounds[0],
                            15
                        );

                    }


                    const statusElement =
                        document.getElementById(
                            'routePlannerStatus'
                        );


                    if (statusElement) {

                        statusElement.textContent =
                            'Saved route loaded.';

                    }


                    console.log(
                        'Saved route loaded successfully.',
                        locations
                    );


                    routeMap.invalidateSize();


                    /*
                     * Allow map to paint before
                     * removing loading overlay.
                     */

                    await new Promise(
                        resolve => {

                            requestAnimationFrame(
                                () => {

                                    requestAnimationFrame(
                                        () => {

                                            setTimeout(
                                                resolve,
                                                500
                                            );

                                        }
                                    );

                                }
                            );

                        }
                    );


                    hideRouteLoading();


                } catch (error) {

                    console.error(
                        'Error loading saved route:',
                        error
                    );

                    hideRouteLoading();

                }

            }
        );

    }
);


/* =========================================
   SAVE Routes (AJAX)
========================================= */

$('#RoutesForm').on(
    'submit',
    function (e) {

        e.preventDefault();


        $.ajax({

            url: 'routes.php',

            type: 'POST',

            data: $(this).serialize(),

            dataType: 'json',


            success:
                function (response) {

                    if (
                        response.status ===
                        'success'
                    ) {

                        Swal.fire({

                            icon: 'success',

                            title: 'Success',

                            text:
                                response.message,

                            timer: 2000,

                            showConfirmButton:
                                false

                        }).then(
                            () => {
                                location.reload();
                            }
                        );

                    } else {

                        Swal.fire({

                            icon: 'error',

                            title: 'Error',

                            text:
                                response.message

                        });

                    }

                },


            error:
                function (xhr) {

                    let message =
                        "Unknown server error.";


                    try {

                        const json =
                            JSON.parse(
                                xhr.responseText
                            );


                        if (json.message) {

                            message =
                                json.message;

                        }

                    } catch (e) {

                        message =
                            xhr.responseText;

                    }


                    Swal.fire({

                        icon: 'error',

                        title: 'Server Error',

                        text: message

                    });

                }

        });

    }
);

});


/* =========================================================
   RESET ROUTES FORM
========================================================= */

function resetRoutesForm() {

    $('#RoutesForm')[0].reset();

    $('#routes_id').val('');

    $('#form_mode').val('add');

    $('#formTitle').text(
        'Add New Routes'
    );

    // Default Area to user's assigned area
    $('#area').val(
        <?= json_encode($areaName) ?>
    );

    // Update Origin based on selected Area
    copyAreaToOrigin();

    $('#addBtn').removeClass(
        'd-none'
    );

    $('#updateBtn').addClass(
        'd-none'
    );


    /*
     * Reset Route / Fuel Allocation
     * visibility.
     */

    toggleTwoWheelsRoute();

    /*
     * Reset Fixed Fuel state.
     */

    toggleFixedFuel();
}


/* =========================================================
   AUTO-FILL ORIGIN FROM AREA
========================================================= */

function copyAreaToOrigin() {

    const areaSelect =
        document.getElementById('area');

    const selectedText =
        areaSelect
        .options[
            areaSelect.selectedIndex
        ]
        .text;

    document.getElementById(
        'origin'
    ).value =
        selectedText;
}


/* =========================================================
   TOGGLE 2-WHEELS ROUTE
========================================================= */

function toggleTwoWheelsRoute() {

    const twoWheelsCheckbox =
        document.getElementById(
            'twoWheelsRoute'
        );

    const routeRow =
        document.getElementById(
            'route_row'
        );

    const routeInput =
        document.getElementById(
            'route_input'
        );

    const fuelAllocationRow =
        document.getElementById(
            'fuel_allocation_row'
        );

    const fuelInput =
        document.getElementById(
            'fuel_allocation'
        );

    const fixedFuelRow =
        document.getElementById(
            'fixed_fuel_row'
        );

    const fixedFuelCheckbox =
        document.getElementById(
            'is_fixed_fuel'
        );


    if (
        !twoWheelsCheckbox ||
        !routeRow ||
        !routeInput ||
        !fuelAllocationRow ||
        !fuelInput
    ) {
        return;
    }


    const isTwoWheels =
        twoWheelsCheckbox.checked;


    /* ---------------------------------------------------------
       SHOW / HIDE 2-WHEELS FIELDS
    --------------------------------------------------------- */

    routeRow.style.display =
        isTwoWheels
            ? ''
            : 'none';

    fuelAllocationRow.style.display =
        isTwoWheels
            ? ''
            : 'none';


    /* ---------------------------------------------------------
       SHOW / HIDE FIXED FUEL
    --------------------------------------------------------- */

    if (fixedFuelRow) {

        fixedFuelRow.style.display =
            isTwoWheels
                ? ''
                : 'none';

    }


    /* ---------------------------------------------------------
       2-WHEELS ON
    --------------------------------------------------------- */

    if (isTwoWheels) {

        routeInput.required =
            true;

        fuelInput.required =
            true;

    }


    /* ---------------------------------------------------------
       2-WHEELS OFF
    --------------------------------------------------------- */

    else {

        routeInput.required =
            false;

        fuelInput.required =
            false;


        routeInput.value =
            '';

        fuelInput.value =
            '';


        /* Turn Fixed Fuel OFF */

        if (fixedFuelCheckbox) {

            fixedFuelCheckbox.checked =
                false;

        }

    }


    /* ---------------------------------------------------------
       APPLY FIXED FUEL BEHAVIOR
    --------------------------------------------------------- */

    if (typeof toggleFixedFuel === 'function') {

        toggleFixedFuel();

    }

}


/* =========================================================
   TOGGLE FIXED FUEL
========================================================= */

function toggleFixedFuel() {

    const fixedFuelCheckbox =
        document.getElementById(
            'is_fixed_fuel'
        );

    const distanceInput =
        document.getElementById(
            'distance_km'
        );

    const routeRow =
        document.getElementById(
            'route_row'
        );

    const routeInput =
        document.getElementById(
            'route_input'
        );

    const routeMapElement =
        document.getElementById(
            'routeMap'
        );

    const routeSearchInput =
        document.getElementById(
            'routeSearchInput'
        );

    const routeSearchBtn =
        document.getElementById(
            'routeSearchBtn'
        );

    const useCurrentLocationBtn =
        document.getElementById(
            'useCurrentLocationBtn'
        );

    const finalizeButton =
        document.getElementById(
            'finalizeRouteBtn'
        );


    if (!fixedFuelCheckbox) {
        return;
    }


    const isFixedFuel =
        fixedFuelCheckbox.checked;


    if (isFixedFuel) {

        /* -----------------------------------------------------
           HIDE ROUTE
        ----------------------------------------------------- */

        if (routeRow) {

            routeRow.style.display =
                'none';

        }


        /* -----------------------------------------------------
           Route is not required for Fixed Fuel.
        ----------------------------------------------------- */

        if (routeInput) {

            routeInput.required =
                false;

        }


        /* -----------------------------------------------------
           HIDE DISTANCE
        ----------------------------------------------------- */

        if (distanceInput) {

            const distanceRow =
                distanceInput.closest(
                    '.form-floating'
                );

            if (distanceRow) {

                distanceRow.style.display =
                    'none';

            }

        }


        /* -----------------------------------------------------
           Fixed Fuel always uses distance 0.
        ----------------------------------------------------- */

        if (distanceInput) {

            distanceInput.value =
                '0.1';

        }


        /* -----------------------------------------------------
           Disable map.
        ----------------------------------------------------- */

        if (routeMapElement) {

            routeMapElement.style.pointerEvents =
                'none';

            routeMapElement.style.opacity =
                '0.5';

        }


        /* -----------------------------------------------------
           Disable map controls.
        ----------------------------------------------------- */

        if (routeSearchInput) {

            routeSearchInput.disabled =
                true;

        }

        if (routeSearchBtn) {

            routeSearchBtn.disabled =
                true;

        }

        if (useCurrentLocationBtn) {

            useCurrentLocationBtn.disabled =
                true;

        }

        if (finalizeButton) {

            finalizeButton.disabled =
                true;

        }


        /* -----------------------------------------------------
           Clear existing map route.
        ----------------------------------------------------- */

        clearRoutePlanner();


        /* -----------------------------------------------------
           Distance must remain zero.
        ----------------------------------------------------- */

        if (distanceInput) {

            distanceInput.value =
                '0.1';

        }


    } else {

        /* -----------------------------------------------------
           SHOW ROUTE
        ----------------------------------------------------- */

        if (routeRow) {

            const twoWheelsCheckbox =
                document.getElementById(
                    'twoWheelsRoute'
                );

            routeRow.style.display =
                twoWheelsCheckbox &&
                twoWheelsCheckbox.checked
                    ? ''
                    : 'none';

        }


        /* -----------------------------------------------------
           Restore Route requirement according
           to the 2-Wheels Route setting.
        ----------------------------------------------------- */

        if (routeInput) {

            const twoWheelsCheckbox =
                document.getElementById(
                    'twoWheelsRoute'
                );

            routeInput.required =
                twoWheelsCheckbox
                    ? twoWheelsCheckbox.checked
                    : false;

        }


        /* -----------------------------------------------------
           SHOW DISTANCE
        ----------------------------------------------------- */

        if (distanceInput) {

            const distanceRow =
                distanceInput.closest(
                    '.form-floating'
                );

            if (distanceRow) {

                distanceRow.style.display =
                    '';

            }

        }


        /* -----------------------------------------------------
           Enable map again.
        ----------------------------------------------------- */

        if (routeMapElement) {

            routeMapElement.style.pointerEvents =
                '';

            routeMapElement.style.opacity =
                '';

        }


        /* -----------------------------------------------------
           Enable map controls.
        ----------------------------------------------------- */

        if (routeSearchInput) {

            routeSearchInput.disabled =
                false;

        }

        if (routeSearchBtn) {

            routeSearchBtn.disabled =
                false;

        }

        if (useCurrentLocationBtn) {

            useCurrentLocationBtn.disabled =
                false;

        }


        /* -----------------------------------------------------
           Distance will be calculated
           after route finalization.
        ----------------------------------------------------- */

        if (distanceInput) {

            distanceInput.value =
                '';

        }


        updateDestinationList();

    }

}


/* =========================================================
   INITIALIZE CHECKBOX LISTENERS
========================================================= */

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const twoWheelsCheckbox =
            document.getElementById(
                'twoWheelsRoute'
            );


        if (twoWheelsCheckbox) {

            twoWheelsCheckbox.addEventListener(
                'change',
                toggleTwoWheelsRoute
            );

            toggleTwoWheelsRoute();

        }


        const fixedFuelCheckbox =
            document.getElementById(
                'is_fixed_fuel'
            );


        if (fixedFuelCheckbox) {

            fixedFuelCheckbox.addEventListener(
                'change',
                toggleFixedFuel
            );

            toggleFixedFuel();

        }

    }
);


/* =========================================================
   ROUTE LOADING OVERLAY
========================================================= */

function showRouteLoading(
    message = 'Loading Route...'
) {

    const overlay =
        document.getElementById(
            'routeLoadingOverlay'
        );

    const messageElement =
        document.getElementById(
            'routeLoadingMessage'
        );


    if (!overlay) {

        console.error(
            'routeLoadingOverlay not found.'
        );

        return;
    }


    if (messageElement) {

        messageElement.textContent =
            message;

    }


    overlay.classList.remove(
        'd-none'
    );

}


function hideRouteLoading() {

    const overlay =
        document.getElementById(
            'routeLoadingOverlay'
        );


    if (!overlay) {
        return;
    }


    overlay.classList.add(
        'd-none'
    );

}


/* =========================================================
   ROUTE MAP INITIALIZATION
========================================================= */

let routeMap = null;


const addRoutesModal =
    document.getElementById(
        'addRoutesModal'
    );


if (addRoutesModal) {

    addRoutesModal.addEventListener(
        'shown.bs.modal',
        function () {

            console.log(
                'Add Routes modal opened'
            );


            /* Check Leaflet */

            if (
                typeof L === 'undefined'
            ) {

                console.error(
                    'Leaflet JavaScript is not loaded.'
                );

                return;
            }


            /* Create map only once */

            if (!routeMap) {

                routeMap =
                    L.map(
                        'routeMap',
                        {
                            minZoom: 5,
                            maxZoom: 18
                        }
                    )
                    .setView(
                        [9.7392, 118.7353],
                        13
                    );


                /* =========================================
                   BASE MAP LAYERS
                ========================================= */

                const streetLayer =
                    L.tileLayer(
                        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                        {
                            maxNativeZoom: 18,
                            maxZoom: 18,
                            attribution:
                                '&copy; OpenStreetMap contributors'
                        }
                    );


                const satelliteLayer =
                    L.tileLayer(
                        'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
                        {
                            maxNativeZoom: 17,
                            maxZoom: 18,
                            attribution:
                                'Tiles &copy; Esri'
                        }
                    );


                /* DEFAULT MAP */

                streetLayer.addTo(
                    routeMap
                );


                /* MAP SWITCHER */

                const baseMaps = {

                    "Street Map":
                        streetLayer,

                    "Satellite":
                        satelliteLayer

                };


                L.control.layers(
                    baseMaps,
                    null,
                    {
                        position:
                            'topright',

                        collapsed:
                            false
                    }
                )
                .addTo(
                    routeMap
                );


                console.log(
                    'Route map created'
                );


                /* =========================================
                   MAP CLICK
                ========================================= */

                routeMap.on(
                    'click',
                    function (e) {

                        /*
                         * Fixed Fuel should never
                         * create map pins.
                         */

                        const fixedFuelCheckbox =
                            document.getElementById(
                                'is_fixed_fuel'
                            );


                        if (
                            fixedFuelCheckbox &&
                            fixedFuelCheckbox.checked
                        ) {

                            return;

                        }


                        /*
                         * Prevent adding pins after
                         * route finalization.
                         */

                        if (
                            routeFinalized
                        ) {

                            const status =
                                document.getElementById(
                                    'routePlannerStatus'
                                );


                            if (status) {

                                status.textContent =
                                    'Route is finalized. Clear the route to create a new route.';

                            }


                            return;
                        }


                        /*
                         * First pin = origin.
                         */

                        if (!originLatLng) {

                            setRouteOrigin(
                                e.latlng.lat,
                                e.latlng.lng
                            );

                        } else {

                            addRouteDestination(
                                e.latlng.lat,
                                e.latlng.lng
                            );

                        }

                    }
                );

            }


            /* Fix map size */

            setTimeout(
                function () {

                    routeMap.invalidateSize();

                },
                300
            );

        }
    );

} else {

    console.error(
        'addRoutesModal not found.'
    );

}


/* =========================================================
   MULTI-DESTINATION PINS
========================================================= */

let originMarker = null;

let originLatLng = null;

let destinationMarkers = [];

let destinationPoints = [];

let routeLayers = [];

let routeFinalized = false;


/* =========================================================
   SET ORIGIN
========================================================= */

function setRouteOrigin(
    lat,
    lng
) {

    originLatLng =
        L.latLng(
            lat,
            lng
        );


    if (originMarker) {

        routeMap.removeLayer(
            originMarker
        );

    }


    originMarker =
        L.marker(
            originLatLng,
            {
                draggable:
                    false
            }
        )
        .addTo(
            routeMap
        )
        .bindPopup(
            'Starting Point'
        )
        .openPopup();


    updateDestinationList();


    document.getElementById(
        'routePlannerStatus'
    ).textContent =
        'Starting point set. Add one or more destinations.';


    generateRoadRoutes();

}


/* =========================================================
   USE CURRENT LOCATION
========================================================= */

const useCurrentLocationBtn =
    document.getElementById(
        'useCurrentLocationBtn'
    );


if (useCurrentLocationBtn) {

    useCurrentLocationBtn.addEventListener(
        'click',
        function () {

            /*
             * Fixed Fuel cannot use location.
             */

            const fixedFuelCheckbox =
                document.getElementById(
                    'is_fixed_fuel'
                );


            if (
                fixedFuelCheckbox &&
                fixedFuelCheckbox.checked
            ) {

                return;

            }


            if (routeFinalized) {

                const status =
                    document.getElementById(
                        'routeLocationStatus'
                    );


                if (status) {

                    status.textContent =
                        'Route is finalized. Clear the route to start again.';

                }


                return;
            }


            if (
                !navigator.geolocation
            ) {

                const status =
                    document.getElementById(
                        'routeLocationStatus'
                    );


                if (status) {

                    status.textContent =
                        'Geolocation is not supported by this browser.';

                }


                return;
            }


            useCurrentLocationBtn.disabled =
                true;


            useCurrentLocationBtn.innerHTML =
                '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';


            useCurrentLocationBtn.title =
                'Getting current location...';


            const status =
                document.getElementById(
                    'routeLocationStatus'
                );


            if (status) {

                status.textContent =
                    'Getting your current location...';

            }


            navigator.geolocation.getCurrentPosition(

                function (position) {

                    const lat =
                        position.coords.latitude;

                    const lng =
                        position.coords.longitude;


                    routeMap.setView(
                        [lat, lng],
                        16
                    );


                    if (!originLatLng) {

                        setRouteOrigin(
                            lat,
                            lng
                        );

                    } else {

                        if (status) {

                            status.textContent =
                                'Starting point is already set.';

                        }

                    }


                    useCurrentLocationBtn.disabled =
                        false;


                    useCurrentLocationBtn.innerHTML =
                        '<i class="bi bi-crosshair"></i>';

                    useCurrentLocationBtn.title =
                        'Use Current Location';

                },


                function (error) {

                    let message =
                        'Unable to get your current location.';


                    switch (
                        error.code
                    ) {

                        case error.PERMISSION_DENIED:

                            message =
                                'Location permission was denied.';

                            break;


                        case error.POSITION_UNAVAILABLE:

                            message =
                                'Location information is unavailable.';

                            break;


                        case error.TIMEOUT:

                            message =
                                'Location request timed out.';

                            break;

                    }


                    if (status) {

                        status.textContent =
                            message;

                    }


                    useCurrentLocationBtn.disabled =
                        false;


                    useCurrentLocationBtn.innerHTML =
                        '<i class="bi bi-crosshair"></i>';

                    useCurrentLocationBtn.title =
                        'Use Current Location';

                },


                {
                    enableHighAccuracy:
                        true,

                    timeout:
                        15000,

                    maximumAge:
                        0
                }

            );

        }
    );

}


/* =========================================================
   ADD DESTINATION
========================================================= */

function addRouteDestination(
    lat,
    lng
) {

    /*
     * Fixed Fuel cannot use map.
     */

    const fixedFuelCheckbox =
        document.getElementById(
            'is_fixed_fuel'
        );


    if (
        fixedFuelCheckbox &&
        fixedFuelCheckbox.checked
    ) {

        return;

    }


    if (!originLatLng) {

        alert(
            'Please set the starting point first.'
        );

        return;
    }


    const destinationNumber =
        destinationPoints.length + 1;


    const latLng =
        L.latLng(
            lat,
            lng
        );


    destinationPoints.push({
        lat: lat,
        lng: lng
    });


    const marker =
        L.marker(
            latLng
        )
        .addTo(
            routeMap
        )
        .bindPopup(
            'Destination ' +
            destinationNumber
        );


    destinationMarkers.push(
        marker
    );


    updateDestinationList();

    generateRoadRoutes();

}


/* =========================================================
   CLEAR ROAD ROUTES
========================================================= */

function clearRouteLines() {

    routeLayers.forEach(
        function (layer) {

            if (
                routeMap &&
                routeMap.hasLayer(
                    layer
                )
            ) {

                routeMap.removeLayer(
                    layer
                );

            }

        }
    );


    routeLayers = [];

}


/* =========================================================
   GET ROAD ROUTE FROM OSRM
========================================================= */

async function getRoadRoute(
    start,
    end
) {

    const url =
        'https://router.project-osrm.org/' +
        'route/v1/driving/' +
        `${start.lng},${start.lat};${end.lng},${end.lat}` +
        '?overview=full' +
        '&geometries=geojson';


    try {

        const response =
            await fetch(
                url
            );


        const data =
            await response.json();


        if (
            data.code !== 'Ok' ||
            !data.routes ||
            !data.routes.length
        ) {

            console.error(
                'OSRM route error:',
                data
            );

            return null;
        }


        return data.routes[0];


    } catch (error) {

        console.error(
            'OSRM routing error:',
            error
        );

        return null;

    }

}


/* =========================================================
   GENERATE ROAD ROUTE
========================================================= */

async function generateRoadRoutes() {

    /*
     * Fixed Fuel does not use road routes.
     */

    const fixedFuelCheckbox =
        document.getElementById(
            'is_fixed_fuel'
        );


    if (
        fixedFuelCheckbox &&
        fixedFuelCheckbox.checked
    ) {

        clearRouteLines();

        return;

    }


    if (
        !originLatLng ||
        destinationPoints.length === 0
    ) {

        clearRouteLines();

        return;
    }


    clearRouteLines();


    const locations = [

        {
            lat:
                originLatLng.lat,

            lng:
                originLatLng.lng
        },

        ...destinationPoints

    ];


    let totalDistance =
        0;


    for (
        let i = 0;
        i < locations.length - 1;
        i++
    ) {

        const start =
            locations[i];

        const end =
            locations[i + 1];


        const route =
            await getRoadRoute(
                start,
                end
            );


        if (!route) {
            continue;
        }


        totalDistance +=
            route.distance / 1000;


        const routeLayer =
            L.geoJSON(
                route.geometry,
                {
                    style: {
                        weight: 5,
                        opacity: 0.85
                    }
                }
            )
            .addTo(
                routeMap
            );


        routeLayers.push(
            routeLayer
        );

    }


    const routeDistance =
        document.getElementById(
            'routeTotalDistance'
        );


    if (routeDistance) {

        routeDistance.textContent =
            totalDistance.toFixed(2) +
            ' km';

    }


    const returnDistance =
        document.getElementById(
            'routeReturnDistance'
        );


    if (returnDistance) {

        returnDistance.textContent =
            '0.00 km';

    }


    const finalDistance =
        document.getElementById(
            'routeFinalDistance'
        );


    if (finalDistance) {

        finalDistance.textContent =
            '0.00 km';

    }


    const distanceInput =
        document.getElementById(
            'distance_km'
        );


    if (distanceInput) {

        distanceInput.value =
            '';

    }

}


/* =========================================================
   FINALIZE ROUTE
========================================================= */

async function finalizeRoute() {

    /*
     * Fixed Fuel routes do not finalize a map route.
     */

    const fixedFuelCheckbox =
        document.getElementById(
            'is_fixed_fuel'
        );


    if (
        fixedFuelCheckbox &&
        fixedFuelCheckbox.checked
    ) {

        const distanceInput =
            document.getElementById(
                'distance_km'
            );


        if (distanceInput) {

            distanceInput.value =
                '0.1';

        }


        return true;

    }


    if (!originLatLng) {

        alert(
            'Please set the starting point first.'
        );

        return false;
    }


    if (
        destinationPoints.length === 0
    ) {

        alert(
            'Please add at least one destination.'
        );

        return false;
    }


    if (routeFinalized) {

        return false;

    }


    const finalizeButton =
        document.getElementById(
            'finalizeRouteBtn'
        );


    if (!finalizeButton) {

        return false;

    }


    finalizeButton.disabled =
        true;

    finalizeButton.textContent =
        'Calculating Return Route...';


    const status =
        document.getElementById(
            'routePlannerStatus'
        );


    if (status) {

        status.textContent =
            'Calculating route and return trip...';

    }


    clearRouteLines();


    const locations = [

        {
            lat:
                originLatLng.lat,

            lng:
                originLatLng.lng
        },

        ...destinationPoints

    ];


    let outgoingDistance =
        0;


    /*
     * ORIGIN → DESTINATION 1 →
     * DESTINATION 2...
     */

    for (
        let i = 0;
        i < locations.length - 1;
        i++
    ) {

        const route =
            await getRoadRoute(
                locations[i],
                locations[i + 1]
            );


        if (!route) {

            continue;

        }


        outgoingDistance +=
            route.distance / 1000;


        const routeLayer =
            L.geoJSON(
                route.geometry,
                {
                    style: {
                        color: '#0d6efd',
                        weight: 5,
                        opacity: 0.85
                    }
                }
            )
            .addTo(
                routeMap
            );


        routeLayers.push(
            routeLayer
        );

    }


    /*
     * LAST DESTINATION → INITIAL PIN
     */

    const lastDestination =
        locations[
            locations.length - 1
        ];


    const startingPoint =
        locations[0];


    const returnRoute =
        await getRoadRoute(
            lastDestination,
            startingPoint
        );


    if (!returnRoute) {

        alert(
            'Unable to calculate the return route.'
        );


        finalizeButton.disabled =
            false;


        finalizeButton.textContent =
            'Finalize Route';


        return false;

    }


    const returnDistance =
        returnRoute.distance / 1000;


    const returnRouteLayer =
        L.geoJSON(
            returnRoute.geometry,
            {
                style: {
                    color: '#dc3545',
                    weight: 4,
                    opacity: 0.9,
                    dashArray: '10, 10'
                }
            }
        )
        .addTo(
            routeMap
        );


    routeLayers.push(
        returnRouteLayer
    );


    /*
     * FINAL DISTANCE
     */

    const finalDistance =
        outgoingDistance +
        returnDistance;


    /*
     * UPDATE DISPLAYS
     */

    const routeTotalDistance =
        document.getElementById(
            'routeTotalDistance'
        );


    if (routeTotalDistance) {

        routeTotalDistance.textContent =
            outgoingDistance.toFixed(2) +
            ' km';

    }


    const routeReturnDistance =
        document.getElementById(
            'routeReturnDistance'
        );


    if (routeReturnDistance) {

        routeReturnDistance.textContent =
            returnDistance.toFixed(2) +
            ' km';

    }


    const routeFinalDistance =
        document.getElementById(
            'routeFinalDistance'
        );


    if (routeFinalDistance) {

        routeFinalDistance.textContent =
            finalDistance.toFixed(2) +
            ' km';

    }


    /*
     * SAVE FINAL DISTANCE
     */

    const distanceInput =
        document.getElementById(
            'distance_km'
        );


    if (distanceInput) {

        distanceInput.value =
            finalDistance.toFixed(2);

    }


    /* =====================================================
       COMPUTE FUEL ALLOCATION - 2 WHEELS

       Formula:
       (Final Distance / 30 km/L)
       + (Number of Destination Pins × 0.6 L)
    ===================================================== */

    const fuelAllocationInput =
        document.getElementById(
            'fuel_allocation'
        );


    const twoWheelsCheckbox =
        document.getElementById(
            'twoWheelsRoute'
        );


    const fixedFuelCheckbox2 =
        document.getElementById(
            'is_fixed_fuel'
        );


    if (
        fuelAllocationInput &&
        twoWheelsCheckbox &&
        twoWheelsCheckbox.checked
    ) {

        const isFixedFuel =
            fixedFuelCheckbox2 &&
            fixedFuelCheckbox2.checked;


        if (!isFixedFuel) {

            const destinationCount =
                destinationPoints.length;


            const fuelAllocation =
                (finalDistance / 30) +
                (
                    destinationCount *
                    0.6
                );


            fuelAllocationInput.value =
                fuelAllocation.toFixed(2);

        }

        /*
         * Fixed Fuel:
         * keep manually entered fuel allocation.
         */

    } else if (fuelAllocationInput) {

        fuelAllocationInput.value =
            '';

    }


    /*
     * SAVE ROUTE DATA
     */

    const routeDataInput =
        document.getElementById(
            'route_data'
        );


    if (routeDataInput) {

        routeDataInput.value =
            JSON.stringify({

                origin:
                    startingPoint,

                destinations:
                    destinationPoints,

                route_distance:
                    outgoingDistance,

                return_distance:
                    returnDistance,

                final_distance:
                    finalDistance

            });

    }


    /*
     * MARK FINALIZED
     */

    routeFinalized =
        true;


    updateDestinationList();


    finalizeButton.textContent =
        'Route Finalized';


    if (status) {

        status.textContent =
            'Route finalized. Return trip added to the starting point.';

    }


    /*
     * FIT COMPLETE ROUTE
     */

    if (
        routeLayers.length > 0
    ) {

        const group =
            L.featureGroup(
                routeLayers
            );


        routeMap.fitBounds(
            group.getBounds(),
            {
                padding: [30, 30]
            }
        );

    }


    return true;

}


document
    .getElementById(
        'finalizeRouteBtn'
    )
    ?.addEventListener(
        'click',
        async function () {

            await finalizeRoute();

        }
    );


/* =========================================================
   UPDATE DESTINATION LIST
========================================================= */

function updateDestinationList() {

    const list =
        document.getElementById(
            'routeDestinationList'
        );


    if (!list) {

        return;

    }


    list.innerHTML =
        '';


    if (!originLatLng) {

        list.innerHTML = `
            <div class="text-muted small p-2">
                No starting point selected.
            </div>
        `;


        const sequenceCount =
            document.getElementById(
                'routeSequenceCount'
            );


        if (sequenceCount) {

            sequenceCount.textContent =
                '0 Locations';

        }


        return;

    }


    /* STARTING POINT */

    const originItem =
        document.createElement(
            'div'
        );


    originItem.className =
        'list-group-item d-flex justify-content-between align-items-center';


    originItem.innerHTML = `
        <div>
            <strong>1. Starting Point</strong><br>
            <small class="text-muted">
                ${originLatLng.lat.toFixed(6)},
                ${originLatLng.lng.toFixed(6)}
            </small>
        </div>

        <button
            type="button"
            class="btn btn-danger btn-sm remove-origin-btn"
            ${routeFinalized ? 'disabled' : ''}
        >
            Clear Route
        </button>
    `;


    list.appendChild(
        originItem
    );


    /* DESTINATIONS */

    destinationPoints.forEach(
        function (
            point,
            index
        ) {

            const item =
                document.createElement(
                    'div'
                );


            item.className =
                'list-group-item d-flex justify-content-between align-items-center';


            item.innerHTML = `
                <div>
                    <strong>${index + 2}. Destination</strong><br>
                    <small class="text-muted">
                        ${point.lat.toFixed(6)},
                        ${point.lng.toFixed(6)}
                    </small>
                </div>

                <button
                    type="button"
                    class="btn btn-danger btn-sm remove-destination-btn"
                    data-index="${index}"
                    ${routeFinalized ? 'disabled' : ''}
                >
                    Remove
                </button>
            `;


            list.appendChild(
                item
            );

        }
    );


    /* UPDATE COUNTS */

    const sequenceCount =
        document.getElementById(
            'routeSequenceCount'
        );


    if (sequenceCount) {

        const count =
            destinationPoints.length +
            1;


        sequenceCount.textContent =
            `${count} ${
                count === 1
                    ? 'Location'
                    : 'Locations'
            }`;

    }


    /* UPDATE FINALIZE BUTTON */

    const finalizeButton =
        document.getElementById(
            'finalizeRouteBtn'
        );


    if (finalizeButton) {

        finalizeButton.disabled =
            !originLatLng ||
            destinationPoints.length === 0 ||
            routeFinalized;

    }

}


/* =========================================================
   REMOVE STARTING POINT
========================================================= */

document.addEventListener(
    'click',
    function (e) {

        const removeOriginButton =
            e.target.closest(
                '.remove-origin-btn'
            );


        if (!removeOriginButton) {

            return;

        }


        if (routeFinalized) {

            return;

        }


        if (originMarker) {

            routeMap.removeLayer(
                originMarker
            );

        }


        destinationMarkers.forEach(
            function (marker) {

                if (
                    marker &&
                    routeMap.hasLayer(
                        marker
                    )
                ) {

                    routeMap.removeLayer(
                        marker
                    );

                }

            }
        );


        clearRouteLines();


        originMarker =
            null;

        originLatLng =
            null;

        destinationMarkers =
            [];

        destinationPoints =
            [];


        const routeDistance =
            document.getElementById(
                'routeTotalDistance'
            );


        if (routeDistance) {

            routeDistance.textContent =
                '0.00 km';

        }


        const returnDistance =
            document.getElementById(
                'routeReturnDistance'
            );


        if (returnDistance) {

            returnDistance.textContent =
                '0.00 km';

        }


        const finalDistance =
            document.getElementById(
                'routeFinalDistance'
            );


        if (finalDistance) {

            finalDistance.textContent =
                '0.00 km';

        }


        const distanceInput =
            document.getElementById(
                'distance_km'
            );


        if (distanceInput) {

            distanceInput.value =
                '';

        }


        const routeData =
            document.getElementById(
                'route_data'
            );


        if (routeData) {

            routeData.value =
                '';

        }


        updateDestinationList();


        const status =
            document.getElementById(
                'routePlannerStatus'
            );


        if (status) {

            status.textContent =
                'Starting point removed. Click the map or search for a new starting point.';

        }

    }
);


/* =========================================================
   REMOVE SELECTED DESTINATION
========================================================= */

document.addEventListener(
    'click',
    function (e) {

        const removeButton =
            e.target.closest(
                '.remove-destination-btn'
            );


        if (!removeButton) {

            return;

        }


        if (routeFinalized) {

            return;

        }


        const index =
            parseInt(
                removeButton.dataset.index,
                10
            );


        if (isNaN(index)) {

            return;

        }


        const marker =
            destinationMarkers[index];


        if (
            marker &&
            routeMap &&
            routeMap.hasLayer(
                marker
            )
        ) {

            routeMap.removeLayer(
                marker
            );

        }


        destinationMarkers.splice(
            index,
            1
        );


        destinationPoints.splice(
            index,
            1
        );


        routeFinalized =
            false;


        clearRouteLines();


        const finalizeButton =
            document.getElementById(
                'finalizeRouteBtn'
            );


        if (finalizeButton) {

            finalizeButton.disabled =
                false;

            finalizeButton.textContent =
                'Finalize Route';

        }


        const returnDistance =
            document.getElementById(
                'routeReturnDistance'
            );


        if (returnDistance) {

            returnDistance.textContent =
                '0.00 km';

        }


        const finalDistance =
            document.getElementById(
                'routeFinalDistance'
            );


        if (finalDistance) {

            finalDistance.textContent =
                '0.00 km';

        }


        const distanceInput =
            document.getElementById(
                'distance_km'
            );


        if (distanceInput) {

            distanceInput.value =
                '';

        }


        updateDestinationList();


        generateRoadRoutes();


        const status =
            document.getElementById(
                'routePlannerStatus'
            );


        if (status) {

            status.textContent =
                'Destination removed. Route has been recalculated.';

        }

    }
);


/* =========================================================
   LOCATION SEARCH - PHILIPPINES
========================================================= */

const routeSearchInput =
    document.getElementById(
        'routeSearchInput'
    );


const routeSearchBtn =
    document.getElementById(
        'routeSearchBtn'
    );


const routeSearchResults =
    document.getElementById(
        'routeSearchResults'
    );


async function searchRouteLocation() {

    /*
     * Fixed Fuel cannot search map locations.
     */

    const fixedFuelCheckbox =
        document.getElementById(
            'is_fixed_fuel'
        );


    if (
        fixedFuelCheckbox &&
        fixedFuelCheckbox.checked
    ) {

        return;

    }


    const query =
        routeSearchInput.value.trim();


    if (!query) {

        routeSearchResults.innerHTML = `
            <div class="list-group-item text-muted">
                Enter a location to search.
            </div>
        `;

        return;

    }


    if (routeFinalized) {

        routeSearchResults.innerHTML = `
            <div class="list-group-item text-danger">
                Route is finalized. Clear the route before adding locations.
            </div>
        `;

        return;

    }


    routeSearchBtn.disabled =
        true;

    routeSearchBtn.textContent =
        'Searching...';


    routeSearchResults.innerHTML = `
        <div class="list-group-item text-muted">
            Searching locations...
        </div>
    `;


    try {

        const url =
            'https://nominatim.openstreetmap.org/search?' +
            'format=jsonv2' +
            '&limit=10' +
            '&countrycodes=ph' +
            '&addressdetails=1' +
            '&q=' +
            encodeURIComponent(
                query
            );


        const response =
            await fetch(
                url,
                {
                    headers: {
                        'Accept':
                            'application/json'
                    }
                }
            );


        if (!response.ok) {

            throw new Error(
                'Search service returned an error.'
            );

        }


        const results =
            await response.json();


        routeSearchResults.innerHTML =
            '';


        if (!results.length) {

            routeSearchResults.innerHTML = `
                <div class="list-group-item text-muted">
                    No Philippine locations found.
                </div>
            `;

            return;

        }


        results.forEach(
            function (result) {

                const button =
                    document.createElement(
                        'button'
                    );


                button.type =
                    'button';


                button.className =
                    'list-group-item list-group-item-action';


                button.textContent =
                    result.display_name;


                button.addEventListener(
                    'click',
                    function () {

                        selectRouteSearchResult(
                            parseFloat(
                                result.lat
                            ),
                            parseFloat(
                                result.lon
                            ),
                            result.display_name
                        );

                    }
                );


                routeSearchResults.appendChild(
                    button
                );

            }
        );


    } catch (error) {

        console.error(
            'Location search error:',
            error
        );


        routeSearchResults.innerHTML = `
            <div class="list-group-item text-danger">
                Unable to search locations. Please try again.
            </div>
        `;

    } finally {

        routeSearchBtn.disabled =
            false;

        routeSearchBtn.textContent =
            'Search';

    }

}


/* =========================================================
   SELECT SEARCH RESULT
========================================================= */

function selectRouteSearchResult(
    lat,
    lng,
    locationName
) {

    const fixedFuelCheckbox =
        document.getElementById(
            'is_fixed_fuel'
        );


    if (
        fixedFuelCheckbox &&
        fixedFuelCheckbox.checked
    ) {

        return;

    }


    if (!routeMap) {

        return;

    }


    routeMap.setView(
        [lat, lng],
        16
    );


    routeSearchResults.innerHTML =
        '';


    routeSearchInput.value =
        locationName;


    if (!originLatLng) {

        setRouteOrigin(
            lat,
            lng
        );

    } else {

        addRouteDestination(
            lat,
            lng
        );

    }

}


/* =========================================================
   SEARCH BUTTON
========================================================= */

if (routeSearchBtn) {

    routeSearchBtn.addEventListener(
        'click',
        searchRouteLocation
    );

}


/* =========================================================
   ENTER KEY
========================================================= */

if (routeSearchInput) {

    routeSearchInput.addEventListener(
        'keydown',
        function (e) {

            if (e.key === 'Enter') {

                e.preventDefault();

                searchRouteLocation();

            }

        }
    );

}


/* =========================================================
   RESET ROUTE PLANNER WHEN MODAL CLOSES
========================================================= */

if (addRoutesModal) {

    addRoutesModal.addEventListener(
        'hidden.bs.modal',
        function () {

            clearRoutePlanner();

        }
    );

}


/* =========================================================
   CLEAR ROUTE PLANNER
========================================================= */

function clearRoutePlanner() {

    /*
     * Remove origin marker
     */

    if (
        originMarker &&
        routeMap &&
        routeMap.hasLayer(
            originMarker
        )
    ) {

        routeMap.removeLayer(
            originMarker
        );

    }


    /*
     * Remove destination markers
     */

    destinationMarkers.forEach(
        function (marker) {

            if (
                marker &&
                routeMap &&
                routeMap.hasLayer(
                    marker
                )
            ) {

                routeMap.removeLayer(
                    marker
                );

            }

        }
    );


    /*
     * Remove route lines
     */

    clearRouteLines();


    /*
     * Reset route variables
     */

    originMarker =
        null;

    originLatLng =
        null;

    destinationMarkers =
        [];

    destinationPoints =
        [];

    routeFinalized =
        false;


    updateDestinationList();


    /*
     * Reset distances
     */

    const routeDistance =
        document.getElementById(
            'routeTotalDistance'
        );


    if (routeDistance) {

        routeDistance.textContent =
            '0.00 km';

    }


    const returnDistance =
        document.getElementById(
            'routeReturnDistance'
        );


    if (returnDistance) {

        returnDistance.textContent =
            '0.00 km';

    }


    const finalDistance =
        document.getElementById(
            'routeFinalDistance'
        );


    if (finalDistance) {

        finalDistance.textContent =
            '0.00 km';

    }


    /*
     * Clear distance field
     */

    const distanceInput =
        document.getElementById(
            'distance_km'
        );


    if (distanceInput) {

        distanceInput.value =
            '';

    }


    /*
     * Clear route data
     */

    const routeData =
        document.getElementById(
            'route_data'
        );


    if (routeData) {

        routeData.value =
            '';

    }


    /*
     * Clear search
     */

    const routeSearchInput =
        document.getElementById(
            'routeSearchInput'
        );


    if (routeSearchInput) {

        routeSearchInput.value =
            '';

    }


    /*
     * Clear search results
     */

    const routeSearchResults =
        document.getElementById(
            'routeSearchResults'
        );


    if (routeSearchResults) {

        routeSearchResults.innerHTML =
            '';

    }


    /*
     * Reset Finalize button
     */

    const finalizeButton =
        document.getElementById(
            'finalizeRouteBtn'
        );


    if (finalizeButton) {

        finalizeButton.disabled =
            true;

        finalizeButton.textContent =
            'Finalize Route';

    }


    /*
     * Reset status
     */

    const status =
        document.getElementById(
            'routePlannerStatus'
        );


    if (status) {

        status.textContent =
            'Click the map to set a starting point.';

    }


    /*
     * Return map to default view
     */

    if (routeMap) {

        routeMap.setView(
            [9.7392, 118.7353],
            10
        );

    }

}


/* =========================================================
   INITIALIZE ROUTE PLANNER WHEN MODAL OPENS
========================================================= */

if (addRoutesModal) {

    addRoutesModal.addEventListener(
        'shown.bs.modal',
        function () {

            const finalizeButton =
                document.getElementById(
                    'finalizeRouteBtn'
                );


            if (finalizeButton) {

                finalizeButton.disabled =
                    !originLatLng ||
                    destinationPoints.length === 0 ||
                    routeFinalized;


                finalizeButton.textContent =
                    'Finalize Route';

            }


            /*
             * Re-apply Fixed Fuel state after
             * the modal and Leaflet map are ready.
             */

            const fixedFuelCheckbox =
                document.getElementById(
                    'is_fixed_fuel'
                );


            if (
                fixedFuelCheckbox &&
                fixedFuelCheckbox.checked
            ) {

                toggleFixedFuel();

            }

        }
    );

}


/* =========================================================
   CONFIRM BEFORE CLOSING ROUTE MODAL
========================================================= */

let allowRouteModalClose =
    false;


if (addRoutesModal) {

    addRoutesModal.addEventListener(
        'hide.bs.modal',
        function (event) {

            /*
             * Allow closing after confirmation.
             */

            if (
                allowRouteModalClose
            ) {

                return;

            }


            /*
             * Check current modal mode.
             */

            const formMode =
                document.getElementById(
                    'form_mode'
                )?.value ||
                'add';


            /*
             * Existing saved routes
             * can close normally.
             */

            if (
                formMode === 'edit'
            ) {

                return;

            }


            /*
             * Fixed Fuel has no map route,
             * so there is nothing to discard.
             */

            const fixedFuelCheckbox =
                document.getElementById(
                    'is_fixed_fuel'
                );


            if (
                fixedFuelCheckbox &&
                fixedFuelCheckbox.checked
            ) {

                return;

            }


            /*
             * Only new routes can have
             * an unsaved map route.
             */

            const hasRoute =
                originLatLng ||
                destinationPoints.length > 0;


            if (!hasRoute) {

                return;

            }


            /*
             * Stop modal from closing.
             */

            event.preventDefault();


            /*
             * Show confirmation.
             */

            Swal.fire({

                title:
                    'Discard Route?',

                text:
                    'The current starting point, destinations, and route will be cleared.',

                icon:
                    'warning',

                showCancelButton:
                    true,

                confirmButtonText:
                    'Discard Route',

                cancelButtonText:
                    'Keep Editing',

                confirmButtonColor:
                    '#dc3545',

                reverseButtons:
                    true

            })
            .then(
                function (result) {

                    if (
                        result.isConfirmed
                    ) {

                        allowRouteModalClose =
                            true;


                        const modalInstance =
                            bootstrap.Modal.getInstance(
                                addRoutesModal
                            );


                        if (
                            modalInstance
                        ) {

                            modalInstance.hide();

                        }

                    }

                }
            );

        }
    );


    /* =====================================================
       RESET PLANNER AFTER MODAL FULLY CLOSES
    ===================================================== */

    addRoutesModal.addEventListener(
        'hidden.bs.modal',
        function () {

            clearRoutePlanner();


            allowRouteModalClose =
                false;

        }
    );

}

</script>

</body>
</html>