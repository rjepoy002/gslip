<?php
session_start();
require_once 'includes/config.php';

$conn = getDBConnection();
// 🔐 AUTH GUARD
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['session_token']) ||
    !isset($_SESSION['role']) ||
    !isset($_SESSION['department_id'])
) {
    header('Location: index.php');
    exit;
}

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];
$department = $_SESSION['department_id'];

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
    u.id,
    u.department_id,

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

  WHERE u.department_id = ?
    AND (
      ? IN ('recommender', 'approver')
      OR u.id = ?
    )
    AND (
      ( ? = 'recommender' AND gs.status = 'pending' )
      OR
      ( ? = 'approver' AND gs.status = 'recommended' )
      OR
      ( ? NOT IN ('recommender', 'approver')
        AND gs.status IN ('pending', 'recommended')
      )
    )

  GROUP BY gs.id
  ORDER BY gs.gas_slip_id DESC
");

$stmt->bind_param(
  'isisss',
  $department,
  $role,
  $userId,
  $role,
  $role,
  $role
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
              <h1 class="mb-1">Pending Gas Slips</h1>
              <p class="page-subtitle mb-1">
                  Overview of gas slip requests awaiting action
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
                              echo '<span class="badge bg-warning text-dark ms-1" title="For Recommendation">R</span>';
                              break;
                            case 'recommended':
                              echo '<span class="badge bg-info text-dark ms-1" title="For Apprxoval">A</span>';
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

<!-- E-SIGNATURE UPLOAD MODAL -->
<div class="modal fade" id="esignModal" tabindex="-1">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">E-Signature</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-3">
          <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#drawTab">
              Draw
            </button>
          </li>
          <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#uploadTab">
              Upload
            </button>
          </li>
        </ul>

        <div class="tab-content">

          <!-- DRAW TAB -->
          <div class="tab-pane fade show active" id="drawTab">
            <canvas
              id="signaturePad"
              width="450"
              height="200"
              style="border:1px dashed #cbd5e1; width:100%;"
            ></canvas>

            <div class="d-flex justify-content-between mt-2">
              <button class="btn btn-sm btn-outline-secondary" id="clearSign">
                Clear
              </button>
            </div>
          </div>

          <!-- UPLOAD TAB -->
          <div class="tab-pane fade" id="uploadTab">
            <input
              type="file"
              class="form-control"
              id="esignFile"
              accept="image/png,image/jpeg"
            >
          </div>

        </div>

        <small class="text-muted d-block mt-2">
          Draw or upload your official signature.
        </small>

      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="saveEsignBtn">Save Signature</button>
      </div>

    </div>
  </div>
</div>



<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/sweetalert2.all.min.js"></script>

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
/* =========================================================
   GLOBAL HELPERS
========================================================= */
let signatureCanvas = null;
let signatureCtx = null;
let isDrawing = false;

/* =========================================================
   DOM READY
========================================================= */
document.addEventListener('DOMContentLoaded', function () {

  initSidebarToggle();
  initLogoutSweetAlert();
  initGasSlipRowModal();
  initRecommendHandler();
  initApproveHandler();
  initEsignModal();

});

/* =========================================================
   SIDEBAR TOGGLE
========================================================= */
function initSidebarToggle() {
  const sidebar = document.querySelector('.app-sidebar');
  const content = document.querySelector('.app-content');
  const toggleBtn = document.getElementById('sidebarToggle');

  if (!sidebar || !content || !toggleBtn) return;

  if (localStorage.getItem('sidebarCollapsed') === 'true') {
    sidebar.classList.add('collapsed');
    content.classList.add('sidebar-collapsed');
  }

  toggleBtn.addEventListener('click', () => {
    sidebar.classList.toggle('collapsed');
    content.classList.toggle('sidebar-collapsed');
    localStorage.setItem(
      'sidebarCollapsed',
      sidebar.classList.contains('collapsed')
    );
  });
}

/* =========================================================
   LOGOUT CONFIRMATION
========================================================= */
function initLogoutSweetAlert() {
  const logoutBtn = document.getElementById('logoutBtn');
  if (!logoutBtn) return;

  logoutBtn.addEventListener('click', e => {
    e.preventDefault();

    Swal.fire({
      title: 'Logout Confirmation',
      text: 'Are you sure you want to log out?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, logout',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#dc2626',
      cancelButtonColor: '#64748b',
      reverseButtons: true
    }).then(result => {
      if (result.isConfirmed) {
        window.location.href = 'logout.php';
      }
    });
  });
}

/* =========================================================
   GAS SLIP ROW → DETAILS MODAL
========================================================= */
function initGasSlipRowModal() {
  const modalEl = document.getElementById('gasSlipModal');
  const modalBody = document.getElementById('gasSlipModalBody');
  const modalTitle = document.getElementById('gasSlipModalTitle');

  if (!modalEl || !modalBody || !modalTitle) return;

  const gasSlipModal = new bootstrap.Modal(modalEl);

  document.querySelectorAll('.gas-slip-row').forEach(row => {

    row.style.cursor = 'pointer';
    row.setAttribute('tabindex', '0');

    const openModal = () => {
      const gasSlipId = row.dataset.id;
      if (!gasSlipId) return;

      modalTitle.innerHTML = 'Gas Slip Details';
      modalBody.innerHTML = `
        <div class="text-center py-5">
          <div class="spinner-border text-primary"></div>
          <div class="mt-2">Loading details…</div>
        </div>
      `;

      gasSlipModal.show();

      fetch(`view_gas_slip_modal.php?id=${gasSlipId}&mode=json`)
        .then(res => res.json())
        .then(data => {
          if (data.error) {
            modalBody.innerHTML = `<div class="text-danger text-center">${data.error}</div>`;
            return;
          }

          modalTitle.innerHTML = `
            Gas Slip No. <strong><u>${data.gas_slip_id}</u></strong>
            <span class="badge ${data.statusClass} ms-2">${data.statusLabel}</span>
          `;

          return fetch(`view_gas_slip_modal.php?id=${gasSlipId}&mode=body`);
        })
        .then(res => res?.text())
        .then(html => {
          if (html) modalBody.innerHTML = html;
        })
        .catch(() => {
          modalBody.innerHTML = `
            <div class="text-danger text-center py-4">
              Failed to load gas slip details.
            </div>
          `;
        });
    };

    row.addEventListener('click', openModal);
    row.addEventListener('keydown', e => {
      if (e.key === 'Enter') openModal();
    });
  });
}

/* =========================================================
   RECOMMEND BUTTON HANDLER
========================================================= */
function initRecommendHandler() {
  document.addEventListener('click', function (e) {

    const btn = e.target.closest('#recommendBtn');
    if (!btn) return;

    e.stopPropagation();

    const gasSlipId = btn.dataset.gasSlipId;
    if (!gasSlipId) {
      Swal.fire('Error', 'Missing gas slip ID', 'error');
      return;
    }

    fetch('recommend_gas_slip.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'id=' + encodeURIComponent(gasSlipId)
    })
    .then(res => res.json())
    .then(data => {

      if (!data.success) {
        Swal.fire({
          icon: 'warning',
          title: 'E-signature Required',
          text: data.message,
          confirmButtonText: 'Upload E-signature'
        }).then(() => {
          const modal = new bootstrap.Modal(document.getElementById('esignModal'));
          modal.show();
        });
        return;
      }

      Swal.fire({
        icon: 'success',
        title: 'Recommended',
        text: 'Gas slip successfully recommended.',
        timer: 1500,
        showConfirmButton: false
      }).then(() => location.reload());
    })
    .catch(() => {
      Swal.fire('Error', 'Request failed. Please try again.', 'error');
    });
  });
}

/* =========================================================
   APPROVE BUTTON HANDLER
========================================================= */
function initApproveHandler() {
  document.addEventListener('click', function (e) {

    const btn = e.target.closest('#approveBtn');
    if (!btn) return;

    e.stopPropagation();

    const gasSlipId = btn.dataset.gasSlipId;
    if (!gasSlipId) {
      Swal.fire('Error', 'Missing gas slip ID', 'error');
      return;
    }

    fetch('approve_gas_slip.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'id=' + encodeURIComponent(gasSlipId)
    })
    .then(res => res.json())
    .then(data => {

      if (!data.success) {
        Swal.fire({
          icon: 'warning',
          title: 'E-signature Required',
          text: data.message,
          confirmButtonText: 'Upload E-signature'
        }).then(() => {
          const modal = new bootstrap.Modal(document.getElementById('esignModal'));
          modal.show();
        });
        return;
      }

      Swal.fire({
        icon: 'success',
        title: 'Approved',
        text: 'Gas slip successfully approved.',
        timer: 1500,
        showConfirmButton: false
      }).then(() => location.reload());
    })
    .catch(() => {
      Swal.fire('Error', 'Request failed. Please try again.', 'error');
    });
  });
}


/* =========================================================
   E-SIGN MODAL + SIGNATURE PAD
========================================================= */
function initEsignModal() {
  const modalEl = document.getElementById('esignModal');
  if (!modalEl) return;

  modalEl.addEventListener('shown.bs.modal', initSignaturePad);

  document.getElementById('saveEsignBtn')?.addEventListener('click', saveEsign);
}

function initSignaturePad() {
  signatureCanvas = document.getElementById('signaturePad');
  if (!signatureCanvas) return;

  signatureCtx = signatureCanvas.getContext('2d');
  signatureCtx.lineWidth = 2;
  signatureCtx.lineCap = 'round';

  signatureCanvas.onmousedown = e => {
    isDrawing = true;
    signatureCtx.beginPath();
    signatureCtx.moveTo(e.offsetX, e.offsetY);
  };

  signatureCanvas.onmousemove = e => {
    if (!isDrawing) return;
    signatureCtx.lineTo(e.offsetX, e.offsetY);
    signatureCtx.stroke();
  };

  signatureCanvas.onmouseup = signatureCanvas.onmouseleave = () => {
    isDrawing = false;
  };

  document.getElementById('clearSign')?.addEventListener('click', () => {
    signatureCtx.clearRect(0, 0, signatureCanvas.width, signatureCanvas.height);
  });
}

/* =========================================================
   SAVE E-SIGN (UPLOAD OR DRAW)
========================================================= */
function saveEsign() {
  const fileInput = document.getElementById('esignFile');
  const formData = new FormData();

  if (fileInput && fileInput.files.length > 0) {
    formData.append('esign', fileInput.files[0]);
  } else {
    if (!signatureCanvas) {
      Swal.fire('Error', 'Signature pad not ready', 'error');
      return;
    }

    const blank = document.createElement('canvas');
    blank.width = signatureCanvas.width;
    blank.height = signatureCanvas.height;

    if (signatureCanvas.toDataURL() === blank.toDataURL()) {
      Swal.fire('Warning', 'Please draw your signature first.', 'warning');
      return;
    }

    formData.append('drawn_esign', signatureCanvas.toDataURL('image/png'));
  }

  fetch('upload_esign.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (!data.success) {
      Swal.fire('Error', data.message, 'error');
      return;
    }

    Swal.fire({
      icon: 'success',
      title: 'Saved',
      text: 'E-signature saved successfully.',
      timer: 1500,
      showConfirmButton: false
    }).then(() => location.reload());
  })
  .catch(() => {
    Swal.fire('Error', 'Upload failed. Please try again.', 'error');
  });
}
</script>





</body>
</html>
