<?php
session_start();
require_once 'includes/config.php';

$conn = getDBConnection();

// === ACCESS CONTROL ===
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
//     header("Location: login.php");
//     exit;
// }

$success = '';
$error = '';

// === RESET PASSWORD ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    if (!empty($_POST['user_id'])) {
        $id = intval($_POST['user_id']);

        // Reset to default password
        $defaultPassword = password_hash("password", PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $defaultPassword, $id);

        if ($stmt->execute()) {
            $success = "Password has been reset to default (password).";
        } else {
            $error = "Error resetting password: " . $stmt->error;
        }
    } else {
        $error = "No user selected for password reset.";
    }
}


// Add or update user accounts
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Normalize inputs
    $first_name = ucfirst(strtolower(trim($_POST['first_name'])));     // "john" -> "John"
    $middle_initial = strtoupper(trim($_POST['middle_initial']));     // "d" -> "D"
    $last_name = ucfirst(strtolower(trim($_POST['last_name'])));      // "doe" -> "Doe"
    $username = strtolower(trim($_POST['username']));                 // "John.Doe" -> "john.doe"
    $area = trim($_POST['area']);
    $role = trim($_POST['role']);
    $status = trim($_POST['status']);
    
    if ($username && $role && $status) {
        if (isset($_POST['form_mode']) && $_POST['form_mode'] === 'edit' && isset($_POST['user_id']) && $_POST['user_id'] !== '') {
            // === UPDATE USER ===
            $id = intval($_POST['user_id']);

            $check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $check->bind_param("si", $username, $id);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = "Username already exists.";
            } else {
                $stmt = $conn->prepare("UPDATE users SET first_name=?, middle_initial=?, last_name=?, username=?, area=?, role=?, status=? WHERE id=?");
                $stmt->bind_param("sssssssi", $first_name, $middle_initial, $last_name, $username, $area, $role, $status, $id);
                if ($stmt->execute()) {
                    $success = true;
                    // 🔹 Update session if current logged-in user is being edited
                    if ($_SESSION['user_id'] == $id) {
                        $_SESSION['area'] = $area;
                        $_SESSION['fullname'] = $first_name . ' ' . $last_name;
                        $_SESSION['role'] = $role; // optional, in case role also changes
                    }
                } else {
                    $error = "Error updating account: " . $stmt->error;
                }
            }
        } else {
            // === ADD USER ===
            $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $check->bind_param("s", $username);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = "Username already exists.";
            } else {

                // Set a default password (you can change this logic)
                $defaultPassword = password_hash("password", PASSWORD_DEFAULT);

                $stmt = $conn->prepare("INSERT INTO users (first_name, middle_initial, last_name, username, password, area, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssss", $first_name, $middle_initial, $last_name, $username, $defaultPassword, $area, $role, $status);
                if ($stmt->execute()) {
                    $success = true;
                } else {
                    $error = "Error adding account: " . $stmt->error;
                }
            }
        }
    } else {
        $error = "All required fields must be filled.";
    }
}

$result = $conn->query("SELECT id, first_name, middle_initial, last_name, username, password, area, role, status, last_login, created_at FROM users ORDER BY id DESC");
$users = $result->fetch_all(MYSQLI_ASSOC);

// Fetch distinct areas from routes table
$areaResult = $conn->query("SELECT DISTINCT origin AS area FROM routes ORDER BY origin ASC");
$areas = $areaResult->fetch_all(MYSQLI_ASSOC);


?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Register User - GSIS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="includes/style.css">
</head>
<body>
  <div class="container py-4">
    <?php include 'header.php'; ?>

  <?php if ($success): ?>
    <div id="registerAlertSuccess" class="alert alert-success alert-dismissible fade show" role="alert">
      Vehicle saved successfully.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php elseif ($error): ?>
    <div id="registerAlertError" class="alert alert-danger alert-dismissible fade show" role="alert">
      <?= $error ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <!-- NEW CODE -->
  <div class="row">
      <div class="col-lg-3 col-md-7 col-sm-12 mb-4">
        <div class="card rounded-4">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h6 id="formTitle"><strong>Add New Account</strong></h6>
              <button type="button" id="cancelEditBtn" class="btn btn-warning px-2 d-none"><i class="fa fa-ban"></i> Cancel Edit</button>
            </div>
            <form method="POST" id="registerForm" action="register.php">
              <input type="hidden" name="form_mode" id="form_mode" value="add">
              <input type="hidden" name="user_id" id="user_id">

              <table class="table borderless w-100" style="max-width: 600px;">
                <tr>
                  <td style="white-space: nowrap; padding-right: 10px;">
                    <label class="form-label">First Name:</label>
                  </td>
                  <td style="border-bottom: 1px dashed #f1f1f1;">
                    <input type="text" name="first_name" id="first_name" class="form-control" required>
                  </td>
                </tr>
                <tr>
                  <td style="white-space: nowrap; padding-right: 10px;">
                    <label for="middle_initial" class="form-label">Middle Initial:</label>
                  </td>
                  <td style="border-bottom: 1px dashed #f1f1f1;">
                    <input type="text" name="middle_initial" id="middle_initial" maxlength="1" class="form-control" required>
                  </td>
                </tr>
                <tr>
                  <td style="white-space: nowrap; padding-right: 10px;">
                    <label for="last_name" class="form-label">Last Name:</label>
                  </td>
                  <td style="border-bottom: 1px dashed #f1f1f1;">
                    <input type="text" name="last_name" id="last_name" class="form-control" required>
                  </td>
                </tr>
                <tr>
                  <td style="white-space: nowrap; padding-right: 10px;">
                    <label class="form-label">Username:</label>
                  </td>
                  <td style="border-bottom: 1px dashed #f1f1f1;">
                    <input type="text" name="username" id="username" class="form-control" autocomplete="new-username" readonly>
                  </td>
                </tr>
                <tr>
                  <td style="white-space: nowrap; padding-right: 10px;">
                    <label for="area" class="form-label">Area:</label>
                  </td>
                  <td style="border-bottom: 1px dashed #f1f1f1;">
                    <select name="area" id="area" class="form-select" required>
                      <option value="">-- Select Area --</option>
                      <?php foreach ($areas as $a): ?>
                        <option value="<?= htmlspecialchars($a['area']) ?>">
                          <?= htmlspecialchars($a['area']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                </tr>
                <tr id="fuel_allocation_row">
                  <td style="white-space: nowrap; padding-right: 10px;">
                    <label for="role" class="form-label">Role:</label>
                  </td>
                  <td style="border-bottom: 1px dashed #f1f1f1;">
                    <select name="role" id="role" class="form-select" required>
                      <option value="user" selected>user</option>
                      <option value="admin">admin</option>
                    </select>
                  </td>
                </tr>
                <tr>
                  <td style="white-space: nowrap; padding-right: 10px;">
                    <label class="form-label">Status</label>
                  </td>
                  <td style="border-bottom: 1px dashed #f1f1f1;">
                    <select name="status" id="status" class="form-select" required>
                      <option value="active" selected>Active</option>
                      <option value="inactive">Inactive</option>
                    </select>
                  </td>
                </tr>
                <tr>
                  <td style="white-space: nowrap; padding-right: 10px;">
                    <label class="form-label">Password:</label>
                  </td>
                  <td>
                    <button type="submit" name="reset_password" id="resetPasswordBtn" class="btn btn-warning btn-sm" disabled>
                      <i class="fas fa-key"></i> Reset Password
                    </button>
                  </td>
                </tr>
              </table>
              <div class="col-12 text-end">
                <button type="submit" name="add_vehicle" id="addBtn" class="btn btn-primary px-2"><i class="fas fa-save"></i> Save Account</button>
                <button type="submit" name="update_vehicle" id="updateBtn" class="btn btn-success px-2 d-none"><i class="fas fa-save"></i> Save Changes</button>
                <a href="main.php" class="btn btn-secondary px-2"><i class="fa fa-reply-all"></i> Back</a>
              </div>
            </form>
          </div>
        </div>
      </div>
      <div class="col-lg-9 col-md-5 col-sm-12 mb-4">
        <div class="card rounded-4">
          <div class="card-body">
            <h6><strong>List of Accounts</strong></h6>
            <div class="table-responsive">
              <table class="styled-table" id="userTable">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Area</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Date Created</th>
                    <th>Last Login</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (count($users) === 0): ?>
                    <tr><td colspan="9" class="text-center text-muted">No accounts found.</td></tr>
                  <?php else: ?>
                    <?php foreach ($users as $i => $v): ?>
                      <tr data-id="<?= $v['id'] ?>" 
                          data-first_name="<?= htmlspecialchars($v['first_name']) ?>" 
                          data-middle_initial="<?= htmlspecialchars($v['middle_initial']) ?>" 
                          data-last_name="<?= htmlspecialchars($v['last_name']) ?>" 
                          data-username="<?= htmlspecialchars($v['username']) ?>"
                          data-area="<?= htmlspecialchars($v['area']) ?>" 
                          data-role="<?= htmlspecialchars($v['role']) ?>" 
                          data-status="<?= htmlspecialchars($v['status']) ?>"
                          data-isdefaultpass="<?= password_verify('password', $v['password']) ? 1 : 0 ?>">
                        <td><?= $i + 1 ?></td>
                        <td>
                            <?= htmlspecialchars($v['last_name']) ?>, 
                            <?= htmlspecialchars($v['first_name']) ?>
                            <?= !empty($v['middle_initial']) ? htmlspecialchars($v['middle_initial']) . '.' : '' ?>
                        </td>
                        <td><?= htmlspecialchars($v['username']) ?></td>
                        <td><?= htmlspecialchars($v['area']) ?></td>
                        <td><?= htmlspecialchars($v['role']) ?></td>
                        <td><?= htmlspecialchars($v['status']) ?></td>
                        <td><?= htmlspecialchars($v['created_at']) ?></td>
                        <td><?= htmlspecialchars($v['last_login']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
<script>
function generateUsername() {
    const firstName = document.getElementById("first_name").value.trim().toLowerCase();
    const middleInitial = document.getElementById("middle_initial").value.trim().toLowerCase();
    const lastName = document.getElementById("last_name").value.trim().toLowerCase();

    // If any of the fields is blank → clear username
    if (!firstName || !middleInitial || !lastName) {
        document.getElementById("username").value = "";
        return;
    }

    // Build username format: first letter + middle initial + "_" + lastname
    const firstLetter = firstName.charAt(0);
    let username = firstLetter + middleInitial + "_" + lastName.replace(/\s+/g, '');
    
    document.getElementById("username").value = username.toLowerCase(); // enforce lowercase
}

// Attach event listeners
document.getElementById("first_name").addEventListener("input", generateUsername);
document.getElementById("middle_initial").addEventListener("input", generateUsername);
document.getElementById("last_name").addEventListener("input", generateUsername);

// Auto-dismiss alerts
const successAlert = document.getElementById('registerAlertSuccess');
if (successAlert) {
  setTimeout(() => bootstrap.Alert.getOrCreateInstance(successAlert).close(), 3000);
}
const errorAlert = document.getElementById('registerAlertError');
if (errorAlert) {
  setTimeout(() => bootstrap.Alert.getOrCreateInstance(errorAlert).close(), 5000);
}

if (window.history.replaceState) {
  window.history.replaceState(null, null, window.location.href);
}

// Edit vehicle on double click
[...document.querySelectorAll('#userTable tbody tr')].forEach(row => {
  row.addEventListener('dblclick', () => {

    const isDefaultPass = row.dataset.isdefaultpass; // 👈 correct variable

    document.getElementById('user_id').value = row.dataset.id;
    document.getElementById('first_name').value = row.dataset.first_name;
    document.getElementById('middle_initial').value = row.dataset.middle_initial;
    document.getElementById('last_name').value = row.dataset.last_name;
    document.getElementById('username').value = row.dataset.username;
    document.getElementById('area').value = row.dataset.area;
    document.getElementById('role').value = row.dataset.role;
    document.getElementById('status').value = row.dataset.status;

    document.getElementById('formTitle').textContent = 'Edit Account';
    document.getElementById('addBtn').classList.add('d-none');
    document.getElementById('updateBtn').classList.remove('d-none');
    document.getElementById('cancelEditBtn').classList.remove('d-none');
    document.getElementById('form_mode').value = 'edit';

    // ✅ Enable/disable Reset Password based on default pass check
    const resetBtn = document.getElementById('resetPasswordBtn');
    if (isDefaultPass === "1") {
      resetBtn.disabled = true;  // already default, don’t allow reset
    } else {
      resetBtn.disabled = false; // custom password, allow reset
    }
  });
});


// Cancel edit
const cancelBtn = document.getElementById('cancelEditBtn');
cancelBtn.addEventListener('click', () => {
  document.getElementById('registerForm').reset();
  document.getElementById('user_id').value = '';
  document.getElementById('formTitle').textContent = 'Add New Account';
  document.getElementById('addBtn').classList.remove('d-none');
  document.getElementById('updateBtn').classList.add('d-none');
  cancelBtn.classList.add('d-none');
  document.getElementById('form_mode').value = 'add';
});

// Reset Password confirmation
document.getElementById('resetPasswordBtn').addEventListener('click', function (e) {
  const userId = document.getElementById('user_id').value;
  const username = document.getElementById('username').value;

  if (!userId) {
    e.preventDefault();
    alert("⚠️ Please select a user first.");
    return;
  }

  const confirmReset = confirm(`Are you sure you want to reset the password for "${username}"?\n\n(Default password: "password")`);
  if (!confirmReset) {
    e.preventDefault(); // cancel form submit
  }
});

// Escape key handler
let escapePressedOnce = false;
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    const updateBtn = document.getElementById('updateBtn');
    const isEditing = updateBtn && getComputedStyle(updateBtn).display !== 'none';
    if (isEditing) {
      cancelBtn.click();
      escapePressedOnce = false;
    } else {
      window.location.href = 'main.php';
    }
    e.preventDefault();
  }
}, true);
</script>

