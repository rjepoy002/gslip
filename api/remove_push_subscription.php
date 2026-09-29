<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/includes/config.php';

header('Content-Type: application/json; charset=utf-8');

function removePushSubscriptionResponse(int $status, bool $success, string $message = ''): void
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    removePushSubscriptionResponse(405, false, 'POST required.');
}

if (!isset($_SESSION['user_id'])) {
    removePushSubscriptionResponse(401, false, 'Authentication required.');
}

$input = json_decode(file_get_contents('php://input'), true);
$endpoint = is_array($input) ? trim((string) ($input['endpoint'] ?? '')) : '';
if ($endpoint === ''
    || strlen($endpoint) > 4096
    || !filter_var($endpoint, FILTER_VALIDATE_URL)
    || strtolower((string) parse_url($endpoint, PHP_URL_SCHEME)) !== 'https') {
    removePushSubscriptionResponse(422, false, 'Invalid subscription data.');
}

$userId = (int) $_SESSION['user_id'];
$conn = getDBConnection(true);
$stmt = $conn->prepare('DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint = ?');
if (!$stmt) {
    removePushSubscriptionResponse(500, false, 'Unable to remove subscription.');
}
$stmt->bind_param('is', $userId, $endpoint);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    removePushSubscriptionResponse(500, false, 'Unable to remove subscription.');
}

echo json_encode(['success' => true]);
