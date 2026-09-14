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
            SELECT id, first_name, last_name, username, password, area, role, status
            FROM users
            WHERE username = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {

            if ($row['status'] !== 'active') {
                $error = "Your account is inactive. Please contact the administrator.";
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
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['fullname'] = $row['first_name'] . ' ' . $row['last_name'];
                    $_SESSION['role'] = $row['role'];
                    $_SESSION['area'] = $row['area'];
                    $_SESSION['session_token'] = $sessionToken;

                    header("Location: main.php");
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

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="includes/style.css">
  <style>
    body {
      background-color: #f8f9fa;
    }
    .login-card {
      max-width: 400px;
      margin: 80px auto;
      padding: 30px;
      border-radius: 15px;
      box-shadow: 0 0 15px rgba(0,0,0,0.1);
      background-color: #fff;
    }
  </style>
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
      <?= htmlspecialchars($error) ?>
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
    <div class="d-grid mb-4">
      <button type="submit" class="btn btn-primary py-2">
        <i class="fa fa-sign-in"></i> Login
      </button>
    </div>
    <!-- Replace existing version label block with this -->
    <div class="d-flex w-100 justify-content-end mt-5">
      <a role="button" class="version-label" data-bs-toggle="modal" data-bs-target="#changelogModal">
        Version 2.6
      </a>
    </div>
  </form>
</div>

<!-- 🧾 Change Logs Modal -->
<div class="modal fade" id="changelogModal" tabindex="-1" aria-labelledby="changelogModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content rounded-4 shadow">
      <div class="modal-header">
        <h6 class="modal-title fw-bold" id="changelogModalLabel">Change logs</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="font-size: 0.7rem;">
        <ol class="mb-0 ps-3">
          <li><strong>08/10/2025</strong> – Reorganized <code>main.php</code> to display existing gas slips separately. Added dedicated page for creating new slips.</li>
          <li><strong>08/14/2025</strong> – Integrated modern and responsive design using <code>style.css</code> with Roboto font and clean layouts.</li>
          <li><strong>08/22/2025</strong> – Added header inclusion via <code>header.php</code> for consistent system titles and navigation.</li>
          <li><strong>08/25/2025</strong> – Implemented route dropdowns populated dynamically from the database, displaying <em>distance (km)</em> and <em>fuel allocation</em>.</li>
          <li><strong>08/27/2025</strong> – Added <em>keyboard shortcuts</em> for quick actions (Alt+F for Fuel, Alt+V for Vehicle, + / - for add/remove rows).</li>
          <li><strong>08/30/2025</strong> – Implemented <em>signatories per area</em> assignment. Users are now required to have an assigned area before slip submission.</li>
          <li><strong>09/05/2025</strong> – Added <em>Excel multi-upload</em> feature for area signatories with automatic validation and update per assigned area.</li>
          <li><strong>09/10/2025</strong> – Enhanced <em>login system</em> with password hashing, session tokens, and inactive account restrictions.</li>
          <li><strong>09/12/2025</strong> – Introduced <em>single active session</em> per user with session token regeneration for improved security.</li>
          <li><strong>09/15/2025</strong> – Added <em>route-based fuel auto-fill</em> feature for diesel/unleaded fuel types.</li>
          <li><strong>09/18/2025</strong> – Added new database field <code>idling_rate</code> in <code>vehicles</code> table for enhanced truck fuel computation.</li>
          <li><strong>09/20/2025</strong> – Introduced <em>truck-specific fuel formula</em> based on distance and load factor.</li>
          <li><strong>09/25/2025</strong> – Updated fuel filtering logic to automatically exclude <em>diesel</em> for <em>2-wheel vehicles</em>.</li>
          <li><strong>09/29/2025</strong> – Locked <em>destination</em> field to the user’s assigned area to ensure data consistency.</li>
          <li><strong>09/30/2025</strong> – Optimized <em>vehicle and route data loading</em> for faster dropdown response.</li>
          <li><strong>10/01/2025</strong> – Updated <em>fuel request validation</em> to prevent blank or invalid quantity entries.</li>
          <li><strong>10/03/2025</strong> – Enhanced <em>fuel allocation logic</em> to check valid <code>KM per Liter</code> before applying route auto-fill.</li>
          <li><strong>10/05/2025</strong> – Improved <em>vehicle dropdowns</em> to filter by <em>category</em> (4-wheels, 2-wheels, trucks) and <em>ownership</em> (coop-owned, private).</li>
          <li><strong>10/06/2025</strong> – Added hidden fields for <code>vehicle_category</code> and <code>km_per_liter</code> to support dynamic JS logic.</li>
          <li><strong>10/07/2025</strong> – Refined <em>route and fuel interaction scripts</em> for better modularity and maintainability.</li>
          <li><strong>10/08/2025</strong> – Fixed <em>Escape key</em> behavior to properly close modals before redirecting to <code>main.php</code>.</li>
          <li><strong>10/09/2025</strong> – Implemented <em>vehicle edit modal</em> (double-click row) to streamline vehicle data updates.</li>
          <li><strong>10/10/2025</strong> – Finalized <em>UI and UX improvements</em> for better responsiveness, cleaner form structure, and consistent color themes.</li>
          <li><strong>10/13/2025</strong> – Added confirmation message for escape button in <code>login.php</code>.</li>
        </ol>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
