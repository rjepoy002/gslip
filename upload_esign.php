<?php
session_start();
require_once 'includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit;
}

$conn   = getDBConnection();
$userId = (int) $_SESSION['user_id'];

$uploadDir = __DIR__ . '/uploads/esignatures/';
if (!is_dir($uploadDir)) {
  mkdir($uploadDir, 0755, true);
}

/* =========================================================
   DRAWN SIGNATURE
========================================================= */
if (!empty($_POST['drawn_esign'])) {

  $data = $_POST['drawn_esign'];

  if (!str_starts_with($data, 'data:image/png;base64,')) {
    echo json_encode(['success' => false, 'message' => 'Invalid signature data']);
    exit;
  }

  $data = str_replace('data:image/png;base64,', '', $data);
  $data = base64_decode($data);

  if ($data === false) {
    echo json_encode(['success' => false, 'message' => 'Invalid image encoding']);
    exit;
  }

  $filename = 'esign_' . $userId . '.png';
  $path     = $uploadDir . $filename;

  file_put_contents($path, $data);

  $dbPath = 'uploads/esignatures/' . $filename;
  $stmt = $conn->prepare("UPDATE users SET esign_path=? WHERE id=?");
  $stmt->bind_param('si', $dbPath, $userId);
  $stmt->execute();

  echo json_encode(['success' => true]);
  exit;
}

/* =========================================================
   FILE UPLOAD SIGNATURE
========================================================= */
if (!isset($_FILES['esign'])) {
  echo json_encode(['success' => false, 'message' => 'No signature provided']);
  exit;
}

$file = $_FILES['esign'];

$allowedTypes = ['image/png', 'image/jpeg'];
if (!in_array($file['type'], $allowedTypes)) {
  echo json_encode(['success' => false, 'message' => 'Invalid file type']);
  exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['png', 'jpg', 'jpeg'])) {
  echo json_encode(['success' => false, 'message' => 'Invalid file extension']);
  exit;
}

$filename = 'esign_' . $userId . '.' . $ext;
$target   = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $target)) {
  echo json_encode(['success' => false, 'message' => 'Upload failed']);
  exit;
}

$dbPath = 'uploads/esignatures/' . $filename;
$stmt = $conn->prepare("UPDATE users SET esign_path=? WHERE id=?");
$stmt->bind_param('si', $dbPath, $userId);
$stmt->execute();

echo json_encode(['success' => true]);
