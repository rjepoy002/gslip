<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$configPath = dirname(__DIR__) . '/includes/web_push_config.php';
if (!is_file($configPath)) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Web Push is not configured.']);
    exit;
}

$config = require $configPath;
$publicKey = is_array($config) ? trim((string) ($config['public_key'] ?? '')) : '';
if ($publicKey === '') {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Web Push is not configured.']);
    exit;
}

echo json_encode(['success' => true, 'public_key' => $publicKey]);
