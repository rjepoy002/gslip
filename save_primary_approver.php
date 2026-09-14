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

/* =========================================================
   CHECK IF RECOMMENDER
========================================================= */

$stmt = $conn->prepare("
    SELECT id
    FROM department_recommenders
    WHERE department_id = ?
    AND user_id = ?
");

$stmt->bind_param(
    "ii",
    $departmentId,
    $userId
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0) {

    echo json_encode([
        'success' => false,
        'message' => 'Recommender cannot be approver.'
    ]);

    exit;
}

$stmt->close();

/* =========================================================
   SAVE PRIMARY APPROVER
========================================================= */

try {

    $conn->begin_transaction();

    /* REMOVE OLD PRIMARY */

    $stmt = $conn->prepare("
        DELETE FROM department_approvers
        WHERE department_id = ?
        AND is_primary = 1
    ");

    $stmt->bind_param(
        "i",
        $departmentId
    );

    $stmt->execute();

    $stmt->close();

    /* INSERT NEW PRIMARY */

    $isPrimary = 1;

    $stmt = $conn->prepare("
        INSERT INTO department_approvers (
            department_id,
            user_id,
            is_primary
        )
        VALUES (?, ?, ?)
    ");

    $stmt->bind_param(
        "iii",
        $departmentId,
        $userId,
        $isPrimary
    );

    $stmt->execute();

    $stmt->close();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Primary approver updated.'
    ]);

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>