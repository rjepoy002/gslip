<?php
require_once dirname(__DIR__).'/includes/push_api.php';
pushApiRequirePost();

$input = json_decode(file_get_contents('php://input'), true);
$endpoint = is_array($input) ? trim((string) ($input['endpoint'] ?? '')) : '';
if ($endpoint === ''
    || strlen($endpoint) > 4096
    || !filter_var($endpoint, FILTER_VALIDATE_URL)
    || strtolower((string) parse_url($endpoint, PHP_URL_SCHEME)) !== 'https') {
    pushApiResponse(422, false, 'Invalid subscription data.');
}

$userId = (int) $_SESSION['user_id'];

$stmt = $conn->prepare('DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint = ?');
if (!$stmt) {
    pushApiResponse(500, false, 'Unable to remove subscription.');
}
$stmt->bind_param('is', $userId, $endpoint);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    pushApiResponse(500, false, 'Unable to remove subscription.');
}

echo json_encode(['success' => true]);
