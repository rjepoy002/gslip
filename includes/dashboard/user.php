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



?>