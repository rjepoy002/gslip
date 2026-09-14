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

$where = [];

/* =========================================
   ROLE FILTERS
========================================= */

if (!$isRecommender && !$isApprover && !$isAdmin) {

    // Regular user: only own gas slips
    $where[] = "gs.user_id = " . intval($userId);

} elseif ($isRecommender || $isApprover || $isAdmin) {

    // Department-based view
    // if (!$isPrivateApprover && !$isAdmin) {
    //     $where[] = "u.department_id = " . intval($department);
    // }

    if (!$isPrivateApprover && !$isAdmin) {

        $areaRestrictedDepartments = [1, 2];
    
        if ($isRecommender && in_array($department, $areaRestrictedDepartments)) {
            $where[] = "gs.area_id = " . intval($area);
        } else {
            $where[] = "u.department_id = " . intval($department);
        }
    }

    // Uncomment if approvers/admins should be limited by area
    /*
    if ($isApprover || $isAdmin) {
        $where[] = "gs.area_id = '" . $conn->real_escape_string($area) . "'";
    }
    */
}

/* =========================================
   COMMON FILTERS
========================================= */

$where[] = "gs.status = 'approved'";

/* =========================================
   BUILD WHERE CLAUSE
========================================= */

if (!empty($where)) {
    $recentQuery .= " WHERE " . implode(" AND ", $where);
}

$recentQuery .= "
    GROUP BY gs.id
    ORDER BY gs.date_issued DESC
    LIMIT 10
";

$recentResult = $conn->query($recentQuery);
// if(!$isRecommender && !$isApprover && !$isAdmin){
//     $recentQuery .= " WHERE user_id = " . intval($userId);

// }elseif($isRecommender || $isApprover || $isAdmin){

//     if(!$isPrivateApprover && !$isAdmin){
//         $recentQuery .= " WHERE u.department_id = " . intval($department);
//     }
    
//     // if ($isApprover || $isAdmin) {
//     //  $recentQuery .= " AND gs.area_id = '" . $conn->real_escape_string($area) . "'";
//     // }
// }

// $recentQuery .= " AND gs.status != 'cancelled' GROUP BY gs.id ORDER BY gs.date_issued DESC LIMIT 10";
// // $recentQuery .= " AND gs.status = 'approved' GROUP BY gs.id ORDER BY gs.date_issued DESC LIMIT 10";

// $recentResult = $conn->query($recentQuery);


?>