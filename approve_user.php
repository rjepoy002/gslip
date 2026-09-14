<?php
session_start();
require_once 'includes/config.php';

$conn = getDBConnection();

header('Content-Type: application/json');

try {

    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized');
    }

    if (!isset($_POST['id']) || empty($_POST['id'])) {
        throw new Exception('Invalid user ID');
    }

    $userId = intval($_POST['id']);

    $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param("i", $userId);

    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}