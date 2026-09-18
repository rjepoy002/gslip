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
$role     = $_SESSION['role'] ?? '';
$department = $_SESSION['department_id'];
$area = $_SESSION['area'];

$isAdmin        = ($role === 'admin');
$isRecommender  = !empty($_SESSION['is_recommender']);
$isApprover     = !empty($_SESSION['is_approver']);
$isPrivateApprover     = !empty($_SESSION['is_private_approver']);

/* =========================================================
   FETCH PENDING GAS SLIPS (FIXED)
========================================================= */
$conn->begin_transaction();

if($isRecommender){

  // COUNT RECOMMENDED GAS SLIPS FOR RECOMMENDER
  $countStmt = $conn->prepare("
      SELECT COUNT(*) AS total
      FROM gas_slips gs
      WHERE gs.recommended_by = ?
      AND gs.status = 'recommended'
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
  LEFT JOIN users u ON u.id = gs.user_id

  WHERE gs.recommended_by = ?
  AND gs.status = 'recommended'

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
  
  // COUNT RECOMMENDED GAS SLIPS FOR APPROVER
  $countStmt = $conn->prepare("
      SELECT COUNT(DISTINCT gs.id) AS total

      FROM gas_slips gs
      LEFT JOIN users u ON u.id = gs.user_id

      WHERE gs.recommended_by = ?
      AND gs.status = 'recommended'
  ");

  $countStmt->bind_param('i', $userId);
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
  LEFT JOIN users u ON u.id = gs.user_id

  WHERE gs.recommended_by = ?
  AND gs.status = 'recommended'

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
}else{

  // COUNT RECOMMENDED GAS SLIPS FOR REGULAR USER
  $countStmt = $conn->prepare("
      SELECT COUNT(*) AS total
      FROM gas_slips
      WHERE user_id = ?
      AND status = 'recommended'
  ");

  $countStmt->bind_param('i', $userId);
  $countStmt->execute();

  $totalRecords = $countStmt
      ->get_result()
      ->fetch_assoc()['total'];

  // main query for regular users
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
  AND gs.status = 'recommended'

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

    <?php include 'includes/dark-mode-preload.php'; ?>
    
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
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
            <div class="page-header mb-2">
              <h1 class="mb-1">Recommended Gas Slips</h1>
              <p class="page-subtitle mb-1">
                  Overview of gas slip requests endorsed and forwarded for approval
              </p>
            </div>

            <!-- Recent Activity -->
            <table class="styled-table excel-table">
                <thead>
                    <tr class="group-header">
                      <th>No.</th>
                      <th>Gas Slip No.</th>
                      <th>Date Requested</th>
                      <th>Requested By</th>
                      <th>Purpose</th>
                      <th>Route</th>
                      <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($result->num_rows > 0): ?>
                  <?php $no = 1; ?>
                  <?php while ($row = $result->fetch_assoc()): ?>

                      <tr class="gas-slip-row"
                          data-id="<?= htmlspecialchars($row['gs_id']); ?>"
                          tabindex="0">

                      <td><?= $no++; ?></td>

                      <td>
                        <strong><?= htmlspecialchars($row['gas_slip_id']); ?></strong>
                        <?php
                          switch ($row['status']) {
                            case 'pending':
                              echo '<span class="badge bg-info ms-1" title="Pending">P</span>';
                              break;
                            case 'recommended':
                              echo '<span class="badge bg-warning ms-1" title="Recommended">R</span>';
                              break;
                            case 'cancelled':
                              echo '<span class="badge bg-danger ms-1" title="Cancelled">C</span>';
                              break;
                            default:
                              echo '<span class="badge bg-light text-dark ms-1" title="Unknown">?</span>';
                          }
                        ?>
                      </td>


                      <td>
                        <?= date('Y-m-d', strtotime($row['date_issued'])); ?>
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
                        <?php
                          switch ($row['status']) {
                            case 'pending':
                              echo '<span>For Recommendation</span>';
                              break;
                            case 'recommended':
                              echo '<span>For Approval</span>';
                              break;
                            case 'cancelled':
                              echo '<span>Cancelled</span>';
                              break;
                            default:
                              echo '<span>Unknown</span>';
                          }
                        ?>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="7" class="text-center text-muted py-4">
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

<?php
$autoRefreshTime = 60000;
include 'includes/autorefresh.php';
?>

</body>
</html>
