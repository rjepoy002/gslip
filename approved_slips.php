<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/pagination_setup.php';

$conn = getDBConnection();
// 🔐 AUTH GUARD
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['session_token']) ||
    !isset($_SESSION['role']) ||
    !isset($_SESSION['department_id']) ||
    !isset($_SESSION['area'])
) {
    header('Location: index.php');
    exit;
}

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];
$department = $_SESSION['department_id'];
$area = $_SESSION['area'];
$hasDates = !empty($_SESSION['date_from']) || !empty($_SESSION['date_to']);

$isAdmin        = ($role === 'admin');
$isRecommender  = !empty($_SESSION['is_recommender']);
$isApprover     = !empty($_SESSION['is_approver']);
$isPrivateApprover     = !empty($_SESSION['is_private_approver']);

$filtersActive = !empty($_SESSION['show_printed']);
// $showApproved = !empty($_SESSION['show_approved']);
$showPrinted  = !empty($_SESSION['show_printed']);
$dateFrom = !empty($_SESSION['date_from']) ? $_SESSION['date_from'] : null;
$dateTo   = !empty($_SESSION['date_to'])   ? $_SESSION['date_to']   : null;

/* 🔎 DEBUG — TEMPORARY */
// var_dump($dateFrom, $dateTo);
// exit;
// var_dump($_SESSION['role'], $_SESSION['user_id'], $_SESSION['area'], $_SESSION['department_id']);
// exit;


/* =========================================================
   FILTER CONDITIONS
========================================================= */

$whereExtra = "";

/* Printed filter */
if (!$showPrinted) {
    $whereExtra .= " AND gs.printed_at IS NULL ";
}

/* Date filters */
if (!empty($dateFrom)) {
    $whereExtra .= " AND DATE(gs.approved_at) >= '{$conn->real_escape_string($dateFrom)}' ";
}

if (!empty($dateTo)) {
    $whereExtra .= " AND DATE(gs.approved_at) <= '{$conn->real_escape_string($dateTo)}' ";
}

$conn->begin_transaction();

if($isRecommender){

  // COUNT APPROVED GAS SLIPS FOR RECOMMENDER
  $countStmt = $conn->prepare("
      SELECT COUNT(*) AS total
      FROM gas_slips gs
      WHERE gs.recommended_by = ?
      AND gs.status = 'approved'
      {$whereExtra}
  ");

  $countStmt->bind_param('i', $userId);
  $countStmt->execute();

  $totalRecords = $countStmt
      ->get_result()
      ->fetch_assoc()['total'];

  // main query for recommenders
  $stmt = $conn->prepare("

  SELECT 
      gs.id AS gs_id,
      gs.gas_slip_id,
      gs.date_issued,
      gs.requested_by,
      gs.purpose,
      gs.status,
      gs.printed_at,
      gs.approved_at,
      CONCAT(
        MIN(r.origin),
        ' → ',
        GROUP_CONCAT(
          r.destination
          ORDER BY gsr.id
          SEPARATOR ' → '
        )
      ) AS route_path
      
  FROM gas_slips gs 
  LEFT JOIN gas_slip_routes gsr ON gsr.gas_slip_id = gs.id
  LEFT JOIN routes r ON r.id = gsr.route_id

  WHERE gs.recommended_by = ?
  AND gs.status = 'approved'
  {$whereExtra}

  GROUP BY gs.id
  ORDER BY gs.date_issued DESC
  LIMIT ?, ?;

  ");

  $stmt->bind_param(
      'iii',
      $userId,
      $offset,
      $recordsPerPage
  );
}elseif($isApprover){
  
  // COUNT APPROVED GAS SLIPS FOR APPROVER
    $countStmt = $conn->prepare("
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

            AND (

                (
                    v.ownership = 'private'

                    AND EXISTS (
                        SELECT 1
                        FROM approval_global_settings ags
                        WHERE ags.private_vehicle_approver_user_id = a.id
                    )

                    OR gs.recommended_by = ?

                    OR gs.approved_by = ?
                )

                OR

                (
                    v.ownership = 'coop-owned'

                    AND NOT EXISTS (
                        SELECT 1
                        FROM approval_global_settings ags
                        WHERE ags.private_vehicle_approver_user_id = a.id
                    )

                    AND EXISTS (
                        SELECT 1
                        FROM department_areas da
                        WHERE da.department_id = a.department_id
                        AND da.area_id = u.area_id
                    )
                )

            )

            -- AND gs.approved_by = ?
            -- AND gs.printed_at IS NULL
            {$whereExtra}
    ");

    $countStmt->bind_param(
        'iii',
        $userId, // a.id
        $userId, // private recommended_by
        $userId  // private approved_by
    );
    $countStmt->execute();

    $totalRecords = $countStmt
        ->get_result()
        ->fetch_assoc()['total'];

  // main query for approvers
  $stmt = $conn->prepare("

  SELECT 
      gs.id AS gs_id,
      gs.gas_slip_id,
      gs.date_issued,
      gs.requested_by,
      gs.purpose,
      gs.status,
      gs.printed_at,
      gs.approved_at,

      CONCAT(
          MIN(r.origin),
          ' → ',
          GROUP_CONCAT(
              r.destination
              ORDER BY gsr.id
              SEPARATOR ' → '
          )
      ) AS route_path
        
  FROM gas_slips gs 

  LEFT JOIN users u
      ON u.id = gs.user_id

  LEFT JOIN users a
      ON a.id = ?

  LEFT JOIN vehicles v
      ON v.id = gs.vehicle_id

  LEFT JOIN gas_slip_routes gsr
      ON gsr.gas_slip_id = gs.id

  LEFT JOIN routes r
      ON r.id = gsr.route_id

  WHERE
      gs.status = 'approved'

      AND (

          /* PRIVATE VEHICLES */
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

          /* COOP-OWNED VEHICLES */
          (
              v.ownership = 'coop-owned'

              AND EXISTS (
                  SELECT 1
                  FROM department_areas da
                  WHERE da.department_id = a.department_id
                  AND da.area_id = u.area_id
              )

            /*  AND (
                  gs.recommended_by = ?
                  OR gs.approved_by = ?
              ) */
          )
      )

      -- AND gs.printed_at IS NULL
      {$whereExtra}

  GROUP BY gs.id
  ORDER BY gs.date_issued DESC
  LIMIT ?, ?

  ");

  $stmt->bind_param(
      "iiiii",
      $userId, // a.id
      $userId, // private recommended_by
      $userId, // private approved_by
      //$userId, // coop recommended_by
      //$userId, // coop approved_by
      $offset,
      $recordsPerPage
  );

}else{

  // COUNT APPROVED GAS SLIPS FOR NORMAL USERS
  $countStmt = $conn->prepare("
      SELECT COUNT(*) AS total

      FROM gas_slips gs

      WHERE gs.user_id = ?
      AND gs.status = 'approved'
      {$whereExtra}
  ");

  $countStmt->bind_param('i', $userId);
  $countStmt->execute();

  $totalRecords = $countStmt
      ->get_result()
      ->fetch_assoc()['total'];

  // main query for normal users
  $stmt = $conn->prepare("

  SELECT 
      gs.id AS gs_id,
      gs.gas_slip_id,
      gs.date_issued,
      gs.requested_by,
      gs.purpose,
      gs.status,
      gs.printed_at,
      gs.approved_at,
      CONCAT(
        MIN(r.origin),
        ' → ',
        GROUP_CONCAT(
          r.destination
          ORDER BY gsr.id
          SEPARATOR ' → '
        )
      ) AS route_path
      
  FROM gas_slips gs 
  LEFT JOIN gas_slip_routes gsr ON gsr.gas_slip_id = gs.id
  LEFT JOIN routes r ON r.id = gsr.route_id

  WHERE gs.user_id = ?
  AND gs.status = 'approved'
  {$whereExtra}

  GROUP BY gs.id
  ORDER BY gs.date_issued DESC
  LIMIT ?, ?;

  ");

  $stmt->bind_param(
      'iii',
      $userId,
      $offset,
      $recordsPerPage
  );
}

$stmt->execute();
$result = $stmt->get_result();
$totalPages = max(1, ceil($totalRecords / $recordsPerPage));

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard | e-GSlip</title>

    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/icons/bootstrap-icons.css">
    <script src="assets/js/sweetalert2.all.min.js"></script>
</head>

<body>

<?php include 'includes/sidebar.php'; ?>

<div class="app-content">

    <!-- Main Page Content -->
    <main class="main-content">
        <!-- Temporary Empty State -->
        <div class="panel-container">
            <!-- Page Header -->
            <div class="page-header mb-4">
              <h1 class="mb-1">Approved Gas Slips</h1>
              <p class="page-subtitle mb-1">
                  Overview of gas slip requests that have been approved
              </p>
            </div>



            <div class="d-flex align-items-center mb-2">
              <button
                class="btn btn-primary btn-sm"
                id="printSelected"
              >
                <i class="bi bi-printer"></i> Print Selected
              </button>

              <div class="ms-auto">
                <button
                  type="button"
                  class="btn btn-sm btn-settings"
                  data-bs-toggle="modal"
                  data-bs-target="#filterModal"
                  title="Settings/Filters"
                >
                  <i class="fas fa-cog fa-lg"></i>
                </button>
              </div>
            </div>

            <!-- Recent Activity -->
            <table class="styled-table excel-table">
                <thead>
                    <tr class="group-header">
                      <th class="no-modal check-all-cell">
                        <input type="checkbox" id="checkAll">
                      </th>
                      <th>No.</th>
                      <th>Gas Slip No.</th>
                      <th>Date Approved</th>
                      <th>Requested By</th>
                      <th>Purpose</th>
                      <th>Route</th>
                      <th>Date Printed</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($result->num_rows > 0): ?>
                  <?php $no = 1; ?>
                  <?php while ($row = $result->fetch_assoc()): ?>

                      <tr class="gas-slip-row"
                          data-id="<?= htmlspecialchars($row['gs_id']); ?>"
                          tabindex="0">
                      
                      <td class="no-modal check-cell">
                        <?php if ($row['status'] === 'approved'): ?>
                          <input
                            type="checkbox"
                            class="print-check"
                            value="<?= htmlspecialchars($row['gs_id']); ?>"
                          >
                        <?php endif; ?>
                      </td>

                      <td><?= $no++; ?> </td>

                      <td>
                        <strong><?= htmlspecialchars($row['gas_slip_id']); ?></strong>
                        <?php
                        if (!empty($row['printed_at'])) {
                          echo '<span class="badge bg-primary" title="Approved"><i class="bi bi-check-lg"></i></span>';
                        }else{
                          switch ($row['status']) {
                            case 'recommended':
                              echo '<span class="badge bg-warning ms-1" title="Recommended">R</span>';
                              break;
                            case 'approved':
                              echo '<span class="badge bg-success ms-1" title="Approved">A</span>';
                              break;
                            case 'cancelled':
                              echo '<span class="badge bg-danger ms-1" title="Cancelled">C</span>';
                              break;
                            default:
                              echo '<span class="badge bg-light ms-1" title="Unknown">?</span>';
                          }
                        }
                        ?>
                      </td>

                      <td>
                        <?= date('M d, Y · h:i A', strtotime($row['approved_at'])); ?>
                      </td>

                      <td>
                        <?= htmlspecialchars($row['requested_by']); ?>
                      </td>

                      <td class="text-truncate" style="max-width: 240px;">
                        <?= htmlspecialchars($row['purpose']); ?>
                      </td>

                      <td>
                        <?= htmlspecialchars($row['route_path']) ?>
                      </td>
                      <td>
                        <?= !empty($row['printed_at'])
                        ? date('M d, Y · h:i A', strtotime($row['printed_at']))
                        : '<span class="text-muted">Not printed</span>'; ?>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                      No pending gas slips found
                    </td>
                  </tr>
                <?php endif; ?>
                </tbody>

            </table>
            <?php include 'includes/pagination.php'; ?>
        </div>

    </main>

</div>

<!-- Gas Slip Details Modal -->
<div class="modal fade" id="gasSlipModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg  modal-dialog-scrollable">
    <div class="modal-content">

      <!-- MODAL HEADER (STATIC) -->
      <div class="modal-header">
        <h5 class="modal-title d-flex align-items-center gap-2 fs-6"
            id="gasSlipModalTitle">
          Gas Slip Details
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <!-- MODAL BODY (DYNAMIC CONTENT GOES HERE) -->
      <div class="modal-body">
        <div id="gasSlipModalBody" style="font-size:14px;">
          <div class="spinner-border text-primary"></div>
          <div class="mt-2">Loading details…</div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- Filter Modal -->
<div class="modal fade" id="filterModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm  modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header py-2">
        <h6 class="modal-title mb-0">
          <i class="fas fa-filter me-1"></i> Display Filters
        </h6>
        <button
          type="button"
          class="btn-close"
          data-bs-dismiss="modal"
          aria-label="Close"
        ></button>
      </div>

      <form method="post" action="save_filters.php">
        <div class="modal-body">

          <div class="form-check">
            <input
              class="form-check-input"
              type="checkbox"
              id="showPrinted"
              name="show_printed"
              <?= !empty($_SESSION['show_printed']) ? 'checked' : '' ?>
            >
            <label class="form-label" for="showPrinted">
              Include printed gas slips
            </label>
          </div>

          <div class="mt-3">

          
          <label class="form-label mb-1">Approved date range</label>

          <div class="py-2 d-flex align-items-center">
            <input
              type="date"
              class="form-control form-control-sm date-filter"
              name="date_from"
              placeholder="From"
              value="<?= $_SESSION['date_from'] ?? '' ?>"
            >

            <span class="text-muted px-1">:</span>
            
            <input
              type="date"
              class="form-control form-control-sm date-filter"
              name="date_to"
              placeholder="To"
              value="<?= $_SESSION['date_to'] ?? '' ?>"
            >

            <button
              type="submit"
              name="clear_dates"
              value="1"
              class="btn btn-link btn-sm text-danger me-auto"
              <?= !$hasDates ? 'disabled' : '' ?>
            >
              <i class="fas fa-rotate-left"></i>
            </button>
          </div>



        </div>


        </div>

        <div class="modal-footer py-2">

          <button type="submit" class="btn btn-primary btn-sm">
            Apply
          </button>
          <button
            type="button"
            class="btn btn-secondary btn-sm"
            data-bs-dismiss="modal"
          >
            Cancel
          </button>
        </div>
      </form>

    </div>
  </div>
</div>


<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/sweetalert2.all.min.js"></script>

<script src="assets/js/app-ui.js"></script>
<script src="assets/js/gas-slip.js"></script>
<script src="assets/js/notifications.js"></script>

<?php if (!empty($_SESSION['swal_success'])): ?>
<script>
Swal.fire({
  icon: 'success',
  title: 'Success',
  text: '<?= $_SESSION['swal_success']; ?>',
  timer: 2000,
  showConfirmButton: false
});
</script>
<?php unset($_SESSION['swal_success']); endif; ?>

<script>

document.addEventListener('click', function (e) {
  if (e.target.matches('input[type="date"]')) {
    if (typeof e.target.showPicker === 'function') {
      e.target.showPicker();
    }
  }
});

document.addEventListener('change', function (e) {
  if (e.target.name === 'date_from') {
    const to = document.querySelector('input[name="date_to"]');
    if (to && !to.value) {
      to.value = new Date().toISOString().slice(0, 10);
    }
  }
});

document.getElementById('printSelected').addEventListener('click', function () {
  const checked = document.querySelectorAll('.print-check:checked');

  if (checked.length === 0) {
    Swal.fire({
      icon: 'info',
      title: 'Selection Required',
      text: 'Please select at least one gas slip before continuing.',
      confirmButtonText: 'Got it',
      confirmButtonColor: '#0d6efd' // Bootstrap primary
    });
    return;
  }


  const ids = Array.from(checked).map(cb => cb.value);

  window.location.href =
    'print_gas_slip.php?ids=' + encodeURIComponent(ids.join(','));
});

// Check all toggle
document.getElementById('checkAll').addEventListener('change', function () {
  document.querySelectorAll('.print-check').forEach(cb => {
    cb.checked = this.checked;
  });
});
</script>

<?php
$autoRefreshTime = 60000;
include 'includes/autorefresh.php';
?>

</body>
</html>
