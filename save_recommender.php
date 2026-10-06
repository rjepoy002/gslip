<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/settings/assignment_scope.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: application/json');

$conn = getDBConnection();

/* =========================================================
   AUTH GUARD
========================================================= */

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['session_token']) ||
    !isset($_SESSION['role']) ||
    !isset($_SESSION['department_id']) ||
    !isset($_SESSION['area'])
) {

    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access.'
    ]);

    exit;
}

$sessionDepartmentId = (int) $_SESSION['department_id'];
$managerId = (int) $_SESSION['user_id'];
$managerRole = (string) $_SESSION['role'];

$departmentId = $sessionDepartmentId;

/* =========================================================
   VALIDATE REQUEST
========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);

    exit;
}

/* =========================================================
   FORM VALUES
========================================================= */

$userId = !empty($_POST['user_id'])
    ? (int) $_POST['user_id']
    : 0;

/* =========================================================
   VALIDATE INPUT
========================================================= */

if ($userId <= 0) {

    echo json_encode([
        'success' => false,
        'message' => 'Invalid user.'
    ]);

    exit;
}

if ($managerRole === 'admin') {
    $departmentId = !empty($_POST['department_id'])
        ? (int) $_POST['department_id']
        : $sessionDepartmentId;
} elseif (
    !empty($_POST['department_id']) &&
    (int) $_POST['department_id'] !== $sessionDepartmentId
) {
    echo json_encode([
        'success' => false,
        'message' => 'You may only manage recommenders for your authorized department.'
    ]);

    exit;
}

if (!canManageDepartmentRecommenders(
    $conn,
    $managerId,
    $managerRole,
    $departmentId
)) {

    echo json_encode([
        'success' => false,
        'message' => 'You are not authorized to manage recommenders for this department.'
    ]);

    exit;
}

/* =========================================================
   VALIDATE THE SAME DEPARTMENT SCOPE AS THE MODAL
========================================================= */

if (!isEligiblePrimaryRecommenderCandidate($conn, $userId, $departmentId)) {

    echo json_encode([
        'success' => false,
        'message' => 'Selected user is not eligible to be a recommender for this department.'
    ]);

    exit;
}

/* =========================================================
   INSERT RECOMMENDER
========================================================= */

try {

    $stmt = $conn->prepare("
        INSERT INTO department_recommenders (
            department_id,
            user_id
        )
        VALUES (?, ?)
    ");

    $stmt->bind_param(
        "ii",
        $departmentId,
        $userId
    );

    $stmt->execute();

    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Recommender added successfully.'
    ]);

} catch (Exception $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
