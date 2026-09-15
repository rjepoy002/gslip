<?php
$current = basename($_SERVER['PHP_SELF']);

// Session values
$fullname = $_SESSION['fullname'] ?? '';
$role     = $_SESSION['role'] ?? '';
$area     = $_SESSION['area'] ?? '';
$designation = $_SESSION['designation'] ?? '';
$department = $_SESSION['department_id'];
$userId = $_SESSION['user_id'];
$isAdmin        = ($role === 'admin');
$isRecommender  = !empty($_SESSION['is_recommender']);
$isApprover     = !empty($_SESSION['is_approver']);
$isPrivateApprover     = !empty($_SESSION['is_private_approver']);
$isPrimaryApprover = false;

$stmtPrimary = $conn->prepare("
    SELECT is_primary
    FROM department_approvers
    WHERE department_id = ?
    AND user_id = ?
    AND is_primary = 1
    LIMIT 1
");

$stmtPrimary->bind_param(
    "ii",
    $department,
    $userId
);

$stmtPrimary->execute();

$resultPrimary = $stmtPrimary->get_result();

$isPrimaryApprover = $resultPrimary->num_rows > 0;

$stmtPrimary->close();

// Display name
$loggedInName = $fullname
    ? ucwords(strtolower($fullname))
    : 'User';

$roleClass = '';

$roleClass = 'role-default';

if ($isAdmin) {
    $roleClass = 'role-admin';
} elseif ($isApprover) {
    $roleClass = 'role-approver';
} elseif ($isPrivateApprover) {
    $roleClass = 'role-private-approver';
} elseif ($isRecommender) {
    $roleClass = 'role-recommender';
}

// $isAdmin = "";
// $isApprover = true;

// =========================================================
// Get counts per status
// =========================================================

$statuses = ['draft', 'pending', 'recommended', 'approved', 'cancelled'];
$counts = [];

foreach ($statuses as $status) {

  // user → only own records
  $stmtCount = $conn->prepare("
      SELECT COUNT(*) as total
      FROM gas_slips
      WHERE status = ?
      AND user_id = ?
      AND printed_at IS NULL
  ");
  $stmtCount->bind_param("si", $status, $userId);

  // For recommenders, also count department pending slips
  if($isRecommender && $status !== 'draft'){
    
    if($status === 'approved'){

      $stmtCount = $conn->prepare("
          SELECT COUNT(gs.id) as total
          FROM gas_slips gs
          LEFT JOIN users u ON u.id = gs.user_id
          WHERE 
            gs.recommended_by = ?
            AND gs.status = ?
      ");
      $stmtCount->bind_param("is", $userId, $status);

    }elseif($status === 'recommended'){

      $stmtCount = $conn->prepare("
          SELECT COUNT(gs.id) as total
          FROM gas_slips gs
          LEFT JOIN users u ON u.id = gs.user_id
          WHERE 
            gs.recommended_by = ?
            AND gs.status = ?
      ");
      $stmtCount->bind_param("is", $userId, $status);

    }elseif($status === 'pending'){

      $stmtCount = $conn->prepare("
        SELECT COUNT(gs.id) as total
        FROM gas_slips gs
        LEFT JOIN users u ON u.id = gs.user_id
        LEFT JOIN vehicles v ON v.id = gs.vehicle_id
        WHERE 
            u.department_id = ?
            AND u.area_id = ?
            AND gs.status = ?
            AND (
                v.ownership <> 'private'
                OR gs.user_id = ?
            )
      ");
      $stmtCount->bind_param("iisi", $department, $area, $status, $userId);

    }

  }elseif($isApprover){
      // For approvers, count recommended slips in their deparment. if ASOD/ANOD then count all recommended in their area. if scope is private then count all recommended regardless of department/area
      if($status === 'pending'){

        if($isPrivateApprover){

            $stmtCount = $conn->prepare("
                SELECT COUNT(gs.id) as total

                FROM gas_slips gs

                LEFT JOIN vehicles v
                    ON v.id = gs.vehicle_id

                WHERE
                    v.ownership = 'private'
                    AND gs.status = 'recommended'
            ");

        }else{

            $stmtCount = $conn->prepare("
                SELECT COUNT(gs.id) as total

                FROM gas_slips gs

                LEFT JOIN users u
                    ON u.id = gs.user_id

                LEFT JOIN users a
                    ON a.id = ?

                LEFT JOIN vehicles v
                    ON v.id = gs.vehicle_id

                WHERE (

                    (
                        v.ownership = 'private'
                        AND gs.status = 'pending'
                        AND u.department_id = a.department_id
                    )

                    OR

                    (
                        v.ownership = 'coop-owned'
                        AND gs.status = 'recommended'

                        AND EXISTS (
                            SELECT 1
                            FROM department_areas da
                            WHERE da.department_id = a.department_id
                            AND da.area_id = u.area_id
                        )
                    )

                )
            ");

            $stmtCount->bind_param("i", $userId);
        }

      }elseif($status === 'recommended'){

        if(!$isPrivateApprover){
          $stmtCount = $conn->prepare("
              SELECT COUNT(gs.id) as total
              FROM gas_slips gs
              LEFT JOIN users u ON u.id = gs.user_id
              WHERE 
                gs.recommended_by = ?
                AND gs.status = ?
          ");
          $stmtCount->bind_param("is", $userId, $status);
        }

      // For approvers, count approved slips where they are the approver. if ASOD/ANOD then count all approved in their area. if scope is private then count all approved regardless of department/area
        }elseif($status === 'approved'){

            $stmtCount = $conn->prepare("

                SELECT COUNT(DISTINCT gs.id) AS total

                FROM gas_slips gs

                LEFT JOIN users u
                    ON u.id = gs.user_id

                LEFT JOIN users a
                    ON a.id = ?

                LEFT JOIN vehicles v
                    ON v.id = gs.vehicle_id

                WHERE
                    gs.status = 'approved'

                    AND gs.printed_at IS NULL

                    AND (

                        /* =====================================
                          PRIVATE VEHICLES
                          ===================================== */
                        (
                            v.ownership = 'private'

                            AND (
                                EXISTS (
                                    SELECT 1
                                    FROM approval_global_settings ags
                                    WHERE ags.private_vehicle_approver_user_id = a.id
                                )

                                OR gs.recommended_by = ?

                                OR gs.approved_by = ?
                            )
                        )

                        OR

                        /* =====================================
                          CO-OP OWNED VEHICLES
                          ===================================== */
                        (
                            v.ownership = 'coop-owned'

                            AND EXISTS (
                                SELECT 1
                                FROM department_areas da
                                WHERE da.department_id = a.department_id
                                AND da.area_id = u.area_id
                            )
                        )

                    )

            ");

            $stmtCount->bind_param(
                "iii",
                $userId, // a.id
                $userId, // recommended_by for private
                $userId  // approved_by for private
            );
        }
  }

  $stmtCount->execute();
  $countRow = $stmtCount->get_result()->fetch_assoc();
  $counts[$status] = $countRow['total'] ?? 0;
}

/* =========================================================
 QUERY ACCOUNTS FOR APPROVAL
========================================================= */
$approvalCount = 0;

// $stmt = $conn->prepare("
//     SELECT COUNT(*)
//     FROM users u
//     WHERE u.status = 'inactive'
//     AND EXISTS (
//         SELECT 1
//         FROM department_areas da
//         WHERE da.department_id = ?
//         AND da.area_id = u.area_id
//     )
// ");

if($isAdmin){
  $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM users u
        WHERE u.status = 'inactive'
  ");
  
}else{
  $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM users u
        WHERE u.status = 'inactive'
        AND u.department_id = ?
  ");
  $stmt->bind_param('i', $department);
}


$stmt->execute();
$stmt->bind_result($approvalCount);
$stmt->fetch();
$stmt->close();

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
  <?php if (!$isApprover): ?>
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
        <span class="badge bg-info ms-1">
          <!--?= //$counts['pending'] ?? 0 ?-->
          <?=
              ($counts['pending'] ?? 0) +
              ((!$isAdmin && !$isApprover && !$isRecommender)
                  ? ($counts['recommended'] ?? 0)
                  : 0)
          ?>
        </span>
        <!-- <?php // if (!$isAdmin && !$isApprover && !$isRecommender): ?>
          <span class="badge bg-info ms-1">
            <!?= $counts['pending'] + $counts['recommended'] ?? 0 ?>
          </span>
        <?php // else: ?>
          <span class="badge bg-info ms-1">
            <!?= $counts['pending'] ?? 0 ?>
          </span>
        <?php // endif; ?> -->
      
  </a>

  <?php if ($isRecommender || $isApprover && !$isPrivateApprover): ?>
    <a href="recommended_gas_slips.php" class="nav-link <?= $current=='recommended_gas_slips.php'?'active':'' ?>">
      <i class="fa-solid fa-thumbs-up"></i>
        <span>
          Recommended Slips

          <span class="badge bg-warning ms-1">
            <?= $counts['recommended'] ?? 0 ?>
          </span>
          <!-- <?php // if (!$isAdmin && !$isApprover && !$isRecommender): ?>
            <span class="badge bg-info ms-1">
              <!?= $counts['pending'] + $counts['recommended'] ?? 0 ?>
            </span>
          <?php // else: ?>
            <span class="badge bg-info ms-1">
              <!?= $counts['pending'] ?? 0 ?>
            </span>
          <?php // endif; ?> -->
        
    </a>
  <?php endif; ?>

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
  <?php if ($isApprover || $isAdmin): ?>
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
  <span class="nav-section">Management</span>
  <?php if ($isRecommender || $isApprover || $isAdmin): ?>

    <a href="accounts.php" class="nav-link <?= $current=='accounts.php'?'active':'' ?>">
      <i class="fa-solid fa-users"></i>
      <span>Accounts</span>
    </a>

  <?php endif; ?>

    <a href="fuel_items.php" class="nav-link <?= $current=='fuel_items.php'?'active':'' ?>">
      <i class="fa-solid fa-gas-pump"></i>
      <span>Fuel Items</span>
    </a>

    <a href="vehicles.php" class="nav-link <?= $current=='vehicles.php'?'active':'' ?>">
      <i class="fa-solid fa-car"></i>
      <span>Vehicles</span>
    </a>
  
    <!-- Routes available to ALL users -->
    <a href="routes.php" class="nav-link <?= $current=='routes.php'?'active':'' ?>">
      <i class="fa-solid fa-road"></i>
      <span>Routes</span>
    </a>

    <div class="nav-separator"></div>
  

  <?php if ($isRecommender || $isApprover || $isAdmin): ?>
    <!-- Reports -->
    <a href="reports.php" class="nav-link <?= $current=='reports.php'?'active':'' ?>">
      <i class="fa-solid fa-file-lines"></i>
      <span>Reports</span>
    </a>
  <?php endif; ?>

  <!-- ?php if ($isApprover || $isAdmin): ?-->
  <?php if ($isPrimaryApprover || $isAdmin): ?>
    <!-- Settings -->
    <a href="settings.php" class="nav-link <?= $current=='settings.php'?'active':'' ?>">
      <i class="fa-solid fa-gear"></i>
      <span>Settings</span>
    </a>
  <?php endif; ?>

  <?php if ($isRecommender): ?>
    <!-- Recommender Settings -->
    <a href="recommender_settings.php"
      class="nav-link <?= $current=='recommender_settings.php'?'active':'' ?>">
      <i class="fa-solid fa-user-shield"></i>
      <span>Settings</span>
    </a>
  <?php endif; ?>

</nav>

  <div class="sidebar-bottom">
    <div class="user-name">

      <div class="d-flex align-items-center justify-content-between mb-1">

        <span>Logged in as:</span>

        <button 
          type="button"
          class="btn btn-sm btn-light border-0 p-1"
          data-bs-toggle="modal"
          data-bs-target="#editProfileModal"
          title="Edit Profile"
        >
          <i class="fa-solid fa-pen-to-square"></i>
        </button>

      </div>

      <strong class="<?= $roleClass ?>">
          <?= htmlspecialchars(strtoupper($loggedInName), ENT_QUOTES, 'UTF-8') ?>

          <?php if ($role === 'admin'): ?>
              <i class="fas fa-star text-warning ms-1"></i>
          <?php endif; ?>
      </strong>

      <br>

      <span class="user-designation">
        <?= htmlspecialchars($designation, ENT_QUOTES, 'UTF-8') ?>
      </span>

    </div>

    <button type="button" id="logoutBtn" class="nav-link logout mt-2">

      <i class="fa-solid fa-arrow-right-from-bracket"></i>
      <span>Logout</span>
    </button>

  </div>
</aside>

<?php

$profileData = [];

$stmtProfile = $conn->prepare("
    SELECT 
        first_name,
        middle_name,
        last_name,
        designation
    FROM users
    WHERE id = ?
");

$stmtProfile->bind_param("i", $userId);
$stmtProfile->execute();

$profileData = $stmtProfile->get_result()->fetch_assoc();

$stmtProfile->close();

?>

<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <form action="update_profile.php" method="POST">

        <div class="modal-header">
          <h5 class="modal-title">
            <i class="fa-solid fa-user-pen me-2"></i>
            Edit Profile
          </h5>

          <button 
            type="button" 
            class="btn-close" 
            data-bs-dismiss="modal"
          ></button>
        </div>

        <div class="modal-body">

          <!-- First Name -->
          <div class="mb-3">
            <label class="form-label">
              First Name
            </label>

            <input 
              type="text"
              name="first_name"
              class="form-control"
              value="<?= htmlspecialchars($profileData['first_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
              required
            >
          </div>

          <!-- Middle Name -->
          <div class="mb-3">
            <label class="form-label">
              Middle Name
            </label>

            <input 
              type="text"
              name="middle_name"
              class="form-control"
              value="<?= htmlspecialchars($profileData['middle_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            >
          </div>

          <!-- Last Name -->
          <div class="mb-3">
            <label class="form-label">
              Last Name
            </label>

            <input 
              type="text"
              name="last_name"
              class="form-control"
              value="<?= htmlspecialchars($profileData['last_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
              required
            >
          </div>

          <!-- Designation -->
          <div class="mb-3">
            <label class="form-label">
              Designation
            </label>

            <input 
              type="text"
              name="designation"
              class="form-control"
              value="<?= htmlspecialchars($profileData['designation'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            >
          </div>

        </div>

        <div class="modal-footer">

          <button 
            type="button" 
            class="btn btn-secondary"
            data-bs-dismiss="modal"
          >
            Cancel
          </button>

          <button 
            type="submit" 
            class="btn btn-primary"
          >
            <i class="fa-solid fa-save me-1"></i>
            Save Changes
          </button>

        </div>

      </form>

    </div>
  </div>
</div>

