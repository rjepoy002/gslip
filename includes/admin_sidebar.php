<?php
$current = basename($_SERVER['PHP_SELF']);

// Session values
$fullname = $_SESSION['fullname'] ?? '';
$role     = $_SESSION['role'] ?? '';
$area     = $_SESSION['area'] ?? '';
$designation = $_SESSION['designation'] ?? '';
$department = $_SESSION['department_id'];
$userId = $_SESSION['user_id'];

// Display name
$loggedInName = $fullname
    ? ucwords(strtolower($fullname))
    : 'User';

$roleClass = '';

switch ($role) {
    case 'recommender':
        $roleClass = 'role-recommender';
        break;
    case 'approver':
        $roleClass = 'role-approver';
        break;
    case 'admin':
        $roleClass = 'role-admin';
        break;
    default:
        $roleClass = 'role-default';
}

?>

<aside class="app-sidebar">
  <div class="sidebar-top">
    <div class="sidebar-top-row">
      <div class="sidebar-brand">
        <img src="images/logo_paleco.png" alt="PALECO Logo" class="sidebar-logo">

        <div class="brand-text">
          <span class="brand-name">e-GSlip</span>
          <span class="brand-sub">Gas Slip Issuance System</span>
        </div>
      </div>

      <button id="sidebarToggle" class="sidebar-toggle" title="Toggle sidebar">
        <i class="fa-solid fa-bars"></i>
      </button>
    </div>
  </div>


<nav class="sidebar-nav">

  <!-- Dashboard -->
  <a href="dashboard.php" class="nav-link <?= $current=='dashboard.php'?'active':'' ?>">
    <i class="fa-solid fa-chart-line"></i>
    <span>Dashboard</span>
  </a>

  <!-- Gas Slip Operations -->
  <?php if ($role !== 'approver'): ?>
    <a href="create_gas_slip.php" class="nav-link <?= $current=='create_gas_slip.php'?'active':'' ?>">
      <i class="fa-solid fa-plus"></i>
      <span>Create Gas Slip</span>
    </a>

    <a href="templates.php" class="nav-link <?= $current=='templates.php'?'active':'' ?>">
      <i class="fa-solid fa-bookmark"></i>
      <span>Templates</span>
    </a>

    <a href="draft_gas_slips.php" class="nav-link <?= $current=='draft_gas_slips.php'?'active':'' ?>">
      <i class="fa-solid fa-pen-to-square"></i>
        <span>
          Draft Gas Slips
          <span class="badge bg-secondary ms-1">
            <?= $counts['draft'] ?? 0 ?>
          </span>
        </span>
    </a>
  <?php endif; ?>

  <a href="pending_gas_slips.php" class="nav-link <?= $current=='pending_gas_slips.php'?'active':'' ?>">
    <i class="fa-regular fa-clock"></i>
      <span>
        Pending Gas Slips

        <?php if ($role === 'user'): ?>
          <span class="badge bg-info ms-1">
            <?= $counts['pending'] + $counts['recommended'] ?? 0 ?>
          </span>
        <?php else: ?>
          <span class="badge bg-info ms-1">
            <?= $counts['pending'] ?? 0 ?>
          </span>
        <?php endif; ?>
      
  </a>

  <a href="approved_slips.php" class="nav-link <?= $current=='approved_slips.php'?'active':'' ?>">
    <i class="fa-regular fa-circle-check"></i>
      <span>
        Approved Gas Slips
        <span class="badge bg-success ms-1">
          <?= ($counts['approved'] ?? 0) ?>
        </span>
      </span>
  </a>

  <!-- Approval Workflow -->
  <?php if ($role === 'approver'): ?>
    <a href="for_approval.php" class="nav-link <?= $current=='for_approval.php'?'active':'' ?>">
      <i class="fa-solid fa-user-check"></i>
      <span>For Approval</span>
      <?php if ($approvalCount > 0): ?>
          <span class="badge bg-danger"><?= $approvalCount ?></span>
      <?php endif; ?>
    </a>
  <?php endif; ?>

 <div class="nav-separator"></div>

  <!-- Management -->
  <?php if ($role === 'recommender'): ?>
    <span class="nav-section">Management</span>

    <a href="vehicles.php" class="nav-link <?= $current=='vehicles.php'?'active':'' ?>">
      <i class="fa-solid fa-car"></i>
      <span>Vehicles</span>
    </a>

    <a href="routes.php" class="nav-link <?= $current=='routes.php'?'active':'' ?>">
      <i class="fa-solid fa-road"></i>
      <span>Routes</span>
    </a>

    <a href="fuel_items.php" class="nav-link <?= $current=='fuel_items.php'?'active':'' ?>">
      <i class="fa-solid fa-gas-pump"></i>
      <span>Fuel Items</span>
    </a>

    <a href="accounts.php" class="nav-link <?= $current=='accounts.php'?'active':'' ?>">
      <i class="fa-solid fa-users"></i>
      <span>Accounts</span>
    </a>

    <div class="nav-separator"></div>
  <?php endif; ?>

  <!-- Reports -->
  <a href="reports.php" class="nav-link <?= $current=='reports.php'?'active':'' ?>">
    <i class="fa-solid fa-file-lines"></i>
    <span>Reports</span>
  </a>

  <!-- Settings -->
  <?php if ($role === 'approver'): ?>
    <a href="settings.php" class="nav-link <?= $current=='settings.php'?'active':'' ?>">
      <i class="fa-solid fa-gear"></i>
      <span>Settings</span>
    </a>
  <?php endif; ?>

</nav>

  <div class="sidebar-bottom">
    <span class="user-name">
      Logged in as:
      <br>
      <strong class="<?= $roleClass ?>">
          <?= htmlspecialchars(strtoupper($loggedInName), ENT_QUOTES, 'UTF-8') ?>
      </strong>
      <br>
      <span class="user-designation"><?= htmlspecialchars($designation, ENT_QUOTES, 'UTF-8') ?></span>

    </span>

    <button type="button" id="logoutBtn" class="nav-link logout mt-2">

      <i class="fa-solid fa-arrow-right-from-bracket"></i>
      <span>Logout</span>
    </button>

  </div>
</aside>

