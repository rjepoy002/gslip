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

/* =========================================================
   COUNT GAS SLIPS (FIXED)
========================================================= */

$conn->begin_transaction();

$countStmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM gas_slips gs
    LEFT JOIN users u ON u.id = gs.user_id
    WHERE gs.status = 'draft'
    AND u.id = ?
");

$countStmt->bind_param('i', $userId);
$countStmt->execute();

$totalRecords = $countStmt
    ->get_result()
    ->fetch_assoc()['total'];

$totalPages = max(1, ceil($totalRecords / $recordsPerPage));

/* =========================================================
   FETCH DRAFT GAS SLIPS (FIXED)
========================================================= */
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

  WHERE gs.status = 'draft'
  AND u.id = ?

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
              <h1 class="mb-1">Draft Gas Slips</h1>
              <p class="page-subtitle mb-1">
                  Withdrawn pending gas slips that can be edited and resubmitted.
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
                            case 'draft':
                              echo '<span class="badge bg-secondary ms-1" title="Draft">D</span>';
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

<script src="assets/js/jquery.min.js"></script>
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

</body>
</html>

<script>
// document.addEventListener('click', function (e) {
//   const btn = e.target.closest('.btn-cancel-draft');
//   if (!btn) return;

//   const id = btn.getAttribute('data-id');

//   const modalBody = document.getElementById('gasSlipModalBody');
//   const modalTitle = document.getElementById('gasSlipModalTitle');

//   // change title
//   modalTitle.innerHTML = 'Confirm Cancel';

//   // replace modal content
//   modalBody.innerHTML = `
//     <div class="text-center py-3">
//       <p class="mb-4">Are you sure you want to cancel this draft?</p>

//       <form method="POST" action="cancel_gas_slip.php">
//         <input type="hidden" name="id" value="${id}">
        
//         <button type="submit" class="btn btn-danger me-2">
//           <i class="fas fa-times"></i> Yes, Cancel
//         </button>

//         <button type="button" class="btn btn-secondary" id="btnBackToDetails">
//           Back
//         </button>
//       </form>
//     </div>
//   `;
// });

document.addEventListener('click', function (e) {

const btn = e.target.closest('.btn-cancel-draft');
if (!btn) return;

const id = btn.dataset.id;

Swal.fire({
  title: 'Cancel Draft?',
  text: 'This draft gas slip will be permanently deleted.',
  icon: 'warning',
  showCancelButton: true,
  confirmButtonText: 'Yes, Cancel Draft',
  cancelButtonText: 'Keep Draft',
  confirmButtonColor: '#dc3545'
}).then((result) => {

  if (!result.isConfirmed) return;

  const form = document.createElement('form');
  form.method = 'POST';
  form.action = 'cancel_gas_slip.php';

  const input = document.createElement('input');
  input.type = 'hidden';
  input.name = 'id';
  input.value = id;

  form.appendChild(input);
  document.body.appendChild(form);
  form.submit();

});

});

// document.addEventListener('click', function (e) {
//   if (e.target.id === 'btnBackToDetails') {
//     const id = document.querySelector('[name="id"]').value;

//     const modalBody = document.getElementById('gasSlipModalBody');
//     const modalTitle = document.getElementById('gasSlipModalTitle');

//     modalTitle.innerHTML = 'Gas Slip Details';

//     modalBody.innerHTML = `
//       <div class="text-center py-5">
//         <div class="spinner-border text-primary"></div>
//       </div>
//     `;

//     fetch(`view_gas_slip_modal.php?id=${id}&mode=body`)
//       .then(r => r.text())
//       .then(html => modalBody.innerHTML = html);
//   }
// });

</script>
