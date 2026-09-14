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