<?php
require_once 'includes/config.php';
$conn = getDBConnection();

// Always start the session before using $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate padded code
$code = str_pad(($_SESSION['user_id'] * 1234) + 1, 6, '0', STR_PAD_LEFT);

// $loggedInName = isset($_SESSION['fullname']) ? ucwords(strtolower($_SESSION['fullname'])) : '';
$loggedInName = isset($_SESSION['fullname']) 
    ? ucwords(strtolower($_SESSION['fullname'])) 
    : '';
$role = $_SESSION['role'] ?? '';

// Role (only add [ADMIN] in green if admin)
$roleLabel = '';
if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $roleLabel = ' <span class="text-success">[Admin]</span>';
}
?>

<!-- Include SweetAlert2 -->
<link rel="stylesheet" href="assets/css/sweetalert2.min.css">
<script src="assets/js/sweetalert2.all.min.js"></script>

<div class="bg-white p-3 rounded shadow-sm mb-4">
  <div class="d-flex align-items-start gap-3">
    
    <!-- Left: Logo -->
    <div>
      <img src="images/logo_paleco.png" alt="Logo" style="height: 60px;">
    </div>

    <!-- Right: Title and Logged in as + Logout -->
    <div class="flex-grow-1 d-flex flex-column">
      
      <!-- Top: Title -->
      <h2 class="mb-0">
        Gas Slip Issuance System 
        <span style="color:blue; text-decoration:underline;">[<?php echo $code; ?>]</span>
      </h2>
      
      <!-- Divider line -->
      <hr class="my-0 w-50" style="border-top: 1px solid #dee2e6;">

      <!-- Bottom: Logged in as + Logout inline -->
      
      <div class="d-flex justify-content-between align-items-center">
        <div class="text-muted small mb-0">
            Logged in as: <strong><?= htmlspecialchars($loggedInName) ?><?= $roleLabel ?></strong>
            <button type="button" 
                    class="btn btn-sm px-2 border-0 bg-transparent" 
                    id="changePasswordBtn" 
                    data-bs-toggle="modal" 
                    data-bs-target="#changePasswordModal"
                    title="Change Password">
                <i class="fa fa-key text-warning"></i>
            </button>
        </div>
          
        <button id="logoutBtn" class="btn btn-danger btn-sm">
          <i class="fa fa-sign-out"></i> Logout
        </button>
      </div>
      
    </div>

  </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
        <div class="modal-header">
            <h6 class="modal-title" id="settingsModalLabel"><strong>Change Password</strong></h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="changePasswordForm">
            <div class="modal-body">
            <input type="hidden" name="changePass_user_id" id="changePass_user_id" value="<?= $_SESSION['user_id'] ?? '' ?>">
            <div class="mb-3">
                <label for="oldPassword" class="form-label">Old Password</label>
                <input type="password" class="form-control" id="oldPassword" name="oldPassword" required>
            </div>
            <div class="mb-3">
                <label for="newPassword" class="form-label">New Password</label>
                <input type="password" class="form-control" id="newPassword" name="newPassword" required>
            </div>
            <div class="mb-3">
                <label for="confirmPassword" class="form-label">Confirm Password</label>
                <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
            </div>
            </div>
            <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-ban"></i> Cancel</button>
            <button type="submit" class="btn btn-info text-white"><i class="fas fa-key"></i> Update</button>
            </div>
        </form>
        </div>
    </div>
</div>

<script>

  // Change Password Form Submission
  document.getElementById('changePasswordForm').addEventListener('submit', async function(e) {
  e.preventDefault();

    const formData = new FormData(this);
    const oldPassword = formData.get('oldPassword');
    const newPassword = formData.get('newPassword');
    const confirmPassword = formData.get('confirmPassword');
    const changePass_user_id = formData.get('changePass_user_id');

    if (newPassword !== confirmPassword) {
      Swal.fire('Error', 'New passwords do not match!', 'error');
      return;
    }

    try {
      const response = await fetch('change_password.php', {
        method: 'POST',
        body: formData
      });

      let result;
      try {
        result = await response.json();
      } catch (jsonError) {
        const rawText = await response.text(); // fallback to see what PHP actually sent
        Swal.fire(
          'Error',
          'Invalid JSON response from server:<br><pre style="text-align:left;">' + rawText + '</pre>',
          'error'
        );
        return; // stop here
      }

      if (result.success) {
        Swal.fire('Success', result.message, 'success');
        this.reset();
        const modal = bootstrap.Modal.getInstance(document.getElementById('changePasswordModal'));
        modal.hide();
      } else {
        Swal.fire('Error', result.message, 'error');
      }
    } catch (error) {
      Swal.fire('Error', 'Something went wrong!<br><small>' + error.message + '</small>', 'error');
    }

  });

  // logout confirmation
  document.getElementById('logoutBtn').addEventListener('click', function () {
    Swal.fire({
      title: 'Logout?',
      text: "Are you sure you want to logout?",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Yes, logout',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        window.location.href = 'main.php?logout=1'; // ✅ calls logout handler
      }
    });
  });

  // Reset changePasswordModal on close (back to initial values)
    document.getElementById('changePasswordModal').addEventListener('hidden.bs.modal', function () {
        this.querySelector('form').reset();
    });

</script>
