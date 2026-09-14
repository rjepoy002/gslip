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
    default:
        $roleClass = 'role-default';
}

// Get counts per status
$statuses = ['draft', 'pending', 'recommended', 'approved', 'cancelled'];
$counts = [];

foreach ($statuses as $status) {

    if ($role === 'user') {

        // USER → own slips
        $stmtCount = $conn->prepare("
            SELECT COUNT(*) as total
            FROM gas_slips
            WHERE status = ?
            AND user_id = ?
            AND printed_at IS NULL
        ");
        $stmtCount->bind_param("si", $status, $userId);


    } elseif ($role === 'recommender') {

        // RECOMMENDER
        if ($status === 'pending') {

            // Can see own + department pending
            $stmtCount = $conn->prepare("
                SELECT COUNT(gs.id) as total
                FROM gas_slips gs
                LEFT JOIN users u ON u.id = gs.user_id
                WHERE 
                  (u.id = ?
                  OR u.department_id = ?)
                  AND gs.status = 'pending'
            ");
            $stmtCount->bind_param("ii", $userId, $department);

        } elseif ($status === 'recommended' || $status === 'approved') {

            // Other statuses → only own records
            $stmtCount = $conn->prepare("
                SELECT COUNT(gs.id) as total
                FROM gas_slips gs
                LEFT JOIN users a ON a.id = ?
                WHERE
                gs.recommended_by = ?
                AND a.department_id = ?
                AND gs.printed_at IS NULL
            ");
            $stmtCount->bind_param("iii", $userId, $userId, $department);

        } elseif ($status === 'draft') {
            // Other statuses → only own records
            $stmtCount = $conn->prepare("
                SELECT COUNT(*) as total
                FROM gas_slips
                WHERE status = ?
                AND user_id = ?
                AND printed_at IS NULL
            ");
            $stmtCount->bind_param("si", $status, $userId);
        }

    } elseif ($role === 'approver') {

        // APPROVER  ---> pending ok. for fix of approved
        if ($status === 'pending') {

            $stmtCount = $conn->prepare("
                SELECT COUNT(gs.id) as total
                FROM gas_slips gs
                LEFT JOIN users u ON u.id = gs.user_id
                LEFT JOIN users a ON a.id = ?
                LEFT JOIN vehicles v ON v.id = gs.vehicle_id
                WHERE gs.status = 'recommended'
                AND u.area = ?
                AND (
                  a.approval_scope = 'both'
                  OR a.approval_scope = v.ownership
                )
            ");
            $stmtCount->bind_param(
                "is",
                $userId,   // approver user_id
                $area
            );

        } elseif ($status === 'approved') {

            $stmtCount = $conn->prepare("
                SELECT COUNT(gs.id) as total
                FROM gas_slips gs
                LEFT JOIN users u ON u.id = gs.user_id
                LEFT JOIN users a ON a.id = ?
                LEFT JOIN vehicles v ON v.id = gs.vehicle_id
                WHERE gs.status = 'approved'
                AND u.area = ?
                AND gs.approved_by = ?
                AND printed_at IS NULL
            ");
            $stmtCount->bind_param(
                "isi",
                $userId,   // approver user_id
                $area,
                $userId
            );
        }else{
            // For other statuses, approver doesn't see counts (or can be set to 0)
            $counts[$status] = 0;
            continue;
        }
        

    }

    $stmtCount->execute();
    $countRow = $stmtCount->get_result()->fetch_assoc();
    $counts[$status] = $countRow['total'] ?? 0;
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

  <!-- Approval Workflow (future) -->
  <?php if ($role === 'admin'): ?>
    <a href="for_approval.php" class="nav-link <?= $current=='for_approval.php'?'active':'' ?>">
      <i class="fa-solid fa-user-check"></i>
      <span>For Approval</span>
    </a>
  <?php endif; ?>

  <div class="nav-separator"></div>

  <!-- Management -->
  <?php if ($role === 'admin'): ?>
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

    <div class="nav-separator"></div>
  <?php endif; ?>

  <!-- Reports -->
  <a href="reports.php" class="nav-link <?= $current=='reports.php'?'active':'' ?>">
    <i class="fa-solid fa-file-lines"></i>
    <span>Reports</span>
  </a>

  <!-- Settings -->
  <?php if ($role === 'admin'): ?>
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

