<?php
session_start();

require_once 'includes/config.php';

header('Content-Type: application/json');

/* =========================================================
   AUTH GUARD
========================================================= */

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['department_id'])
) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access.'
    ]);
    exit;
}

$conn = getDBConnection();

$userId     = (int)$_SESSION['user_id'];
$department = (int)$_SESSION['department_id'];

/* =========================================================
   VALIDATE INPUT
========================================================= */

$areaId = isset($_POST['area_id'])
    ? (int)$_POST['area_id']
    : 0;

$enabled = isset($_POST['recommender_only'])
    ? (int)$_POST['recommender_only']
    : 0;

if ($areaId <= 0) {

    echo json_encode([
        'success' => false,
        'message' => 'Invalid area.'
    ]);
    exit;
}

$enabled = $enabled ? 1 : 0;

/* =========================================================
   VERIFY AREA BELONGS TO USER'S DEPARTMENT
========================================================= */

$verifyStmt = $conn->prepare("
    SELECT id
    FROM department_areas
    WHERE department_id = ?
      AND area_id = ?
    LIMIT 1
");

$verifyStmt->bind_param(
    "ii",
    $department,
    $areaId
);

$verifyStmt->execute();

$verifyResult = $verifyStmt->get_result();

if ($verifyResult->num_rows === 0) {

    $verifyStmt->close();

    echo json_encode([
        'success' => false,
        'message' => 'This area is not assigned to your department.'
    ]);
    exit;
}

$verifyStmt->close();

/* =========================================================
   SAVE SETTING
========================================================= */

$saveStmt = $conn->prepare("
    INSERT INTO department_approval_settings (
        department_id,
        area_id,
        recommender_only,
        updated_by
    )
    VALUES (?, ?, ?, ?)

    ON DUPLICATE KEY UPDATE
        recommender_only = VALUES(recommender_only),
        updated_by = VALUES(updated_by),
        updated_at = CURRENT_TIMESTAMP
");

$saveStmt->bind_param(
    "iiii",
    $department,
    $areaId,
    $enabled,
    $userId
);

if (!$saveStmt->execute()) {

    $saveStmt->close();

    echo json_encode([
        'success' => false,
        'message' => 'Failed to save the setting.'
    ]);
    exit;
}

$saveStmt->close();

echo json_encode([
    'success' => true,
    'message' => $enabled
        ? 'Recommender Only Approval enabled.'
        : 'Recommender Only Approval disabled.'
]);