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

requireRecommenderCsrf();
$conn->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
$conn->begin_transaction();
register_shutdown_function(function () use ($conn) { try { $conn->rollback(); } catch (Throwable $e) {} });
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

lockRecommenderDepartment($conn, $departmentId);

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

/* Department lock and approver authorization above cover registration and seed. */
try {
    $created = registerDepartmentRecommender($conn, $userId, $departmentId);
    $conn->commit();
    echo json_encode([
        'success' => true,
        'message' => $created ? 'Recommender added successfully.' : 'Recommender is already registered. Existing area assignments were preserved.'
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e instanceof RuntimeException ? $e->getMessage() : 'Unable to add the recommender. Please try again.'
    ]);
}
