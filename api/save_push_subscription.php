<?php
require_once dirname(__DIR__).'/includes/push_api.php';
pushApiRequirePost();

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    pushApiResponse(400, false, 'Invalid subscription data.');
}

$endpoint = trim((string) ($input['endpoint'] ?? ''));
$p256dh = trim((string) ($input['keys']['p256dh'] ?? ''));
$auth = trim((string) ($input['keys']['auth'] ?? ''));
$base64UrlPattern = '/^[A-Za-z0-9_-]{16,1024}$/';

if (strlen($endpoint) > 4096
    || !filter_var($endpoint, FILTER_VALIDATE_URL)
    || strtolower((string) parse_url($endpoint, PHP_URL_SCHEME)) !== 'https'
    || !preg_match($base64UrlPattern, $p256dh)
    || !preg_match($base64UrlPattern, $auth)) {
    pushApiResponse(422, false, 'Invalid subscription data.');
}

$userId = (int) $_SESSION['user_id'];


$existing = $conn->prepare('SELECT id FROM push_subscriptions WHERE endpoint = ? LIMIT 1');
if (!$existing) {
    pushApiResponse(500, false, 'Unable to save subscription.');
}
$existing->bind_param('s', $endpoint);
$existing->execute();
$row = $existing->get_result()->fetch_assoc();
$existing->close();

if ($row) {
    $id = (int) $row['id'];
    $stmt = $conn->prepare(
        'UPDATE push_subscriptions
         SET user_id = ?, p256dh_key = ?, auth_key = ?, updated_at = NOW()
         WHERE id = ?'
    );
} else {
    $stmt = $conn->prepare(
        'INSERT INTO push_subscriptions (user_id, endpoint, p256dh_key, auth_key, created_at, updated_at)
         VALUES (?, ?, ?, ?, NOW(), NOW())'
    );
}

if (!$stmt) {
    pushApiResponse(500, false, 'Unable to save subscription.');
}

if ($row) {
    $stmt->bind_param('issi', $userId, $p256dh, $auth, $id);
} else {
    $stmt->bind_param('isss', $userId, $endpoint, $p256dh, $auth);
}

if (!$stmt->execute()) {
    $stmt->close();
    pushApiResponse(500, false, 'Unable to save subscription.');
}
$stmt->close();

echo json_encode(['success' => true]);
