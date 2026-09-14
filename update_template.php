<?php
session_start();

require_once 'includes/config.php';

$conn = getDBConnection();

header('Content-Type: application/json');

// ==========================
// AUTH CHECK
// ==========================
if (!isset($_SESSION['user_id'])) {

    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);

    exit;
}

$userId = $_SESSION['user_id'];

// ==========================
// INPUTS
// ==========================
$templateId   = isset($_POST['id'])
    ? (int) $_POST['id']
    : 0;

$templateName = trim($_POST['template_name'] ?? '');

// ==========================
// VALIDATION
// ==========================
if ($templateId <= 0 || empty($templateName)) {

    echo json_encode([
        'success' => false,
        'message' => 'Invalid template data'
    ]);

    exit;
}

// ==========================
// UPDATE TEMPLATE
// ==========================
$stmt = $conn->prepare("
    UPDATE templates
    SET template_name = ?
    WHERE id = ?
      AND created_by = ?
");

if (!$stmt) {

    echo json_encode([
        'success' => false,
        'message' => $conn->error
    ]);

    exit;
}

$stmt->bind_param(
    "sii",
    $templateName,
    $templateId,
    $userId
);

$success = $stmt->execute();

// ==========================
// RESPONSE
// ==========================
if ($success) {

    echo json_encode([
        'success' => true
    ]);

} else {

    echo json_encode([
        'success' => false,
        'message' => 'Failed to update template'
    ]);
}