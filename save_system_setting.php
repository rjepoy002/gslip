<?php
session_start();

require_once 'includes/config.php';

header('Content-Type: application/json');

if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST'
    || empty($_SESSION['user_id'])
    || ($_SESSION['role'] ?? '') !== 'admin'
) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Only administrators can change this setting.'
    ]);
    exit;
}

$key = $_POST['setting_key'] ?? '';
$value = $_POST['setting_value'] ?? '';

if ($key !== 'print_once' || !in_array($value, ['0', '1'], true)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid system setting request.'
    ]);
    exit;
}

$conn = getDBConnection();
$stmt = $conn->prepare(
    'INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) '
    . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
);

if (!$stmt) {
    error_log('Unable to prepare system setting update: ' . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save the setting.']);
    exit;
}

$stmt->bind_param('ss', $key, $value);
$success = $stmt->execute();
$stmt->close();

if (!$success) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save the setting.']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => $value === '1'
        ? 'Print Once enabled. Printed gas slips can no longer be reprinted.'
        : 'Print Once disabled. Printed gas slips can now be reprinted.'
]);
