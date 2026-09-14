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

?>