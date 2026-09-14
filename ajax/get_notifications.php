<?php
session_start();

require_once '../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$conn = getDBConnection();

$userId = (int)$_SESSION['user_id'];

$sql = "
SELECT
    id,
    gas_slip_id,
    type,
    title,
    message,
    created_at
FROM notifications
WHERE user_id = ?
  AND is_displayed = 0
ORDER BY created_at ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();

$notifications = [];

while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

header('Content-Type: application/json');
echo json_encode($notifications);