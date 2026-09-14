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

// Add or update Routes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    header('Content-Type: application/json');

    $form_mode  = $_POST['form_mode'] ?? 'add';
    $routes_id = isset($_POST['routes_id']) ? (int)$_POST['routes_id'] : 0;
    $area  = trim($_POST['area'] ?? '');
    $route     = trim($_POST['route'] ?? '');
    $origin     = trim($_POST['origin'] ?? '');
    $destination  = trim($_POST['destination'] ?? '');
    $distance_km = trim($_POST['distance_km'] ?? '');
    $fuel_allocation = trim($_POST['fuel_allocation'] ?? '');
    $status    = trim($_POST['status'] ?? '');
    $remarks   = trim($_POST['remarks'] ?? '');

    /* ================= VALIDATION ================= */

    if (!$origin || !$destination || !$distance_km || !$status || !$remarks) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Required fields are missing.'
        ]);
        exit;
    }

    /* ================= DUPLICATE CHECK ================= */

    if ($form_mode === 'edit') {

        $check = $conn->prepare("SELECT id FROM routes WHERE origin = ? AND destination = ? AND id != ?");
        $check->bind_param("ssi", $origin, $destination, $routes_id);

    } else {

        $check = $conn->prepare("SELECT id FROM routes WHERE origin = ? AND destination = ?");
        $check->bind_param("ss", $origin, $destination);

    }

    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Another routes with the same origin and destination already exists.'
        ]);
        exit;
    }

    

    if ($form_mode === 'edit' && $routes_id > 0) {

      /* ================= UPDATE ================= */
        $stmt = $conn->prepare("UPDATE routes SET route=?, origin=?, destination=?, distance_km=?, fuel_allocation=?, status=?, remarks=? WHERE id=?");
        
        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
            exit;
        }

        $stmt->bind_param("sssddssi", $route, $origin, $destination, $distance_km, $fuel_allocation, $status, $remarks, $routes_id);

    } else {

        /* ================= INSERT ================= */
        $stmt = $conn->prepare("INSERT INTO routes (area, route, origin, destination, distance_km, fuel_allocation, status, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
            exit;
        }

        $stmt->bind_param("ssssddss", $area, $route, $origin, $destination, $distance_km, $fuel_allocation, $status, $remarks);
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
            ? 'Routes updated successfully.'
            : 'Routes added successfully.'
    ]);

    exit;
}


/* 🔎 DEBUG — TEMPORARY */
// var_dump($dateFrom, $dateTo);
// exit;
// var_dump($_SESSION['role'], $_SESSION['user_id'], $_SESSION['area'], $_SESSION['department_id']);
// exit;

/* =========================================================
  FETCH Routes LIST
========================================================= */
$conn->begin_transaction();

$stmt = $conn->prepare("
  SELECT
    id, 
    area, 
    route, 
    origin, 
    destination, 
    distance_km, 
    fuel_allocation, 
    status, 
    remarks

  FROM routes
  ORDER BY id DESC

");

$stmt->execute();
$result = $stmt->get_result();


/* =========================================================
  FETCH DISTINCT AREAS FOR FILTER DROPDOWN
========================================================= */

$areaStmt = $conn->prepare("
    SELECT DISTINCT area_name
    FROM areas
    ORDER BY area_name ASC
");

$areaStmt->execute();
$areaResult = $areaStmt->get_result();


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
<?php include 'includes/add_routes_modal.php'; ?>

<div class="app-content">

    <!-- Main Page Content -->
    <main class="main-content">
        <!-- Temporary Empty State -->
        <div class="panel-container">
            <!-- Page Header -->
            <div class="page-header mb-4">
              <h1 class="mb-1">Routes Management</h1>
              <p class="page-subtitle mb-1">
                  Maintain route records with distance, fuel computation reference, and activation status for gas slip processing.
              </p>
            </div>

            <button class="btn btn-primary btn-sm" 
              data-bs-toggle="modal" 
              data-bs-target="#addRoutesModal"
              onclick="resetRoutesForm()">
                + Add Routes
            </button>

            <!-- Recent Activity -->
            <table class="styled-table excel-table" id="RoutesTable">
                <thead>
                    <tr class="group-header">
                      <th>No.</th>
                      <th>Area</th>
                      <th>Route</th>
                      <th>Origin</th>
                      <th>Destination</th>
                      <th>Distance (km)</th>
                      <th>Fuel Allocation</th>
                      <th>Status</th>
                      <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($result->num_rows > 0): ?>
                  <?php $no = 1; ?>
                  <?php while ($row = $result->fetch_assoc()): ?>

                    <tr class="Routes-row"
                        data-id="<?= $row['id'] ?>"
                        data-area="<?= htmlspecialchars($row['area']) ?>" 
                        data-route="<?= htmlspecialchars($row['route']) ?>" 
                        data-origin="<?= htmlspecialchars($row['origin']) ?>" 
                        data-destination="<?= htmlspecialchars($row['destination']) ?>" 
                        data-distance_km="<?= htmlspecialchars($row['distance_km']) ?>" 
                        data-fuel_allocation="<?= htmlspecialchars($row['fuel_allocation']) ?>" 
                        data-status="<?= htmlspecialchars($row['status']) ?>" 
                        data-remarks="<?= htmlspecialchars($row['remarks']) ?>">
                      <td><?= $no++; ?> </td>
                      <td><?= htmlspecialchars($row['area']) ?></td>
                      <td>
                        <?= $row['route'] !== null 
                            ? htmlspecialchars($row['route']) 
                            : '' ?>
                      </td>
                      <td><?= htmlspecialchars($row['origin']) ?></td>
                      <td><?= htmlspecialchars($row['destination']) ?></td>
                      <td><?= htmlspecialchars($row['distance_km']) ?></td>
                      <td>
                        <?= $row['fuel_allocation'] !== null 
                            ? htmlspecialchars($row['fuel_allocation']) 
                            : '' ?>
                      </td>
                      <td><?= htmlspecialchars($row['status']) ?></td>
                      <td>
                        <?= $row['remarks'] !== null 
                            ? htmlspecialchars($row['remarks']) 
                            : '' ?>
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

if ($.fn.DataTable.isDataTable('#RoutesTable')) {
  $('#RoutesTable').DataTable().destroy();
}

const table = $('#RoutesTable').DataTable({
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
   ROW CLICK → EDIT MODE
========================================= */

$(document).on('click', '.Routes-row', function () {

  const modal = new bootstrap.Modal(document.getElementById('addRoutesModal'));

  $('#routes_id').val($(this).data('id'));
  $('#area').val($(this).data('area'));
  $('#route_input').val($(this).data('route'));
  // 🔥 ADD THIS LINE
  document.getElementById('route_input').dispatchEvent(new Event('input'));

  $('#origin').val($(this).data('origin'));
  $('#destination').val($(this).data('destination'));
  $('#distance_km').val($(this).data('distance_km'));
  $('#fuel_allocation').val($(this).data('fuel_allocation'));
  $('#status').val($(this).data('status'));
  $('#remarks').val($(this).data('remarks'));

  $('#formTitle').text('Edit Routes');
  $('#addBtn').addClass('d-none');
  $('#updateBtn').removeClass('d-none');
  $('#form_mode').val('edit');

  modal.show();
});


/* =========================================
   SAVE Routes (AJAX)
========================================= */

$('#RoutesForm').on('submit', function (e) {

  e.preventDefault();

  $.ajax({
    url: 'routes.php',
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

function resetRoutesForm() {

    $('#RoutesForm')[0].reset();
    $('#routes_id').val('');
    $('#form_mode').val('add');

    $('#formTitle').text('Add New Routes');
    $('#addBtn').removeClass('d-none');
    $('#updateBtn').addClass('d-none');
}

// Auto-fill Origin based on selected Area
function copyAreaToOrigin() {
    const areaSelect = document.getElementById('area');
    const selectedText = areaSelect.options[areaSelect.selectedIndex].text; // get visible text
    document.getElementById('origin').value = selectedText;
}

// Toggle Fuel Allocation field based on Route input
document.addEventListener('DOMContentLoaded', function () {
  const routeInput = document.getElementById('route_input');
  const fuelInput = document.getElementById('fuel_allocation');
  const fuelAllocationRow = document.getElementById('fuel_allocation_row');

  function handleRouteChange() {
    const hasRoute = routeInput.value.trim() !== '';

    // Toggle required attribute
    if (hasRoute) {
      fuelInput.setAttribute('required', 'required');
    } else {
      fuelInput.removeAttribute('required');
    }

    // Toggle row visibility
    fuelAllocationRow.style.display = hasRoute ? '' : 'none';
  }

  // Run on load and on input
  handleRouteChange();
  routeInput.addEventListener('input', handleRouteChange);
});
</script>

</body>
</html>
