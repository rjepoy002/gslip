<?php
session_start();
require_once 'includes/config.php';

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
    !isset($_SESSION['department_id'])
) {

    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access.'
    ]);

    exit;
}

$departmentId = (int) $_SESSION['department_id'];

/* =========================================================
   CLEAR SECONDARY APPROVER
========================================================= */

try {

    $stmt = $conn->prepare("
        DELETE FROM department_approvers
        WHERE department_id = ?
        AND is_primary = 0
    ");

    $stmt->bind_param(
        "i",
        $departmentId
    );

    $stmt->execute();

    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Secondary approver cleared.'
    ]);

} catch (Exception $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>