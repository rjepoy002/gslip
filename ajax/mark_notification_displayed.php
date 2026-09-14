<?php
session_start();

require_once '../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$conn = getDBConnection();

$id = (int)($_POST['id'] ?? 0);
$userId = (int)$_SESSION['user_id'];

$sql = "
UPDATE notifications
SET is_displayed = 1
WHERE id = ?
AND user_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $id, $userId);
$stmt->execute();

echo "OK";