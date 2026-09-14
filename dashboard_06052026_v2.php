<?php
session_start();
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

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';
$department = $_SESSION['department_id'] ?? 0;
$area = $_SESSION['area'];

$isAdmin        = ($role === 'admin');
$isRecommender  = !empty($_SESSION['is_recommender']);
$isApprover     = !empty($_SESSION['is_approver']);
$isPrivateApprover     = !empty($_SESSION['is_private_approver']);

// echo "<script>alert('User ID: $userId, Role: $role, Department: $department, Area: $area');</script>"; // Debugging line

// $stmt = $conn->prepare("
//     SELECT name
//     FROM departments
//     WHERE id = ?
// ");

// $stmt->bind_param("i", $department);
// $stmt->execute();
// $result = $stmt->get_result();
// $row = $result->fetch_assoc();

// $department_name = $row['name'] ?? '';

/* =========================================================
   GET CURRENT USER / DEPARTMENT SETTINGS
========================================================= */

$settingsStmt = $conn->prepare("
    SELECT
        a.id,
        a.area_name,
        a.fuel_supplier,
        d.name AS department_name
    FROM users u

    LEFT JOIN departments d
        ON d.id = u.department_id

    LEFT JOIN areas a
        ON a.id = u.area_id

    WHERE u.id = ?
    LIMIT 1
");

$settingsStmt->bind_param("i", $userId);
$settingsStmt->execute();

$settingsResult = $settingsStmt->get_result();

$current = $settingsResult->fetch_assoc();
$department_name = $current['department_name'] ?? '';
$area_name = $current['area_name'] ?? '';
$settingsStmt->close();


/* =========================================
   DASHBOARD COUNTS
========================================= */

    $department_counts = [];
    $area_total = 0;
    $deptLabels = [];
    $deptTotals = [];

    if(!$isRecommender && !$isApprover && !$isAdmin){
        // total gas slips created by this user
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total
            FROM gas_slips
            WHERE user_id = ?
        ");

        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total = $row['total'] ?? 0;

    }elseif($isRecommender){

        if($isAdmin){
            $stmt = $conn->prepare("
                SELECT
                    d.id AS department_id,
                    CASE
                        WHEN d.name = 'ASOD'
                            THEN CONCAT('S-', a.area_name)

                        WHEN d.name = 'ANOD'
                            THEN CONCAT('N-', a.area_name)

                        ELSE d.name
                    END AS department_name,

                    COUNT(gs.id) AS total

                FROM departments d

                LEFT JOIN department_areas da
                    ON da.department_id = d.id

                LEFT JOIN areas a
                    ON a.id = da.area_id

                LEFT JOIN users u
                    ON u.department_id = d.id
                    AND (
                        d.name NOT IN ('ASOD', 'ANOD')
                        OR u.area_id = a.id
                    )

                LEFT JOIN gas_slips gs
                    ON gs.user_id = u.id
                    AND gs.status = 'approved'

                GROUP BY
                    d.id,
                    CASE
                        WHEN d.name IN ('ASOD', 'ANOD')
                            THEN CONCAT(d.name, ' [', a.area_name, ']')
                        ELSE d.name
                    END

                ORDER BY
                    d.id,
                    a.area_name
            ");
            $stmt->execute();
            $result = $stmt->get_result();
    
            while ($row = $result->fetch_assoc()) {
                $department_counts[] = $row;
                $area_total += (int)$row['total'];
    
                $deptLabels[] = $row['display_name'] ?? $row['department_name'];
                $deptTotals[] = (int)$row['total'];
            }
            $stmt->close();
    
        }else{
            // total gas slips created by users in this recommender's department
            $stmt = $conn->prepare("
                SELECT COUNT(*) as total
                FROM gas_slips gs
                LEFT JOIN users u ON u.id = gs.user_id
                WHERE u.department_id = ? 
                AND gs.area_id = ?
            ");

            $stmt->bind_param("ii", $department, $area);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $total = $row['total'] ?? 0;
        }



    }elseif($isApprover || $isAdmin){

        // total gas slips created by users in this approver's area
        if($isPrivateApprover || $isAdmin){
            $stmt = $conn->prepare("
                SELECT
                    d.id AS department_id,
                    CASE
                        WHEN d.name = 'ASOD'
                            THEN CONCAT('S-', a.area_name)

                        WHEN d.name = 'ANOD'
                            THEN CONCAT('N-', a.area_name)

                        ELSE d.name
                    END AS department_name,

                    COUNT(gs.id) AS total

                FROM departments d

                LEFT JOIN department_areas da
                    ON da.department_id = d.id

                LEFT JOIN areas a
                    ON a.id = da.area_id

                LEFT JOIN users u
                    ON u.department_id = d.id
                    AND (
                        d.name NOT IN ('ASOD', 'ANOD')
                        OR u.area_id = a.id
                    )

                LEFT JOIN gas_slips gs
                    ON gs.user_id = u.id
                    AND gs.status = 'approved'

                GROUP BY
                    d.id,
                    CASE
                        WHEN d.name IN ('ASOD', 'ANOD')
                            THEN CONCAT(d.name, ' [', a.area_name, ']')
                        ELSE d.name
                    END

                ORDER BY
                    d.id,
                    a.area_name
            ");

        }else{
            $stmt = $conn->prepare("
                SELECT 
                    d.id AS department_id,
                    d.name AS department_name,
                    COUNT(gs.id) AS total
                FROM users approver
                INNER JOIN departments d 
                    ON d.id = approver.department_id
                INNER JOIN users creator 
                    ON creator.department_id = d.id
                LEFT JOIN gas_slips gs 
                    ON gs.user_id = creator.id
                WHERE approver.id = ?
                AND gs.status = 'approved'
                GROUP BY d.id, d.name
                ORDER BY d.name ASC;
            ");
            $stmt->bind_param("i", $userId); // $area_id is the value of area, i need to match it with gas_slip area_id
        }

        
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $department_counts[] = $row;
            $area_total += (int)$row['total'];

            $deptLabels[] = $row['display_name'] ?? $row['department_name'];
            $deptTotals[] = (int)$row['total'];
        }

        $stmt->close();
    }



/* =========================================
   RECENT GAS SLIPS
========================================= */

$recentQuery = "
    SELECT 
        gs.id, 
        gs.gas_slip_id, 
        gs.date_issued, 
        gs.requested_by,
        gs.purpose,
        gs.status, 
        gs.approved_by,
        gs.approved_at,
        gs.printed_at,
        u.id AS user_id,
        v.plate_no,
        r.origin, 
        r.destination,

        CONCAT(
        MIN(r.origin),
        ' → ',
        GROUP_CONCAT(
            r.destination
            ORDER BY gsr.id
            SEPARATOR ' → '
        )
        ) AS route_path

    FROM gas_slips gs
    LEFT JOIN gas_slip_routes gsr ON gsr.gas_slip_id = gs.id
    LEFT JOIN routes r ON r.id = gsr.route_id
    LEFT JOIN users u ON u.id = gs.user_id
    LEFT JOIN vehicles v ON v.id = gs.vehicle_id

";

if(!$isRecommender && !$isApprover && !$isAdmin){
    $recentQuery .= " WHERE user_id = " . intval($userId);

}elseif($isRecommender || $isApprover || $isAdmin){

    if(!$isPrivateApprover && !$isAdmin){
        $recentQuery .= " WHERE u.department_id = " . intval($department);
    }
    
    // if ($isApprover || $isAdmin) {
    //  $recentQuery .= " AND gs.area_id = '" . $conn->real_escape_string($area) . "'";
    // }
}

$recentQuery .= " AND gs.status != 'cancelled' GROUP BY gs.id ORDER BY gs.date_issued DESC LIMIT 10";

$recentResult = $conn->query($recentQuery);

// ===== Monthly Approved Gas Slips (Current Year) =====
$monthly = array_fill(1, 12, 0);
    if(!$isRecommender && !$isApprover && !$isAdmin){
        $sql = "
            SELECT 
                MONTH(date_issued) AS month_num,
                COUNT(*) AS total
            FROM gas_slips
            WHERE 
                user_id = $userId
                AND status IN ('approved', 'printed')
                AND YEAR(date_issued) = YEAR(CURDATE())
            GROUP BY MONTH(date_issued)
            ORDER BY month_num;
        ";
    }elseif($isRecommender){
        
        if($isAdmin){
            $sql = "
            SELECT 
                MONTH(gs.date_issued) AS month_num,
                COUNT(*) AS total
            FROM gas_slips gs
            LEFT JOIN users u ON u.id = gs.user_id
            WHERE  
                gs.status = 'approved'
                AND YEAR(gs.date_issued) = YEAR(CURDATE())
            GROUP BY MONTH(gs.date_issued)
            ORDER BY month_num;
            ";
        }else{
            $sql = "
                SELECT 
                    MONTH(gs.date_issued) AS month_num,
                    COUNT(*) AS total
                FROM gas_slips gs
                LEFT JOIN users u ON u.id = gs.user_id
                WHERE 
                    u.department_id = $department
                    AND gs.area_id = $area
                    AND gs.status = 'approved'
                    AND YEAR(gs.date_issued) = YEAR(CURDATE())
                GROUP BY MONTH(gs.date_issued)
                ORDER BY month_num;
            ";
        }

        
    }elseif($isApprover || $isAdmin){

        // For approvers, we want to count approved slips for all users in their area, regardless of department
        if($isPrivateApprover || $isAdmin){
            $sql = "
                SELECT 
                    MONTH(gs.date_issued) AS month_num,
                    COUNT(*) AS total
                FROM gas_slips gs
                LEFT JOIN users u ON u.id = gs.user_id
                WHERE  
                    gs.status = 'approved'
                    AND YEAR(gs.date_issued) = YEAR(CURDATE())
                GROUP BY MONTH(gs.date_issued)
                ORDER BY month_num;
            ";
        }else{
            $sql = "
                SELECT 
                    MONTH(gs.date_issued) AS month_num,
                    COUNT(*) AS total
                FROM gas_slips gs
                LEFT JOIN users u ON u.id = gs.user_id
                WHERE 
                    u.department_id = $department
                    AND gs.status = 'approved'
                    AND YEAR(gs.date_issued) = YEAR(CURDATE())
                GROUP BY MONTH(gs.date_issued)
                ORDER BY month_num;
            ";
        }

    } 

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $monthly[(int)$row['month_num']] = (int)$row['total'];
    }
}

$monthlyData = array_values($monthly);

/* =========================================
   FUEL CONSUMPTION BY DEPARTMENT (APPROVER/ADMIN AREA)
========================================= */
if($isPrivateApprover || $isAdmin){
    $sqlFuel = "
        SELECT 
            MONTH(gs.date_issued) AS month_num,

            SUM(
                CASE
                    WHEN fi.name = 'Diesel'
                    THEN fr.quantity
                    ELSE 0
                END
            ) AS diesel_total,

            SUM(
                CASE
                    WHEN fi.name = 'Unleaded'
                    THEN fr.quantity
                    ELSE 0
                END
            ) AS unleaded_total

        FROM fuel_requests fr

        INNER JOIN gas_slips gs
            ON gs.id = fr.gas_slip_id

        INNER JOIN users u
            ON u.id = gs.user_id

        INNER JOIN fuel_items fi
            ON fi.id = fr.fuel_item_id

        WHERE 
            fi.name IN ('Diesel', 'Unleaded')
            AND gs.status IN ('approved', 'printed')
            AND YEAR(gs.date_issued) = YEAR(CURDATE())

        GROUP BY MONTH(gs.date_issued)
        ORDER BY month_num;
    ";
}else{
    
    $sqlFuel = "
        SELECT 
            MONTH(gs.date_issued) AS month_num,

            SUM(
                CASE
                    WHEN fi.name = 'Diesel'
                    THEN fr.quantity
                    ELSE 0
                END
            ) AS diesel_total,

            SUM(
                CASE
                    WHEN fi.name = 'Unleaded'
                    THEN fr.quantity
                    ELSE 0
                END
            ) AS unleaded_total

        FROM fuel_requests fr

        INNER JOIN gas_slips gs
            ON gs.id = fr.gas_slip_id

        INNER JOIN users u
            ON u.id = gs.user_id

        INNER JOIN fuel_items fi
            ON fi.id = fr.fuel_item_id

        WHERE 
            u.department_id = $department
            AND fi.name IN ('Diesel', 'Unleaded')
            AND gs.status IN ('approved', 'printed')
            AND YEAR(gs.date_issued) = YEAR(CURDATE())

        GROUP BY MONTH(gs.date_issued)
        ORDER BY month_num;
    ";
}

$dieselMonthly   = array_fill(0, 12, 0);
$unleadedMonthly = array_fill(0, 12, 0);

$fuelResult = $conn->query($sqlFuel);

if ($fuelResult) {

    while ($row = $fuelResult->fetch_assoc()) {

        $monthIndex = (int)$row['month_num'] - 1;

        $dieselMonthly[$monthIndex]   = (float)$row['diesel_total'];
        $unleadedMonthly[$monthIndex] = (float)$row['unleaded_total'];
    }
}

$dieselData   = $dieselMonthly;
$unleadedData = $unleadedMonthly;

/* =========================================
   TOP 10 VEHICLES BY FUEL CONSUMPTION
========================================= */

if ($isPrivateApprover || $isAdmin) {

    $sqlTopVehicles = "
        SELECT
            v.plate_no,
            CONCAT(v.brand, ' ', v.model) AS vehicle_name,
            SUM(fr.quantity) AS total_fuel

        FROM fuel_requests fr

        INNER JOIN gas_slips gs
            ON gs.id = fr.gas_slip_id

        INNER JOIN vehicles v
            ON v.id = gs.vehicle_id

        INNER JOIN fuel_items fi
            ON fi.id = fr.fuel_item_id

        WHERE
            gs.status IN ('approved','printed')
            AND fi.name IN ('Diesel','Unleaded')

        GROUP BY v.id

        ORDER BY total_fuel DESC

        LIMIT 10
    ";

} else {
    // echo '<script>alert("' . $department . '")</script>';
    $sqlTopVehicles = "
        SELECT
            v.plate_no,
            CONCAT(v.brand, ' ', v.model) AS vehicle_name,
            SUM(fr.quantity) AS total_fuel

        FROM fuel_requests fr

        INNER JOIN gas_slips gs
            ON gs.id = fr.gas_slip_id

        INNER JOIN users u
            ON u.id = gs.user_id

        INNER JOIN vehicles v
            ON v.id = gs.vehicle_id

        INNER JOIN fuel_items fi
            ON fi.id = fr.fuel_item_id

        WHERE
            u.department_id = $department
            AND gs.status IN ('approved','printed')
            AND fi.name IN ('Diesel','Unleaded')

        GROUP BY v.id

        ORDER BY total_fuel DESC

        LIMIT 10
    ";
}
//  echo '<script>alert("' . $department . '")</script>';
$topVehicleLabels = [];
$topVehicleData   = [];

$topVehicleResult = $conn->query($sqlTopVehicles);

if ($topVehicleResult) {
    while ($row = $topVehicleResult->fetch_assoc()) {

        $topVehicleLabels[] =
            $row['plate_no'];

        $topVehicleData[] =
            (float)$row['total_fuel'];
    }
}

/* =========================================
   TOP 10 MOST USED ROUTES
========================================= */

if ($isPrivateApprover || $isAdmin) {

    $sqlTopRoutes = "
        SELECT
            r.origin,
            r.destination,
            COUNT(*) AS total_slips

        FROM gas_slip_routes gsr

        INNER JOIN gas_slips gs
            ON gs.id = gsr.gas_slip_id

        INNER JOIN routes r
            ON r.id = gsr.route_id

        WHERE
            gs.status IN ('approved','printed')

        GROUP BY r.id

        ORDER BY total_slips DESC

        LIMIT 10
    ";

} else {

    $sqlTopRoutes = "
        SELECT
            r.origin,
            r.destination,
            COUNT(*) AS total_slips

        FROM gas_slip_routes gsr

        INNER JOIN gas_slips gs
            ON gs.id = gsr.gas_slip_id

        INNER JOIN users u
            ON u.id = gs.user_id

        INNER JOIN routes r
            ON r.id = gsr.route_id

        WHERE
            u.department_id = $department
            AND gs.status IN ('approved','printed')

        GROUP BY r.id

        ORDER BY total_slips DESC

        LIMIT 10
    ";
}

$topRouteLabels = [];
$topRouteData   = [];

$topRouteResult = $conn->query($sqlTopRoutes);

// if ($topRouteResult) {
//     while ($row = $topRouteResult->fetch_assoc()) {

//         $topRouteLabels[] =
//             $row['origin'] . ' → ' . $row['destination'];

//         $topRouteData[] =
//             (int)$row['total_slips'];
//     }
// }

// echo '<pre>';
// print_r($topVehicleLabels);
// print_r($topVehicleData);
// echo '</pre>';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>

    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/icons/bootstrap-icons.css">
    <script src="assets/js/sweetalert2.all.min.js"></script>
    <script src="assets/js/chart.js/chart.min.js"></script>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<div class="app-content">

    <main class="main-content">
        <div class="panel-container">
            <!-- Page Header -->
            <div class="page-header mb-4">
              <h1 class="mb-1">Dashboard</h1>
              <p class="page-subtitle mb-1">
                  Summary of gas slip activities, request statuses, and recent transactions.
              </p>
            </div>

            <!-- =========================
                STAT CARDS
            ========================== -->

            <div class="row g-4 mb-2">

                <!-- TOTAL CARD -->
                <div class="col-md-3">
                    <a href="create_gas_slip.php" class="text-decoration-none">
                        <div class="card text-bg-primary text-white shadow" 
                            style="border: 4px solid #ffffff;">
                            <div class="card-body p-3">
                                <div class="fw-semibold">
                                    <?php if ($isRecommender): ?>

                                        <?php
                                        if (in_array($department_name, ['ASOD', 'ANOD'])) {
                                            echo $area_name . ' ';
                                        } else {
                                            echo $department_name;
                                        }
                                        ?>

                                    <?php endif; ?>
                                    Total Gas Slips
                                    <?php if (!$isRecommender && !$isApprover && !$isAdmin): ?>
                                        Created
                                    <?php endif; ?>
                                </div>
                                <!-- <hr class="my-2"> -->
                                <div class="text-center">
                                    <span class="fw-bold" style="font-size: 4rem;">
                                        <?php if ($isApprover || $isAdmin) {
                                            echo $area_total;
                                        } else {
                                            echo $total ?? 0;
                                        } ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- STATUS SECTION -->
                <div class="col-md-9 mb-3">
                    
                    <div class="fw-semibold mb-2 p-1">Active Gas Slip Status Overview</div>
                    <!-- <hr class="border-primary border-2 opacity-50 my-2"> -->
                    <hr class="hr-modern my-2">

                    <div class="row g-3">

                        <?php if (!$isApprover): ?>
                            <div class="col-md-4">
                                <a href="draft_gas_slips.php" class="text-decoration-none">
                                    <div class="card text-bg-secondary shadow" 
                                        style="border: 4px solid #ffffff;">
                                        <div class="card-body p-3">
                                            <div class="small fw-semibold">Draft</div>
                                            <!-- <hr class="my-2"> -->
                                            <div class="fs-2 fw-bold text-center">
                                                <?= $counts['draft'] ?? 0 ?>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endif; ?>

                        <div class="col-md-4">
                            <a href="pending_gas_slips.php" class="text-decoration-none">
                                <div class="card text-bg-info text-white shadow" 
                                    style="border: 4px solid #ffffff;">
                                    <div class="card-body p-3">
                                        <div class="small fw-semibold">Pending</div>
                                        <!-- <hr class="my-2"> -->
                                        <!-- <div class="fs-2 fw-bold text-center">
                                            <!?= $counts['pending'] ?? 0 ?>
                                        </div> -->
                                        <div class="fs-2 fw-bold text-center">
                                            <?=
                                                ($counts['pending'] ?? 0) +
                                                ((!$isAdmin && !$isApprover && !$isRecommender)
                                                    ? ($counts['recommended'] ?? 0)
                                                    : 0)
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>

                        <div class="col-md-4">
                            <a href="approved_slips.php" class="text-decoration-none">
                                <div class="card text-bg-success shadow" 
                                    style="border: 4px solid #ffffff;">
                                    <div class="card-body p-3">
                                        <div class="small fw-semibold">Approved</div>
                                        <!-- <hr class="my-2"> -->
                                        <div class="fs-2 fw-bold text-center">
                                            <?= $counts['approved'] ?? 0 ?>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>

                    </div>
                </div>

            </div>


            <!-- =========================
                MONTHLY + DEPARTMENT CHARTS (SEPARATE CARDS)
            ========================== -->

            <div class="row g-4 mb-4">

                <!-- LINE CHART CARD -->
                <div class="<?php echo ($isApprover || $isAdmin) ? 'col-md-6' : 'col-12'; ?>">
                    <div class="card p-0 h-100">

                        <div class="fw-semibold mb-0 px-3" style="padding-top: 10px; color: #64748b;">
                            <?php //if (!$isApprover): ?>

                                <?php
                                if (in_array($department_name, ['ASOD', 'ANOD']) && !$isApprover) {
                                    echo $area_name . ' ';
                                } elseif(!$isPrivateApprover && !$isAdmin) {
                                    echo $department_name;
                                }
                                ?>

                            <?php //endif; ?>
                            Monthly Approved Gas Slips (<?= date('Y'); ?>)
                        </div>

                        <hr class="hr-modern my-2">

                        <div class="px-3 pb-3">
                            <div style="position: relative; height:350px;">
                                <canvas id="approvedLineChart"></canvas>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- FUEL CONSUMPTION CHART CARD (APPROVER ONLY) -->
                <?php if ($isApprover || $isAdmin): ?>
                <div class="col-md-6">
                    <div class="card p-0 h-100">

                        <div class="fw-semibold mb-0 px-3" style="padding-top: 10px; color: #64748b;">
                            Monthly Fuel Consumption (<?= date('Y'); ?>)
                        </div>

                        <hr class="hr-modern my-2">

                        <div class="px-3 pb-3">
                            <div style="position: relative; height:350px;">
                                <canvas id="fuelBarChart"></canvas>
                            </div>
                        </div>

                    </div>
                </div>
                <?php endif; ?>

            </div>

            <div class="row g-4 mb-4">
                <!-- bar CHART CARD (APPROVER ONLY) -->
                <?php if ($isPrivateApprover || $isAdmin): ?>
                <div class="col-12">
                    <div class="card p-0 h-100">
                        <div class="fw-semibold mb-0 px-3" style="padding-top: 10px; color: #64748b;">
                            Department Gas Slip Distribution (<?= date('Y'); ?>)
                        </div>

                        <hr class="hr-modern my-2">

                        <div class="px-3 pb-3">
                            <div style="position: relative; height:350px;">
                                <canvas id="departmentPieChart"></canvas>
                            </div>
                        </div>

                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($isAdmin || $isApprover || $isRecommender): ?>
            <div class="row">

            <!-- =========================
                TOP 10 VEHICLES
            ========================== -->
            
                <?php
                $vehicleCount = count($topVehicleLabels);
                $chartHeight = max(150, $vehicleCount * 50);
                ?>

                <div class="col-lg-6 mb-4">
                    <div class="card p-0 h-100">

                        <div class="fw-semibold mb-0 px-3"
                            style="padding-top:10px; color:#64748b;">
                            Top 10 Vehicles by Fuel Consumption
                        </div>

                        <hr class="hr-modern my-2">

                        <div style="height:<?= $chartHeight ?>px;">
                            <canvas id="topVehiclesChart"></canvas>
                        </div>

                    </div>
                </div>
            


            <!-- =========================
                TOP 10 MOST USED ROUTES
            ========================= -->
            <div class="col-lg-6 mb-4">

                <div class="card p-0 h-100">

                    <div class="fw-semibold mb-0 px-3"
                        style="padding-top:10px; color:#64748b;">
                        Top 10 Most Used Routes
                    </div>

                    <hr class="hr-modern my-2">

                    <div class="table-responsive">
                        <table class="styled-table excel-table mt-0">

                            <thead>
                                <tr>
                                    <th width="60">#</th>
                                    <th>Origin</th>
                                    <th>Destination</th>
                                    <th class="text-end">Gas Slips</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php
                                $rank = 1;

                                if ($topRouteResult && $topRouteResult->num_rows > 0):

                                    while ($row = $topRouteResult->fetch_assoc()):
                                ?>
                                        <tr>
                                            <td><?= $rank++ ?></td>
                                            <td><?= htmlspecialchars($row['origin']) ?></td>
                                            <td><?= htmlspecialchars($row['destination']) ?></td>
                                            <td class="text-end fw-semibold">
                                                <?= number_format($row['total_slips']) ?>
                                            </td>
                                        </tr>
                                <?php
                                    endwhile;
                                else:
                                ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">
                                            No route data available.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>

                        </table>
                    </div>

                </div>

            </div>

            </div>
            <?php endif; ?>

            <!-- =========================
                RECENT GAS SLIPS
            ========================== -->

            <div class="card p-0 mb-4">

                <div class="fw-semibold mb-0 px-3" style="padding-top: 10px; color: #64748b;">Recent Gas Slips</div>
                <hr class="hr-modern my-2">

                <table class="styled-table excel-table mt-0">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Gas Slip No.</th>
                            <th>Date Issued</th>
                            <th>Requested By</th>
                            <th>Purpose</th>
                            <th>Route</th>
                        </tr>
                    </thead>
                    <tbody>

                    <?php if ($recentResult && $recentResult->num_rows > 0): ?>
                        <?php $no = 1; ?>
                        <?php while ($row = $recentResult->fetch_assoc()): ?>
                            <tr>
                                <td><?= $no++; ?> </td>
                                <td>
                                    <strong><?= htmlspecialchars($row['gas_slip_id']); ?></strong>
                                    <?php
                                    if (!empty($row['printed_at'])) {
                                    echo '<span class="badge bg-primary" title="Approved"><i class="bi bi-check-lg"></i></span>';
                                    }else{
                                    switch ($row['status']) {
                                        case 'recommended':
                                        echo '<span class="badge bg-warning ms-1" title="Recommended">R</span>';
                                        break;
                                        case 'approved':
                                        echo '<span class="badge bg-success ms-1" title="Approved">A</span>';
                                        break;
                                        case 'pending':
                                        echo '<span class="badge bg-info ms-1" title="Pending">P</span>';
                                        break;
                                        case 'draft':
                                        echo '<span class="badge bg-secondary ms-1" title="Draft">D</span>';
                                        break;
                                        default:
                                        echo '<span class="badge bg-light ms-1 text-black" title="Unknown">?</span>';
                                    }
                                    }
                                    ?>
                                </td>

                                <td>
                                    <?= date('M d, Y · h:i A', strtotime($row['date_issued'])); ?>
                                </td>
                                
                                <td>
                                    <?= htmlspecialchars($row['requested_by']); ?>
                                </td>

                                <td><?= htmlspecialchars($row['purpose']) ?></td>

                                <td>
                                    <?= htmlspecialchars($row['route_path']) ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">
                                No recent records found
                            </td>
                        </tr>
                    <?php endif; ?>

                    </tbody>
                </table>

            </div>




        </div>
    </main>

</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/sweetalert2.all.min.js"></script>

<script src="assets/js/app-ui.js"></script>
<script src="assets/js/gas-slip.js"></script>

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
<?php unset($_SESSION['swal_success']); endif; ?>

<script>
const ctx = document.getElementById('approvedLineChart').getContext('2d');
    
new Chart(ctx, {
    type: 'line',
    data: {
        labels: [
            'Jan','Feb','Mar','Apr','May','Jun',
            'Jul','Aug','Sep','Oct','Nov','Dec'
        ],
        datasets: [{
            label: 'Approved Gas Slips',
            data: <?php echo json_encode($monthlyData); ?>,
            tension: 0.3,
            fill: true,

            // ✅ Green styling
            borderColor: '#198754',                 // Bootstrap green
            backgroundColor: 'rgba(25,135,84,0.2)', // Light green fill
            pointBackgroundColor: '#198754',
            pointBorderColor: '#198754',
            pointRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                suggestedMin: 0,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});

document.addEventListener('DOMContentLoaded', function () {

    const fuelCanvas = document.getElementById('fuelBarChart');

    if (!fuelCanvas) return;

    const fuelCtx = fuelCanvas.getContext('2d');

    new Chart(fuelCtx, {

        type: 'bar',

        data: {
            labels: [
                'Jan','Feb','Mar','Apr','May','Jun',
                'Jul','Aug','Sep','Oct','Nov','Dec'
            ],

            datasets: [

                {
                    label: 'Diesel',
                    data: <?= json_encode($dieselData) ?>,

                    backgroundColor: 'rgba(25,135,84,0.7)',
                    borderColor: '#198754',
                    borderWidth: 1
                },

                {
                    label: 'Unleaded',
                    data: <?= json_encode($unleadedData) ?>,

                    backgroundColor: 'rgba(13,110,253,0.7)',
                    borderColor: '#0d6efd',
                    borderWidth: 1
                }

            ]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            plugins: {
                legend: {
                    display: true
                }
            },

            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }

    });

});



const topVehicleLabels = <?= json_encode($topVehicleLabels) ?>;
const topVehicleData   = <?= json_encode($topVehicleData) ?>;

new Chart(document.getElementById('topVehiclesChart'), {
    type: 'horizontalBar',
    data: {
        labels: topVehicleLabels,
        datasets: [{
            label: 'Fuel Used (Liters)',
            data: topVehicleData,
            backgroundColor: [
                'rgba(13,110,253,1.00)',
                'rgba(13,110,253,0.92)',
                'rgba(13,110,253,0.84)',
                'rgba(13,110,253,0.76)',
                'rgba(13,110,253,0.68)',
                'rgba(13,110,253,0.60)',
                'rgba(13,110,253,0.52)',
                'rgba(13,110,253,0.44)',
                'rgba(13,110,253,0.36)',
                'rgba(13,110,253,0.28)'
            ],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        legend: {
            display: false
        },
        tooltips: {
            callbacks: {
                label: function(tooltipItem) {
                    return tooltipItem.xLabel.toLocaleString() + ' L';
                }
            }
        },
        scales: {
            xAxes: [{
                ticks: {
                    beginAtZero: true,
                    fontColor: '#6c757d'
                },
                gridLines: {
                    color: '#f1f3f5'
                }
            }],
            yAxes: [{
                ticks: {
                    fontColor: '#495057'
                },
                gridLines: {
                    display: false
                }
            }]
        }
    }
});

<?php if ($isApprover || $isAdmin): ?>

    const deptCtx = document.getElementById('departmentPieChart').getContext('2d');

    new Chart(deptCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($deptLabels); ?>,
            datasets: [{
                label: 'Approved Gas Slips',
                data: <?php echo json_encode($deptTotals); ?>,
                backgroundColor: [
                    '#198754', '#198754', '#198754', '#198754', '#198754', //ASOD
                    '#0d6efd', '#0d6efd', '#0d6efd', '#0d6efd', '#0d6efd', '#0d6efd', '#0d6efd', '#0d6efd', //ANOD
                    '#ffc107',
                    '#dc3545',
                    '#6f42c1',
                    '#fd7e14',
                    '#20c997',
                    '#6610f2',
                    '#0dcaf0',
                    '#adb5bd',
                    '#343a40',
                    '#6c757d'
                ],
                borderWidth: 1,
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,

            legend: {
                display: false
            },

            tooltips: {
                callbacks: {
                    label: function(tooltipItem, data) {
                        return data.datasets[0].data[tooltipItem.index] + ' approved slip(s)';
                    }
                }
            },

            scales: {
                xAxes: [{
                    ticks: {
                        beginAtZero: true,
                        precision: 0
                    },
                    scaleLabel: {
                        display: true
                    }
                }],
                yAxes: [{
                    ticks: {
                        autoSkip: false
                    }
                }]
            },

            layout: {
                padding: 20
            }
        }
    });

<?php endif; ?>

</script>
</body>
</html>