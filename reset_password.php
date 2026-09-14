<?php
session_start();
require_once 'includes/config.php';

$conn = getDBConnection();

$token = $_GET['token'] ?? '';
$token = trim($token);

$validToken = false;
$userId = null;

if ($token) {
    $stmt = $conn->prepare("
        SELECT id 
        FROM users
        WHERE reset_token = ?
          AND reset_expires > NOW()
          AND status = 'active'
        LIMIT 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $validToken = true;
        $userId = $row['id'];
    }
    $stmt->close();
}

/* ===============================
   Handle password reset submit
=============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token    = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (!$token || !$password || !$confirm || $password !== $confirm || strlen($password) < 8) {
        header("Location: reset_password.php?token={$token}&error=invalid");
        exit;
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("
        UPDATE users
        SET password = ?,
            reset_token = NULL,
            reset_expires = NULL
        WHERE reset_token = ?
          AND reset_expires > NOW()
        LIMIT 1
    ");
    $stmt->bind_param("ss", $hashed, $token);
    $stmt->execute();

    if ($stmt->affected_rows === 1) {
        $stmt->close();
        $conn->close();
        header("Location: index.php?reset=success");
        exit;
    }

    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reset Password</title>

  <link rel="stylesheet" href="assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/sweetalert2.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="login-card">

  <h4 class="mb-3 text-center"><strong>Reset Password</strong></h4>

  <?php if (!$validToken): ?>
    <div class="alert alert-danger text-center">
      Invalid or expired reset link.
    </div>
    <div class="text-center">
      <a href="index.php">Back to Login</a>
    </div>

  <?php else: ?>

    <form method="POST" id="resetForm">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

      <div class="mb-3">
        <label class="form-label">New Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Confirm Password</label>
        <input type="password" name="confirm_password" class="form-control" required>
      </div>

      <div class="d-grid gap-2 mt-2">
        <button type="submit" class="btn btn-primary">Reset Password</button>
        <button type="button" class="btn btn-secondary" id="cancelReset">
          Cancel
        </button>
      </div>

    </form>

  <?php endif; ?>

</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/sweetalert2.all.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {

  const params = new URLSearchParams(window.location.search);
  const resetForm = document.getElementById('resetForm');
  const cancelBtn = document.getElementById('cancelReset');

  if (params.get('error') === 'invalid') {
    Swal.fire({
      icon: 'error',
      title: 'Reset Failed',
      text: 'Password reset failed. Please try again.'
    });
  }

  if (resetForm) {
    resetForm.addEventListener('submit', function (e) {
      const pwd = resetForm.querySelector('[name="password"]').value;
      const confirm = resetForm.querySelector('[name="confirm_password"]').value;

      if (pwd !== confirm) {
        e.preventDefault();
        Swal.fire({
          icon: 'error',
          title: 'Password Mismatch',
          text: 'Passwords do not match.'
        });
        return;
      }

      if (pwd.length < 8) {
        e.preventDefault();
        Swal.fire({
          icon: 'warning',
          title: 'Weak Password',
          text: 'Password must be at least 8 characters long.'
        });
      }
    });
  }

  if (cancelBtn) {
    cancelBtn.addEventListener('click', function () {
      Swal.fire({
        icon: 'question',
        title: 'Cancel Password Reset?',
        text: 'Any changes will be lost.',
        showCancelButton: true,
        confirmButtonText: 'Yes, go back',
        cancelButtonText: 'Stay here'
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.href = 'index.php';
        }
      });
    });
  }

});
</script>

</body>
</html>
