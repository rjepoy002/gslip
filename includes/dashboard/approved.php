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

?>