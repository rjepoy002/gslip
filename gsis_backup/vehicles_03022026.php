<?php
session_start();
require_once 'includes/config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Add or update vehicle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plate_no'])) {

    header('Content-Type: application/json');

    $plate_no = trim($_POST['plate_no'] ?? '');
    $brand    = trim($_POST['brand'] ?? '');
    $model    = trim($_POST['model'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $ownership = trim($_POST['ownership'] ?? '');
    $status    = trim($_POST['status'] ?? '');
    $remarks   = trim($_POST['remarks'] ?? '');

    $km_per_liter = isset($_POST['km_per_liter']) && $_POST['km_per_liter'] !== ''
        ? (float)$_POST['km_per_liter']
        : null;

    $idling_rate = ($category === 'trucks' && !empty($_POST['idling_rate']))
        ? (float)$_POST['idling_rate']
        : null;

    /* ================= VALIDATION ================= */

    if (!$plate_no || !$brand || !$model || !$category || !$ownership || !$status) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Required fields are missing.'
        ]);
        exit;
    }

    /* ================= DUPLICATE CHECK ================= */

    $check = $conn->prepare("SELECT id FROM vehicles WHERE plate_no = ?");
    $check->bind_param("s", $plate_no);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Vehicle with this plate number already exists.'
        ]);
        exit;
    }

    /* ================= INSERT ================= */
    $stmt = $conn->prepare("
        INSERT INTO vehicles 
        (plate_no, brand, model, km_per_liter, idling_rate, category, ownership, status, remarks)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        echo json_encode([
            'status' => 'error',
            'message' => $conn->error
        ]);
        exit;
    }

    $stmt->bind_param(
        "sssssssss",
        $plate_no,
        $brand,
        $model,
        $km_per_liter,
        $idling_rate,
        $category,
        $ownership,
        $status,
        $remarks
    );

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }

    exit; // VERY IMPORTANT
}


/* 🔎 DEBUG — TEMPORARY */
// var_dump($dateFrom, $dateTo);
// exit;
// var_dump($_SESSION['role'], $_SESSION['user_id'], $_SESSION['area'], $_SESSION['department_id']);
// exit;

/* =========================================================
  FETCH VEHICLE LIST
========================================================= */
$conn->begin_transaction();

$stmt = $conn->prepare("
  SELECT
    id, 
    plate_no, 
    brand, 
    model, 
    km_per_liter, 
    idling_rate, 
    category, 
    ownership, 
    status, 
    remarks

  FROM vehicles
  ORDER BY id DESC

");

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
<?php include 'includes/km_modal.php'; ?>
<?php include 'includes/add_vehicle_modal.php'; ?>

<div class="app-content">

    <!-- Main Page Content -->
    <main class="main-content">
        <!-- Temporary Empty State -->
        <div class="panel-container">
            <!-- Page Header -->
            <div class="page-header mb-4">
              <h1 class="mb-1">Vehicles Management</h1>
              <p class="page-subtitle mb-1">
                  View and maintain all active and inactive vehicles available for gas slip transactions.
              </p>
            </div>

            <button class="btn btn-primary btn-sm" 
              data-bs-toggle="modal" 
              data-bs-target="#addVehicleModal">
                + Add Vehicle
            </button>

            <!-- Recent Activity -->
            <table class="styled-table excel-table" id="vehicleTable">
                <thead>
                    <tr class="group-header">
                      <th>No.</th>
                      <th>Plate Number</th>
                      <th>Brand</th>
                      <th>Model</th>
                      <th>KM/L</th>
                      <th>Idl_rt</th>
                      <th>Category</th>
                      <th>Ownership</th>
                      <th>Status</th>
                      <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($result->num_rows > 0): ?>
                  <?php $no = 1; ?>
                  <?php while ($row = $result->fetch_assoc()): ?>

                    <tr class="vehicle-row"
                        data-id="<?= $row['id'] ?>"
                        data-plate="<?= htmlspecialchars($row['plate_no']) ?>"
                        data-brand="<?= htmlspecialchars($row['brand']) ?>"
                        data-model="<?= htmlspecialchars($row['model']) ?>"
                        data-kmpl="<?= htmlspecialchars($row['km_per_liter']) ?>"
                        data-idling="<?= htmlspecialchars($row['idling_rate']) ?>"
                        data-category="<?= htmlspecialchars($row['category']) ?>"
                        data-ownership="<?= htmlspecialchars($row['ownership']) ?>"
                        data-status="<?= htmlspecialchars($row['status']) ?>"
                        data-remarks="<?= htmlspecialchars($row['remarks']) ?>">
                      <td><?= $no++; ?> </td>
                      <td><?= htmlspecialchars($row['plate_no']) ?></td>
                      <td><?= htmlspecialchars($row['brand']) ?></td>
                      <td><?= htmlspecialchars($row['model']) ?></td>
                      <td><?= htmlspecialchars($row['km_per_liter']) ?></td>
                      <td>
                        <?= $row['idling_rate'] !== null ? htmlspecialchars($row['idling_rate']) : '' ?>
                      </td>
                      <td><?= htmlspecialchars($row['category']) ?></td>
                      <td><?= htmlspecialchars($row['ownership']) ?></td>
                      <td><?= htmlspecialchars($row['status']) ?></td>
                      <td><?= htmlspecialchars($row['remarks']) ?></td>
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

<script src="assets/js/jquery.min.js"></script>
<script src="assets/js/jquery.dataTables.min.js"></script>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/sweetalert2.all.min.js"></script>

<script src="assets/js/app-ui.js"></script>

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
$(function () {

  /* =========================================
     DATATABLE INITIALIZATION
  ========================================= */

  if ($.fn.DataTable.isDataTable('#vehicleTable')) {
    $('#vehicleTable').DataTable().destroy();
  }

  const table = $('#vehicleTable').DataTable({
    paging: true,
    searching: true,
    ordering: true,
    responsive: true,
    pageLength: 15,
    pagingType: "full_numbers",
    lengthMenu: [[15, 25, 50, 75, 100], [15, 25, 50, 75, 100]],
    language: {
      lengthMenu: 'Rows per page: _MENU_',
      search: '',
      searchPlaceholder: 'Search...',
      infoCallback: function (settings, start, end, max, total) {
        return 'Entries: ' + start + '–' + end + '  |  Total: ' + total;
      },
      paginate: {
        first: 'First',
        previous: '<',
        next: '>',
        last: 'Last'
      }
    },
    dom: '<"d-flex justify-content-between align-items-center mb-2"fl>rt<"d-flex justify-content-between align-items-center mt-3"ip>'
  });

  table.on('draw', function () {

    const pageInfo = table.page.info();
    const $paginate = $('.dataTables_paginate');

    const $first = $paginate.find('.first');
    const $previous = $paginate.find('.previous');
    const $next = $paginate.find('.next');
    const $last = $paginate.find('.last');

    pageInfo.page === 0 ? ($first.hide(), $previous.hide()) : ($first.show(), $previous.show());
    pageInfo.page === pageInfo.pages - 1 ? ($next.hide(), $last.hide()) : ($next.show(), $last.show());

    $paginate.find('.paginate_button')
      .removeClass('active-underline')
      .filter('.current')
      .addClass('active-underline');
  });

  const searchLabel = $('#vehicleTable_filter label');
  searchLabel.contents().filter(function () {
    return this.nodeType === 3;
  }).remove();
  searchLabel.find('input').attr('placeholder', 'Search...');
  $('#vehicleTable_length label select').css({ marginLeft: '5px' });

  /* =========================================
     IDLE RATE TOGGLE
  ========================================= */

  const categorySelect = document.getElementById('category');
  const idlingRateRow = document.getElementById('idlingRateRow');
  const idlingRateInput = document.getElementById('idling_rate');

  function toggleIdlingRate() {
    if (!categorySelect || !idlingRateRow) return;

    if (categorySelect.value === 'trucks') {
      idlingRateRow.style.display = '';
    } else {
      idlingRateRow.style.display = 'none';
      if (idlingRateInput) idlingRateInput.value = '';
    }
  }

  if (categorySelect) {
    categorySelect.addEventListener('change', toggleIdlingRate);
    toggleIdlingRate();
  }

  /* =========================================
     MODAL LOGIC
  ========================================= */

  const kmModalEl = document.getElementById('kmModal');
  const addVehicleEl = document.getElementById('addVehicleModal');
  const vehicleForm = document.getElementById('vehicleForm');

  let switchingToKm = false;

  // Detect when KM modal is about to open
  if (kmModalEl) {
    kmModalEl.addEventListener('show.bs.modal', function () {
      switchingToKm = true;
    });
  }

  // Selecting KM value
  document.querySelectorAll('.select-km').forEach(row => {
    row.addEventListener('click', function () {

      const kmpl = this.getAttribute('data-kmpl');
      document.getElementById('km_per_liter').value = kmpl;

      const kmModal = bootstrap.Modal.getInstance(kmModalEl);
      if (kmModal) kmModal.hide();
    });
  });

  // When KM modal closes → reopen Add Vehicle
  if (kmModalEl && addVehicleEl) {
    kmModalEl.addEventListener('hidden.bs.modal', function () {

      switchingToKm = false;

      setTimeout(function () {
        const addVehicleModal = new bootstrap.Modal(addVehicleEl);
        addVehicleModal.show();
      }, 150);
    });
  }

  // Cancel edit
  const cancelBtn = document.getElementById('cancelEditBtn');
  cancelBtn.addEventListener('click', () => {
    document.getElementById('vehicleForm').reset();
    document.getElementById('vehicle_id').value = '';
    document.getElementById('formTitle').textContent = 'Add New Vehicle';
    document.getElementById('addBtn').classList.remove('d-none');
    document.getElementById('updateBtn').classList.add('d-none');
    cancelBtn.classList.add('d-none');
    document.getElementById('form_mode').value = 'add';
    document.getElementById('idlingRateRow').style.display = 'none';
  });

  /* =========================================
    SAVE VEHICLE (AJAX)
  ========================================= */

$('#vehicleForm').on('submit', function (e) {

  e.preventDefault();

  $.ajax({
    url: 'vehicles.php',
    type: 'POST',
    data: $(this).serialize(),
    dataType: 'json',

    success: function (response) {

      console.log(response);

      if (response.status === 'success') {

        Swal.fire({
          icon: 'success',
          title: 'Success',
          text: response.message,
          timer: 2000,
          showConfirmButton: false
        }).then(() => {
          location.reload();
        });

      } else {

        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: response.message
        });

      }
    },

    error: function (xhr) {

      console.log("HTTP Status:", xhr.status);
      console.log("Raw Response:", xhr.responseText);

      let message = "Unknown server error.";

      try {
        const json = JSON.parse(xhr.responseText);
        if (json.message) {
          message = json.message;
        }
      } catch (e) {
        message = xhr.responseText;
      }

      Swal.fire({
        icon: 'error',
        title: 'Server Error',
        text: message
      });

    }
  });

});

});
</script>

</body>
</html>
