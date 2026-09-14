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

    $areaRestrictedDepartments = [1, 2];

    if ($isRecommender && in_array($department, $areaRestrictedDepartments)) {
        $areaFilter = "AND gs.area_id = $area";
    } else {
        $areaFilter = "AND u.department_id = $department";
    }

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
            -- u.department_id = $department
            gs.status IN ('approved','printed')
            $areaFilter

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


?>