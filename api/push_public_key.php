<?php
require_once dirname(__DIR__).'/includes/push_api.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') pushApiResponse(405, false, 'GET required.');

$configPath = dirname(__DIR__) . '/includes/web_push_config.php';
if (!is_file($configPath)) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Browser notifications are unavailable because the server Web Push configuration is missing. Please contact your administrator.']);
    exit;
}

$config = require $configPath;
$publicKey = is_array($config) ? trim((string) ($config['public_key'] ?? '')) : '';
if ($publicKey === '' || empty($config['private_key']) || empty($config['subject'])) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Browser notifications are unavailable because the server Web Push configuration is incomplete. Please contact your administrator.']);
    exit;
}

echo json_encode(['success' => true, 'public_key' => $publicKey, 'csrf' => $_SESSION['push_csrf']]);
