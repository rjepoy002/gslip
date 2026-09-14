<?php
session_start();
require_once 'includes/config.php';

$conn = getDBConnection();
$error = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        $stmt = $conn->prepare("
            SELECT 
              u.id, 
              u.first_name, 
              u.middle_name, 
              u.last_name, 
              u.username, 
              u.department_id, 
              u.designation, 
              u.password, 
              u.area_id, 
              u.role, 
              u.status, 
              u.remarks,
              a.area_name

            FROM users u
            LEFT JOIN areas a ON a.id = u.area_id
            WHERE u.username = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            
            if ($row['status'] === 'inactive') {

                $error = "Your account is pending approval.";

            } elseif ($row['status'] === 'rejected') {

                $reason = !empty($row['remarks'])
                    ? "<br><strong>Reason:</strong> " . htmlspecialchars($row['remarks'])
                    : "";

                $userData = htmlspecialchars(json_encode([
                    'first_name'    => $row['first_name'],
                    'middle_name'   => $row['middle_name'],
                    'last_name'     => $row['last_name'],
                    'designation'   => $row['designation'],
                    'department_id' => $row['department_id'],
                    'area_id'       => $row['area_id'],
                    'username'      => $row['username']
                ]), ENT_QUOTES, 'UTF-8');

                $error = '
                    Your registration was declined.
                    ' . $reason . '

                    <br><br>

                    <a href="#"
                      id="resubmitLink"
                      class="alert-link"
                      data-user="' . $userData . '">
                      Click here to resubmit
                    </a>
                ';

            } else {
                // 🔎 DEBUGGING OUTPUT
                // REMOVE THIS IN PRODUCTION
                if (isset($_GET['debug'])) {
                    var_dump("Entered password:", $password);
                    var_dump("Stored hash:", $row['password']);
                    var_dump("Verify result:", password_verify($password, $row['password']));
                    exit;
                }
                
                if (password_verify($password, $row['password'])) {
                    // Generate new session token for single active login
                    $sessionToken = bin2hex(random_bytes(32));
                    $update = $conn->prepare("
                        UPDATE users 
                        SET session_token = ?, last_login = NOW() 
                        WHERE id = ?
                    ");
                    $update->bind_param("si", $sessionToken, $row['id']);
                    $update->execute();
                    $update->close();

                    // Store session data
                    session_regenerate_id(true);
                    $middleInitial = '';

                    if (!empty($row['middle_name'])) {
                        $middleInitial = strtoupper(substr(trim($row['middle_name']), 0, 1)) . '. ';
                    }

                    /* =========================================================
                      DETERMINE WORKFLOW ACCESS
                    ========================================================= */

                    $isRecommender = false;
                    $isApprover    = false;

                    /* ---------- CHECK RECOMMENDER ---------- */

                    $recStmt = $conn->prepare("
                        SELECT id
                        FROM department_recommenders
                        WHERE user_id = ?
                        LIMIT 1
                    ");

                    $recStmt->bind_param("i", $row['id']);
                    $recStmt->execute();

                    $recResult = $recStmt->get_result();

                    if ($recResult->num_rows > 0) {
                        $isRecommender = true;
                    }

                    $recStmt->close();

                    /* ---------- CHECK APPROVER ---------- */

                    $appStmt = $conn->prepare("
                        SELECT id
                        FROM department_approvers
                        WHERE user_id = ?
                        LIMIT 1
                    ");

                    $appStmt->bind_param("i", $row['id']);
                    $appStmt->execute();

                    $appResult = $appStmt->get_result();

                    if ($appResult->num_rows > 0) {
                        $isApprover = true;
                    }

                    $appStmt->close();

                    /* ---------- CHECK PRIVATE APPROVER ---------- */

                    $pappStmt = $conn->prepare("
                        SELECT id
                        FROM approval_global_settings
                        WHERE private_vehicle_approver_user_id = ?
                        LIMIT 1
                    ");

                    $pappStmt->bind_param("i", $row['id']);
                    $pappStmt->execute();

                    $pappResult = $pappStmt->get_result();

                    if ($pappResult->num_rows > 0) {
                        $isPrivateApprover = true;
                    }

                    $pappStmt->close();

                    /* =========================================================
                      STORE SESSION DATA
                    ========================================================= */

                    $_SESSION['role'] = $row['role'] ?? ''; // admin/user only

                    $_SESSION['is_recommender'] = $isRecommender;
                    $_SESSION['is_approver']    = $isApprover;
                    $_SESSION['is_private_approver']    = $isPrivateApprover;

                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['fullname'] = $row['first_name'] . ' ' . $middleInitial . ' ' . $row['last_name'];
                    $_SESSION['area'] = $row['area_id'];
                    $_SESSION['session_token'] = $sessionToken;
                    $_SESSION['department_id'] = $row['department_id'];
                    $_SESSION['designation'] = $row['designation'] ?? '';

                    header("Location: dashboard.php");
                    
                    exit;
                } else {
                    $error = "Invalid Username or Password.";
                }
            }

        } else {
            $error = "Invalid Username or Password.";
        }
        $stmt->close();
    } else {
        $error = "Please fill in all fields.";
    }
}

$deptStmt = $conn->query("
    SELECT MIN(id) AS id, name
    FROM departments
    WHERE status = 'active'
    GROUP BY name
    ORDER BY name ASC
");
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login</title>
  <!-- CSS in head.php -->
  <link rel="stylesheet" href="assets/css/sweetalert2.min.css">
  <link rel="stylesheet" href="assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="login-card">
  <div class="d-flex align-items-center mb-4">
    <img src="images/logo_paleco.png" alt="Logo" style="max-width: 100px;">
    <div class="ms-3">
        <h4 class="mb-0"><strong>LOGIN</strong></h4>
        <h6 class="mb-0">Gas Slip Issuance System</h6>
    </div>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?= $error ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <form method="POST" novalidate>
    <div class="mb-3">
      <label for="username" class="form-label">Username</label>
      <input type="text" class="form-control py-2" id="username" name="username" placeholder="Enter your username" required autofocus>
    </div>
    <div class="mb-3">
      <label for="password" class="form-label">Password</label>
      <input type="password" class="form-control py-2" id="password" name="password" placeholder="Enter your password" required>
    </div>
    <div class="d-grid mb-1">
      <button type="submit" class="btn btn-primary py-2">
        <i class="fa fa-sign-in"></i> Login
      </button>
    </div>
    <div style="text-align:right; font-size:14px;"> 
      <a href="#" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal">
        Forgot Password?
      </a>
    </div>
    <div class="text-center small mt-4">
      Don’t have an account yet?
      <a href="#" data-bs-toggle="modal" data-bs-target="#signupModal">
        Create an account
      </a>
    </div>
    <!-- Replace existing version label block with this -->
    <div class="d-flex w-100 justify-content-end mt-5">
      <a role="button" class="version-label" data-bs-toggle="modal" data-bs-target="#changelogModal">
        Version <?= APP_VERSION ?>
      </a>
    </div>
  </form>
</div>

<!-- 🆕 Sign Up Modal -->
<div class="modal fade" id="signupModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4">
      <form method="POST" action="signup_handler.php">
        <div class="modal-header">
          <h5 class="modal-title">Create Account</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          
          <div class="form-floating mb-2">
            <input type="text" name="first_name" class="form-control"
                  id="regFirstName" placeholder="First Name" required>
            <label for="regFirstName">First Name</label>
          </div>

          <div class="form-floating mb-2">
            <input type="text" name="middle_name" class="form-control"
                  id="regMiddleName" placeholder="Middle Name">
            <label for="regMiddleName">Middle Name</label>
          </div>

          <div class="form-floating mb-2">
            <input type="text" name="last_name" class="form-control"
                  id="regLastName" placeholder="Last Name" required>
            <label for="regLastName">Last Name</label>
          </div>

          <div class="form-floating mb-2">
            <input type="text" name="designation" class="form-control"
                  id="regDesignation" placeholder="Designation" required>
            <label for="regDesignation">Designation</label>
          </div>

          <div class="form-floating mb-2">
            <select name="department_id" class="form-select"
                    id="regDepartment" required>
              <option value="" selected disabled>Select Department</option>

              <?php while ($row = $deptStmt->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>">
                  <?= htmlspecialchars($row['name']) ?>
                </option>
              <?php endwhile; ?>

            </select>
            <label for="regDepartment">Department</label>
          </div>

          <div class="form-floating mb-2 d-none" id="areaWrapper">
            <input type="hidden" id="area_hidden">
            <select name="area" class="form-select" id="regArea">
              <option value="" selected disabled>Select Area</option>
            </select>
            <label for="regArea">Area</label>
          </div>

          <div class="form-floating mb-2">
            <input type="text" name="username" class="form-control bg-light"
                  id="regUsername" placeholder="Username"
                  readonly required>
            <label for="regUsername">Username</label>
          </div>

          <div class="form-floating mb-2">
            <input type="password" name="password" class="form-control"
                  id="regPass" placeholder="Password" required>
            <label for="regPass">Password</label>
          </div>

          <div class="form-floating mb-2">
            <input type="password" name="confirm_password" class="form-control"
                  id="regConfirm" placeholder="Confirm Password" required>
            <label for="regConfirm">Confirm Password</label>
          </div>

        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary">Create Account</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- 🔐 Forgot Password Modal -->
<div class="modal fade" id="forgotPasswordModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4">
      <form method="POST" action="forgotpass_handler.php">
        <div class="modal-header">
          <h5 class="modal-title">Forgot Password</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">

          <div class="form-floating mb-2">
            <input type="text" name="username" class="form-control"
                   placeholder="Username" required>
            <label>Username</label>
          </div>

          <div class="form-floating mb-2">
            <input type="text" name="middle_name" class="form-control"
                   placeholder="Middle Name" required>
            <label>Middle Name</label>
          </div>

          <small class="text-muted">
            Please enter your middle name as registered.
          </small>

        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary"
                  data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary">Continue</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- 🧾 Change Logs Modal -->
<?php include 'includes/changelog_modal.php'; ?>

<!-- JS before closing body -->
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/sweetalert2.all.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

  const deptSelect  = document.getElementById('regDepartment');
  const areaWrapper = document.getElementById('areaWrapper');
  const areaSelect  = document.getElementById('regArea');
  const signupForm  = document.querySelector('#signupModal form');

/* ===============================
   Department → Area logic
=============================== */
  if (deptSelect) {
    deptSelect.addEventListener('change', function () {
      const deptText = this.options[this.selectedIndex].text;

      areaSelect.innerHTML =
        '<option value="" selected disabled>Select Area</option>';

      if (deptText === 'ANOD' || deptText === 'ASOD') {

        // ✅ Show area dropdown
        areaWrapper.classList.remove('d-none');
        areaSelect.setAttribute('required', 'required');

        fetch(
          'fetch_department_areas.php?department=' +
          encodeURIComponent(deptText)
        )
          .then(res => res.json())
          .then(data => {
            data.forEach(row => {
              const opt = document.createElement('option');
              opt.value = row.area_id;
              opt.textContent = row.area_name;
              opt.dataset.area = row.area_name; // optional
              areaSelect.appendChild(opt);
            });
          });

        // ❗ Clear hidden default when area is required
        document.getElementById('area_hidden').value = '';

      } else {

        // ✅ HIDE area
        areaWrapper.classList.add('d-none');
        areaSelect.removeAttribute('required');
        areaSelect.value = '';

        // ✅ SET DEFAULT AREA HERE 👇
        document.getElementById('area_hidden').value = 8;
          //'Puerto Princesa [Main Office]';
      }
    });
  }

  if (areaSelect) {
    areaSelect.addEventListener('change', function () {
      document.getElementById('area_hidden').value = this.value; //area_id
    });
  }


  /* ===============================
     Username Generator
  =============================== */
  function generateUsername() {
    const firstName = document.querySelector('#signupModal [name="first_name"]').value.trim().toLowerCase();
    const middleInitial = document.querySelector('#signupModal [name="middle_name"]').value.trim().charAt(0).toLowerCase();
    const lastName = document.querySelector('#signupModal [name="last_name"]').value.trim().toLowerCase();
    const usernameField = document.querySelector('#signupModal [name="username"]');

    if (firstName && middleInitial && lastName) {
      usernameField.value = `${firstName.charAt(0)}${middleInitial}_${lastName}`;
    } else {
      usernameField.value = '';
    }
  }

  ['first_name','middle_name','last_name'].forEach(name => {
    document.querySelector(`#signupModal [name="${name}"]`)
      .addEventListener('input', generateUsername);
  });

  /* ===============================
     Focus + Highlight Helper
  =============================== */
  function focusAndHighlight(el) {
    el.classList.add('input-error');
    el.focus();

    el.addEventListener('input', function removeError() {
      el.classList.remove('input-error');
      el.removeEventListener('input', removeError);
    });
  }

  /* ===============================
     Signup Validation (SweetAlert)
  =============================== */
  signupForm.addEventListener('submit', function(e) {

    const firstNameField  = signupForm.querySelector('[name="first_name"]');
    const usernameField   = signupForm.querySelector('[name="username"]');
    const pwdField        = signupForm.querySelector('[name="password"]');
    const confirmPwdField = signupForm.querySelector('[name="confirm_password"]');

    // Username must exist
    if (!usernameField.value) {
      e.preventDefault();
      Swal.fire({
        icon: 'warning',
        title: 'Incomplete Name',
        text: 'Please complete your name to generate a username.'
      }).then(() => {
        const firstName = signupForm.querySelector('[name="first_name"]');
        const middleName = signupForm.querySelector('[name="middle_name"]');
        const lastName = signupForm.querySelector('[name="last_name"]');

        if (!firstName.value.trim()) {
          focusAndHighlight(firstName);
        } else if (!middleName.value.trim()) {
          focusAndHighlight(middleName);
        } else if (!lastName.value.trim()) {
          focusAndHighlight(lastName);
        }
      });
      return;
    }

    // Password mismatch
    if (pwdField.value !== confirmPwdField.value) {
      e.preventDefault();
      Swal.fire({
        icon: 'error',
        title: 'Password Mismatch',
        text: 'Passwords do not match.'
      }).then(() => {
        focusAndHighlight(confirmPwdField);
      });
      return;
    }

    // Weak password
    if (pwdField.value.length < 8) {
      e.preventDefault();
      Swal.fire({
        icon: 'warning',
        title: 'Weak Password',
        text: 'Password must be at least 8 characters long.'
      }).then(() => {
        focusAndHighlight(pwdField);
      });
      return;
    }

  });

  /* ===============================
     Signup Success Alert
  =============================== */
  const params = new URLSearchParams(window.location.search);
  if (params.get('signup') === 'success') {
    Swal.fire({
      icon: 'success',
      title: 'Account Created',
      text: 'Your account has been successfully created. You may now log in.'
    }).then(() => {
      window.history.replaceState({}, document.title, window.location.pathname);
    });
  }

  /* ===============================
     Reset Signup Modal on Close
  =============================== */
  const signupModal = document.getElementById('signupModal');

  signupModal.addEventListener('hidden.bs.modal', function () {
    const form = signupModal.querySelector('form');

    // Reset all form fields
    form.reset();

    // Clear readonly username explicitly
    form.querySelector('[name="username"]').value = '';

    // Hide and reset area dropdown
    areaWrapper.classList.add('d-none');
    areaSelect.innerHTML = '<option value="" selected disabled>Select Area</option>';
    areaSelect.removeAttribute('required');

    // Remove error highlights
    form.querySelectorAll('.input-error').forEach(el => {
      el.classList.remove('input-error');
    });
  });

  /* ===============================
    Reset Forgot Password Modal on Close
  =============================== */
  const forgotPasswordModal = document.getElementById('forgotPasswordModal');

  if (forgotPasswordModal) {
    forgotPasswordModal.addEventListener('hidden.bs.modal', function () {

      const form = forgotPasswordModal.querySelector('form');

      if (form) {
        // Reset all fields
        form.reset();
      }

      // Remove error highlights
      forgotPasswordModal.querySelectorAll('.input-error').forEach(el => {
        el.classList.remove('input-error');
      });

      // Remove validation classes if any
      forgotPasswordModal.querySelectorAll('.is-invalid, .is-valid').forEach(el => {
        el.classList.remove('is-invalid', 'is-valid');
      });

    });
    
  }

  forgotPasswordModal.addEventListener('shown.bs.modal', function () {
    const input = forgotPasswordModal.querySelector('input[name="username"]');
    if (input) input.focus();
  });

  /* ===============================
     Signup Error Alerts
  =============================== */
  // Username already exists alert
  if (params.get('signup') === 'exists') {
    Swal.fire({
      icon: 'error',
      title: 'Username Already Exists',
      text: 'The generated username is already taken. Please contact the administrator or try again.',
      confirmButtonText: 'OK'
    }).then(() => {
      // Optional: auto-open signup modal again
      const signupModal = new bootstrap.Modal(document.getElementById('signupModal'));
      signupModal.show();
    });

    // Remove query param so alert won’t repeat
    window.history.replaceState({}, document.title, window.location.pathname);
  }

  /* ===============================
    // Forgot password errors
  =============================== */
  if (params.get('reset') === 'invalid') {
    Swal.fire({
      icon: 'error',
      title: 'Verification Failed',
      text: 'Username and middle name do not match our records.'
    }).then(() => {
      const forgotModal = new bootstrap.Modal(
        document.getElementById('forgotPasswordModal')
      );
      forgotModal.show();
    });

    window.history.replaceState({}, document.title, window.location.pathname);
  }

  /* ===============================
    Rejected Account Resubmit
  =============================== */
  document.addEventListener('click', function(e) {

    if (e.target.id === 'resubmitLink') {

      e.preventDefault();

      const user = JSON.parse(
        e.target.getAttribute('data-user')
      );

      // Open modal
      const modal = new bootstrap.Modal(
        document.getElementById('signupModal')
      );

      modal.show();

      // Fill fields
      document.getElementById('regFirstName').value =
        user.first_name || '';

      document.getElementById('regMiddleName').value =
        user.middle_name || '';

      document.getElementById('regLastName').value =
        user.last_name || '';

      document.getElementById('regDesignation').value =
        user.designation || '';

      document.getElementById('regUsername').value =
        user.username || '';

      // Department
      document.getElementById('regDepartment').value =
        user.department_id || '';

      // Trigger department logic
      deptSelect.dispatchEvent(new Event('change'));

      // Wait for area fetch
      setTimeout(() => {

        if (user.area_id) {

          document.getElementById('regArea').value =
            user.area_id;

          document.getElementById('area_hidden').value =
            user.area_id;
        }

      }, 500);

    }

  });
  

});
</script>

</body>
</html>

