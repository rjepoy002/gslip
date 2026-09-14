<?php
session_start();
require_once 'includes/config.php';
$conn = getDBConnection();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_POST['id']) || !isset($_POST['remarks'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$userId = intval($_POST['id']);
$remarks = trim($_POST['remarks']);
$adminId = $_SESSION['user_id'];

$stmt = $conn->prepare("
    UPDATE users 
    SET status = 'rejected',
        remarks = ?,
        declined_by = ?,
        declined_at = NOW()
    WHERE id = ?
");

$stmt->bind_param("sii", $remarks, $adminId, $userId);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $stmt->error]);
}