<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'includes/config.php';
require_once 'includes/notifications.php';

header('Content-Type: application/json');

/* =========================================================
   BASIC VALIDATION
========================================================= */
if (!isset($_POST['id'])) {
  echo json_encode(['success' => false, 'message' => 'Missing gas slip ID']);
  exit;
}

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit;
}

$gasSlipId = (int) $_POST['id'];
$userId    = (int) $_SESSION['user_id'];
$role      = $_SESSION['role'];

/* =========================================================
   UPDATE GAS SLIP STATUS
========================================================= */
$conn = getDBConnection();

$stmt = $conn->prepare("
UPDATE gas_slips
SET 
  status = 'recommended',
  recommended_by = ?,
  recommended_at = NOW()
WHERE id = ?
AND status = 'pending';

");

$stmt->bind_param('ii', $userId, $gasSlipId);
$stmt->execute();

/* =====================================================
CREATE NOTIFICATIONS
===================================================== */
notifyGasSlipRecommended($conn, $gasSlipId);

if ($stmt->affected_rows > 0) {
  echo json_encode(['success' => true]);
} else {
  echo json_encode([
    'success' => false,
    'message' => 'Gas slip not updated (already processed or invalid state)'
  ]);
}
