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
   TOP FUEL ITEMS
========================================= */

if ($isPrivateApprover || $isAdmin) {

    $sqlTopFuelItems = "
        SELECT
            fi.id,
            fi.name,
            COUNT(fr.id) AS total_requests,
            SUM(fr.quantity) AS total_quantity

        FROM fuel_requests fr

        INNER JOIN gas_slips gs
            ON gs.id = fr.gas_slip_id

        INNER JOIN fuel_items fi
            ON fi.id = fr.fuel_item_id

        WHERE
            gs.status IN ('approved','printed')
            AND YEAR(gs.date_issued) = YEAR(CURDATE())

        GROUP BY fi.id, fi.name

        ORDER BY total_quantity DESC

        LIMIT 10
    ";

} else {

    $areaRestrictedDepartments = [1, 2];

    if ($isRecommender && in_array($department, $areaRestrictedDepartments)) {
        $areaFilter = "AND gs.area_id = $area";
    } else {
        $areaFilter = "AND u.department_id = $department";
    }

    $sqlTopFuelItems = "
        SELECT
            fi.id,
            fi.name,
            COUNT(fr.id) AS total_requests,
            SUM(fr.quantity) AS total_quantity

        FROM fuel_requests fr

        INNER JOIN gas_slips gs
            ON gs.id = fr.gas_slip_id

        INNER JOIN users u
            ON u.id = gs.user_id

        INNER JOIN fuel_items fi
            ON fi.id = fr.fuel_item_id

        WHERE
            -- u.department_id = $department
            gs.status IN ('approved','printed')
            AND YEAR(gs.date_issued) = YEAR(CURDATE())
            $areaFilter

        GROUP BY fi.id, fi.name

        ORDER BY total_quantity DESC

        LIMIT 10
    ";
}

$topFuelItemResult = $conn->query($sqlTopFuelItems);