<?php
require_once __DIR__ . "/../gas_slip_access.php";

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

$dashboardViewScope = gasSlipPermissionSql("view", $userId);
$isAdmin        = ($role === 'admin');
$isRecommender  = !empty($_SESSION['is_recommender']);
$isApprover     = !empty($_SESSION['is_approver']);
$isPrivateApprover     = !empty($_SESSION['is_private_approver']);

// Printed slips remain part of approved graph totals for Admins and approvers.
$graphStatusScope = ($isAdmin || $isApprover)
    ? "gs.status IN ('approved', 'printed')"
    : "gs.status = 'approved'";
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

                    COUNT(DISTINCT gs.id) AS total

                FROM departments d

                LEFT JOIN department_areas da
                    ON da.department_id = d.id

                LEFT JOIN areas a
                    ON a.id = da.area_id

                LEFT JOIN users u
                    ON u.department_id = d.id

                LEFT JOIN gas_slips gs
                    ON gs.user_id = u.id
                    AND (
                        d.name NOT IN ('ASOD', 'ANOD')
                        OR gs.area_id = a.id
                    )
                    AND $graphStatusScope AND $dashboardViewScope

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
                WHERE $dashboardViewScope
            ");

            /* Shared policy uses the database actor, not session area/role flags. */
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

                    COUNT(DISTINCT gs.id) AS total

                FROM departments d

                LEFT JOIN department_areas da
                    ON da.department_id = d.id

                LEFT JOIN areas a
                    ON a.id = da.area_id

                LEFT JOIN users u
                    ON u.department_id = d.id

                LEFT JOIN gas_slips gs
                    ON gs.user_id = u.id
                    AND (
                        d.name NOT IN ('ASOD', 'ANOD')
                        OR gs.area_id = a.id
                    )
                    AND $graphStatusScope AND $dashboardViewScope

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
                    COUNT(DISTINCT gs.id) AS total
                FROM users approver
                INNER JOIN departments d 
                    ON d.id = approver.department_id
                INNER JOIN users creator 
                    ON creator.department_id = d.id
                LEFT JOIN gas_slips gs 
                    ON gs.user_id = creator.id
                WHERE approver.id = ?
                AND $graphStatusScope AND $dashboardViewScope
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




// Count approved and printed gas slips once for the Admin total.
if ($isAdmin) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM gas_slips gs
        WHERE gs.status IN ('approved', 'printed') AND $dashboardViewScope");
    $stmt->execute();
    $area_total = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
} elseif ($isApprover) {
    // Count approved and printed slips once, retaining the approver's existing scope.
    $approverDepartmentScope = $isPrivateApprover ? '1=1' : "EXISTS (
        SELECT 1 FROM users creator
        INNER JOIN users approver ON approver.department_id = creator.department_id
        WHERE creator.id = gs.user_id AND approver.id = " . (int)$userId . "
    )";
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM gas_slips gs
        WHERE gs.status IN ('approved', 'printed')
          AND $dashboardViewScope AND $approverDepartmentScope");
    $stmt->execute();
    $area_total = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
}

?>
