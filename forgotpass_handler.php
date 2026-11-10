<?php
session_start();
require_once 'includes/config.php';
require_once __DIR__.'/includes/auth_security.php';
header('Cache-Control: no-store');
authCsrf();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') authRequireCsrf();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; }
$conn = getDBConnection();

$username    = trim($_POST['username'] ?? '');
$middle_name = trim($_POST['middle_name'] ?? '');

if (empty($username) || empty($middle_name)) {
    header("Location: index.php?reset=error");
    exit;
}

/* ===============================
   Verify username + middle name
=============================== */
$stmt = $conn->prepare("
    SELECT id, middle_name
    FROM users
    WHERE username = ?
      AND status = 'active'
    LIMIT 1
");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (
    !$user ||
    strcasecmp(trim($user['middle_name']), $middle_name) !== 0
) {
    header("Location: index.php?reset=invalid");
    exit;
}

/* ===============================
   Generate reset token
=============================== */
$token = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

$stmt = $conn->prepare("
    UPDATE users
    SET reset_token = ?, reset_expires = ?
    WHERE id = ?
");
$stmt->bind_param("ssi", $token, $expires, $user['id']);
$stmt->execute();
$stmt->close();
$conn->close();

/* ===============================
   Redirect to reset password
=============================== */
header("Location: reset_password.php?token=$token");
exit;
