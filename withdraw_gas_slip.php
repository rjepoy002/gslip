<?php
session_start();
require_once 'includes/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    echo json_encode(['success' => false]);
    exit;
}

$id = intval($_POST['id'] ?? 0);

$conn = getDBConnection();

$stmt = $conn->prepare("
    UPDATE gas_slips
    SET
        status = 'draft',
        recommended_by = NULL,
        approved_by = NULL,
        approved_at = NULL,
        rejected_by = NULL,
        rejected_at = NULL
    WHERE id = ?
      AND user_id = ?
      AND status IN ('pending','rejected')
");

$stmt->bind_param("ii", $id, $_SESSION['user_id']);
$stmt->execute();

/* ==========================================
   REMOVE OBSOLETE NOTIFICATIONS
========================================== */
if ($stmt->affected_rows > 0) {

    $delete = $conn->prepare("
        DELETE FROM notifications
        WHERE gas_slip_id = ?
          AND type IN ('new_pending', 'recommended', 'rejected')
    ");

    $delete->bind_param("i", $id);
    $delete->execute();
    $delete->close();
}

echo json_encode([
    'success' => $stmt->affected_rows > 0
]);

$stmt->close();
$conn->close();