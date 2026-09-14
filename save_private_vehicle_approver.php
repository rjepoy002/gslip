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
        'message' => 'Unauthorized.'
    ]);

    exit;
}

/* =========================================================
   VALIDATE INPUT
========================================================= */

$userId = isset($_POST['user_id'])
    ? (int)$_POST['user_id']
    : 0;

if ($userId <= 0) {

    echo json_encode([
        'success' => false,
        'message' => 'Invalid user.'
    ]);

    exit;
}

/* =========================================================
   CHECK IF SETTINGS ROW EXISTS
========================================================= */

$check = $conn->query("
    SELECT id
    FROM approval_global_settings
    LIMIT 1
");

/* ---------------------------------------------
CREATE DEFAULT ROW IF EMPTY
--------------------------------------------- */

if ($check->num_rows === 0) {

    $conn->query("
        INSERT INTO approval_global_settings (
            private_vehicle_approver_user_id
        )
        VALUES (NULL)
    ");
}

/* =========================================================
   UPDATE PRIVATE VEHICLE APPROVER
========================================================= */

$stmt = $conn->prepare("
    UPDATE approval_global_settings

    SET private_vehicle_approver_user_id = ?

    LIMIT 1
");

if (!$stmt) {

    echo json_encode([
        'success' => false,
        'message' => 'Prepare failed: ' . $conn->error
    ]);

    exit;
}

$stmt->bind_param(
    "i",
    $userId
);

$success = $stmt->execute();

if (!$success) {

    echo json_encode([
        'success' => false,
        'message' => 'Execute failed: ' . $stmt->error
    ]);

    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Private vehicle approver updated.'
]);