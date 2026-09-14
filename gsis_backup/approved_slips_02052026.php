<?php
session_start();
require_once 'includes/config.php';

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


/* =========================================================
   FETCH PENDING GAS SLIPS (FIXED)
========================================================= */
$conn->begin_transaction();

$stmt = $conn->prepare("
  SELECT
    gs.id AS gs_id,
    gs.gas_slip_id,
    gs.date_issued,
    gs.requested_by,
    gs.purpose,
    gs.status,
    gs.approved_by,
    gs.approved_at,
    u.id AS user_id,
    u.department_id,
    u.area,
    u.approval_scope,

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
  LEFT JOIN users a ON a.id = ?
  LEFT JOIN vehicles v ON v.id = gs.vehicle_id

  WHERE
  (
    /* ================= USER ================= */
    (
      ? = 'user'
      AND u.id = ?
      AND gs.status IN ('approved', 'printed')
    )

    /* ============== RECOMMENDER ============== */
    OR
    (
      ? = 'recommender'
      AND gs.recommended_by = ?         -- 👈 OWN slips
      AND a.department_id = ?     -- 👈 DEPARTMENT slips
    )

    /* ================= APPROVER =============== */
    OR
    (
      ? = 'approver'
      AND u.area = ?
      AND gs.approved_by = ?
    )
  )

  GROUP BY gs.id
  ORDER BY gs.approved_at DESC;


");

$stmt->bind_param(
  'isisiissi',

  $userId,      // approver user_id

  $role,        // user
  $userId,

  $role,        // recommender
  $userId,      // own slips
  $department,// department slips

  $role,        // approver
  $area,         // area (string)
  $userId         // approved_by
);


$stmt->execute();
$result = $stmt->get_result();



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

            <button
              class="btn btn-primary btn-sm"
              id="printSelected"
            >
              <i class="bi bi-printer"></i> Print Selected
            </button>

            <!-- Recent Activity -->
            <table class="styled-table excel-table">
                <thead>
                    <tr class="group-header">
                      <th class="no-modal check-all-cell">
                        <input type="checkbox" id="checkAll">
                      </th>
                      <th>No.</th>
                      <th>Gas Slip No.</th>
                      <th>Date Requested</th>
                      <th>Date Approved</th>
                      <th>Requested By</th>
                      <th>Purpose</th>
                      <th>Route</th>
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
                        <input
                          type="checkbox"
                          class="print-check"
                          value="<?= $row['gs_id'] ?>"
                        >
                      </td>

                      <td><?= $no++; ?> </td>

                      <td>
                        <strong><?= htmlspecialchars($row['gas_slip_id']); ?></strong>
                        <?php
                          switch ($row['status']) {
                            case 'recommended':
                              echo '<span class="badge bg-info text-dark ms-1" title="For Apprxoval">A</span>';
                              break;
                            case 'approved':
                              echo '<span class="badge bg-success" title="Approved"><i class="bi bi-check-lg"></i></span>';
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
                        <?= date('Y-m-d', strtotime($row['approved_at'])); ?>
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
document.getElementById('printSelected').addEventListener('click', function () {
  const checked = document.querySelectorAll('.print-check:checked');

  if (checked.length === 0) {
    alert('Please select at least one gas slip.');
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

</body>
</html>
