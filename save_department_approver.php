<?php

session_start();

require_once 'includes/config.php';

header('Content-Type: application/json');

$conn = getDBConnection();

/* =========================================================
   AUTH CHECK
========================================================= */

if (!isset($_SESSION['user_id'])) {

    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);

    exit;
}

/* =========================================================
   INPUTS
========================================================= */

$departmentId =
    (int)($_POST['department_id'] ?? 0);

$userId =
    (int)($_POST['user_id'] ?? 0);

if (!$departmentId || !$userId) {

    echo json_encode([
        'success' => false,
        'message' => 'Invalid data.'
    ]);

    exit;
}

/* =========================================================
   CHECK EXISTING
========================================================= */

$checkStmt = $conn->prepare("
    SELECT id
    FROM department_approvers
    WHERE department_id = ?
");

$checkStmt->bind_param(
    'i',
    $departmentId
);

$checkStmt->execute();

$existing =
    $checkStmt->get_result()->fetch_assoc();

$checkStmt->close();

/* =========================================================
   UPDATE OR INSERT
========================================================= */

if ($existing) {

    $stmt = $conn->prepare("
        UPDATE department_approvers
        SET user_id = ?, is_primary = 1
        WHERE department_id = ?
    ");

    $stmt->bind_param(
        'ii',
        $userId,
        $departmentId
    );

} else {

    $stmt = $conn->prepare("
        INSERT INTO department_approvers (
            department_id,
            user_id,
            is_primary
        )
        VALUES (?, ?, 1)
    ");

    $stmt->bind_param(
        'ii',
        $departmentId,
        $userId
    );
}

$success = $stmt->execute();

$stmt->close();

/* =========================================================
   RESPONSE
========================================================= */

echo json_encode([
    'success' => $success
]);