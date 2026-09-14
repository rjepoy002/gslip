<?php
require_once 'includes/config.php';
header('Content-Type: application/json');

$conn = getDBConnection();

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['ids']) || !is_array($data['ids']) || empty($data['ids'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid input. "ids" must be a non-empty array.'
    ]);
    exit;
}

// Sanitize IDs
$ids = array_map('intval', $data['ids']);

// Build placeholders
$placeholders = implode(',', array_fill(0, count($ids), '?'));

$sql = "
    UPDATE gas_slips
    SET printed_at = NOW()
    WHERE id IN ($placeholders)
      AND printed_at IS NULL
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $conn->error
    ]);
    exit;
}

$stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
$stmt->execute();

echo json_encode([
    'success' => true,
    'ids_received' => $ids,
    'affected_rows' => $stmt->affected_rows
]);

$stmt->close();
$conn->close();
