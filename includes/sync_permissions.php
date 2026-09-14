<?php
session_start();

require_once '../includes/config.php';

header('Content-Type: application/json');

/* =========================================================
   AUTH CHECK
========================================================= */

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['session_token'])
) {
    echo json_encode([
        'success' => false,
        'logged_in' => false
    ]);
    exit;
}

$userId = (int) $_SESSION['user_id'];

$conn = getDBConnection();

/* =========================================================
   GET CURRENT USER
========================================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        department_id,
        area_id,
        status
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();

if (!$user || $user['status'] !== 'active') {

    $conn->close();

    echo json_encode([
        'success' => false,
        'logged_in' => false
    ]);

    exit;
}

$department = (int) $user['department_id'];
$area       = (int) $user['area_id'];

/* =========================================================
   DETERMINE RECOMMENDER
========================================================= */

$isRecommender = false;

$recStmt = $conn->prepare("
    SELECT 1
    FROM department_recommenders dr
    INNER JOIN users u
        ON u.id = dr.user_id
    WHERE dr.user_id = ?
      AND dr.department_id = ?
      AND u.area_id = ?
      AND u.status = 'active'

    UNION

    SELECT 1
    FROM recommender_delegations rd
    INNER JOIN department_recommenders dr
        ON dr.user_id = rd.primary_recommender_id
       AND dr.department_id = rd.department_id
    INNER JOIN users primary_user
        ON primary_user.id = rd.primary_recommender_id
    WHERE rd.secondary_recommender_id = ?
      AND rd.department_id = ?
      AND rd.status = 'active'
      AND CURDATE() BETWEEN rd.start_date AND rd.end_date
      AND primary_user.status = 'active'
      AND primary_user.area_id = ?

    LIMIT 1
");

$recStmt->bind_param(
    "iiiiii",
    $userId,
    $department,
    $area,
    $userId,
    $department,
    $area
);

$recStmt->execute();

$recResult = $recStmt->get_result();

if ($recResult->num_rows > 0) {
    $isRecommender = true;
}

$recStmt->close();

/* =========================================================
   DETERMINE APPROVER
========================================================= */

$isApprover = false;

$appStmt = $conn->prepare("
    SELECT id
    FROM department_approvers
    WHERE user_id = ?
    LIMIT 1
");

$appStmt->bind_param("i", $userId);
$appStmt->execute();

$appResult = $appStmt->get_result();

if ($appResult->num_rows > 0) {
    $isApprover = true;
}

$appStmt->close();

/* =========================================================
   DETERMINE PRIVATE APPROVER
========================================================= */

$isPrivateApprover = false;

$pappStmt = $conn->prepare("
    SELECT id
    FROM approval_global_settings
    WHERE private_vehicle_approver_user_id = ?
    LIMIT 1
");

$pappStmt->bind_param("i", $userId);
$pappStmt->execute();

$pappResult = $pappStmt->get_result();

if ($pappResult->num_rows > 0) {
    $isPrivateApprover = true;
}

$pappStmt->close();

/* =========================================================
   COMPARE WITH CURRENT SESSION
========================================================= */

$oldRecommender = !empty($_SESSION['is_recommender']);
$oldApprover = !empty($_SESSION['is_approver']);
$oldPrivateApprover = !empty($_SESSION['is_private_approver']);

$changed =
    ($oldRecommender !== $isRecommender) ||
    ($oldApprover !== $isApprover) ||
    ($oldPrivateApprover !== $isPrivateApprover);

/* =========================================================
   UPDATE SESSION
========================================================= */

$_SESSION['is_recommender'] = $isRecommender;
$_SESSION['is_approver'] = $isApprover;
$_SESSION['is_private_approver'] = $isPrivateApprover;

$conn->close();

/* =========================================================
   RESPONSE
========================================================= */

echo json_encode([
    'success' => true,
    'logged_in' => true,
    'changed' => $changed,
    'is_recommender' => $isRecommender,
    'is_approver' => $isApprover,
    'is_private_approver' => $isPrivateApprover
]);