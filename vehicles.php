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

    $form_mode  = $_POST['form_mode'] ?? 'add';
    $vehicle_id = isset($_POST['vehicle_id']) ? (int)$_POST['vehicle_id'] : 0;

    $plate_no  = trim($_POST['plate_no'] ?? '');
    $brand     = trim($_POST['brand'] ?? '');
    $model     = trim($_POST['model'] ?? '');
    $category  = trim($_POST['category'] ?? '');
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

    if ($form_mode === 'edit') {

        $check = $conn->prepare("SELECT id FROM vehicles WHERE plate_no = ? AND id != ?");
        $check->bind_param("si", $plate_no, $vehicle_id);

    } else {

        $check = $conn->prepare("SELECT id FROM vehicles WHERE plate_no = ?");
        $check->bind_param("s", $plate_no);
    }

    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Vehicle with this plate number already exists.'
        ]);
        exit;
    }

    /* ================= UPDATE ================= */

    if ($form_mode === 'edit' && $vehicle_id > 0) {

        $stmt = $conn->prepare("
            UPDATE vehicles SET
                plate_no = ?,
                brand = ?,
                model = ?,
                km_per_liter = ?,
                idling_rate = ?,
                category = ?,
                ownership = ?,
                status = ?,
                remarks = ?
            WHERE id = ?
        ");

        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
            exit;
        }

        $stmt->bind_param(
            "sssddssssi",
            $plate_no,
            $brand,
            $model,
            $km_per_liter,
            $idling_rate,
            $category,
            $ownership,
            $status,
            $remarks,
            $vehicle_id
        );

    } else {

        /* ================= INSERT ================= */

        $stmt = $conn->prepare("
            INSERT INTO vehicles 
            (plate_no, brand, model, km_per_liter, idling_rate, category, ownership, status, remarks)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
            exit;
        }

        $stmt->bind_param(
            "sssddssss",
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
    }

    /* ================= EXECUTE ================= */

    if (!$stmt->execute()) {
        echo json_encode([
            'status' => 'error',
            'message' => $stmt->error
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'message' => $form_mode === 'edit'
            ? 'Vehicle updated successfully.'
            : 'Vehicle added successfully.'
    ]);

    exit;
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

    <?php include 'includes/dark-mode-preload.php'; ?>
    
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
              data-bs-target="#addVehicleModal"
              onclick="resetVehicleForm()">
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

/* =========================================
   KM MODAL → SELECT VALUE
========================================= */

$(document).on('click', '.select-km', function () {

  const km = $(this).data('kmpl');

  $('#km_per_liter').val(km);

  const kmModal = bootstrap.Modal.getInstance(document.getElementById('kmModal'));
  kmModal.hide();

  const vehicleModal = new bootstrap.Modal(document.getElementById('addVehicleModal'));
  vehicleModal.show();

});
/* =========================================
   ROW CLICK → EDIT MODE
========================================= */

$(document).on('click', '.vehicle-row', function () {

  const modal = new bootstrap.Modal(document.getElementById('addVehicleModal'));

  $('#vehicle_id').val($(this).data('id'));
  $('#plate_no').val($(this).data('plate'));
  $('#brand').val($(this).data('brand'));
  $('#model').val($(this).data('model'));
  $('#km_per_liter').val($(this).data('kmpl'));
  $('#idling_rate').val($(this).data('idling'));
  $('#category').val($(this).data('category')).trigger('change');
  $('#ownership').val($(this).data('ownership'));
  $('#status').val($(this).data('status'));
  $('#remarks').val($(this).data('remarks'));

  $('#formTitle').text('Edit Vehicle');
  $('#addBtn').addClass('d-none');
  $('#updateBtn').removeClass('d-none');
  $('#form_mode').val('edit');

  modal.show();
});

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

function resetVehicleForm() {

    $('#vehicleForm')[0].reset();
    $('#vehicle_id').val('');
    $('#form_mode').val('add');

    $('#formTitle').text('Add New Vehicle');
    $('#addBtn').removeClass('d-none');
    $('#updateBtn').addClass('d-none');
}
</script>

</body>
</html>
