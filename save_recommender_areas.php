<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/settings/assignment_scope.php';
header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'], $_SESSION['session_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized request.']);
    exit;
}
requireRecommenderCsrf();
$conn = getDBConnection();
try {
    $departmentId = (int) ($_POST['department_id'] ?? 0);
    $recommenderId = (int) ($_POST['user_id'] ?? 0);
    $conn->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
    $conn->begin_transaction();
    lockRecommenderDepartment($conn, $departmentId);
    if (!isMultiAreaDepartment($conn, $departmentId)
        || !canManageDepartmentRecommenders($conn, (int) $_SESSION['user_id'], '', $departmentId)) {
        throw new RuntimeException('You are not authorized to manage this department.');
    }
    $stmt = $conn->prepare('SELECT 1 FROM department_recommenders dr JOIN users u ON u.id = dr.user_id WHERE dr.department_id = ? AND dr.user_id = ? AND u.department_id = dr.department_id FOR UPDATE');
    $stmt->bind_param('ii', $departmentId, $recommenderId);
    $stmt->execute();
    if (!$stmt->get_result()->num_rows) { throw new RuntimeException('Invalid department recommender.'); }
    $saved = replaceRecommenderAreas($conn, $departmentId, $recommenderId, $_POST['area_ids'] ?? []);
    $conn->commit();
    echo json_encode(['success' => true] + $saved);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e instanceof RuntimeException ? $e->getMessage() : 'Unable to save assigned areas.']);
}
