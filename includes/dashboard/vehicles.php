<?php 

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
    $areaRestrictedDepartments = [1, 2];

    if ($isRecommender && in_array($department, $areaRestrictedDepartments)) {
        $areaFilter = "AND gs.area_id = $area";
    } else {
        $areaFilter = "AND u.department_id = $department";
    }

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
            -- u.department_id = $department
            gs.status IN ('approved','printed')
            AND fi.name IN ('Diesel','Unleaded')
            $areaFilter

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


?>