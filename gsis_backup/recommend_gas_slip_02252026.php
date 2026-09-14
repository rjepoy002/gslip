<?php
session_start();
require_once 'includes/config.php';

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
   RECOMMENDER E-SIGN CHECK
========================================================= */
if ($role === 'recommender') {

  $esignDir  = __DIR__ . '/uploads/esignatures/';
  $esignFile = $esignDir . 'esign_' . $userId . '.png';

  if (!file_exists($esignFile)) {
    echo json_encode([
      'success' => false,
      'message' => 'E-signature not found. Please upload your e-signature before recommending.'
    ]);
    exit;
  }
}

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
WHERE id = ?;

");

$stmt->bind_param('ii', $userId, $gasSlipId);
$stmt->execute();

if ($stmt->affected_rows > 0) {
  echo json_encode(['success' => true]);
} else {
  echo json_encode([
    'success' => false,
    'message' => 'Gas slip not updated (already processed or invalid state)'
  ]);
}
