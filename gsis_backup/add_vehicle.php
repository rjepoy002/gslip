<?php
require_once 'includes/config.php';

$conn = getDBConnection();
$success = false;
$error = '';

// Add or update vehicle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plate_no = trim($_POST['plate_no']);
    $brand = trim($_POST['brand']);
    $model = trim($_POST['model']);
    $km_per_liter = trim($_POST['km_per_liter']);
    $idling_rate = isset($_POST['idling_rate']) && $_POST['category'] === 'trucks' ? trim($_POST['idling_rate']) : null;
    $category = trim($_POST['category']);
    $ownership = trim($_POST['ownership']);
    $status = trim($_POST['status']);
    $remarks = trim($_POST['remarks']);

    if ($plate_no && $brand && $model && $km_per_liter && $category && $ownership && $status) {
        if (isset($_POST['form_mode']) && $_POST['form_mode'] === 'edit' && isset($_POST['vehicle_id']) && $_POST['vehicle_id'] !== '') {
            $id = intval($_POST['vehicle_id']);

            $check = $conn->prepare("SELECT id FROM vehicles WHERE plate_no = ? AND id != ?");
            $check->bind_param("si", $plate_no, $id);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = "Another vehicle with the same plate number already exists.";
            } else {
                $stmt = $conn->prepare("UPDATE vehicles SET plate_no=?, brand=?, model=?, km_per_liter=?, idling_rate=?, category=?, ownership=?, status=?, remarks=? WHERE id=?");
                $stmt->bind_param("sssssssssi", $plate_no, $brand, $model, $km_per_liter, $idling_rate, $category, $ownership, $status, $remarks, $id);
                if ($stmt->execute()) {
                    $success = true;
                } else {
                    $error = "Error updating vehicle: " . $stmt->error;
                }
            }
        } else {
            $check = $conn->prepare("SELECT id FROM vehicles WHERE plate_no = ?");
            $check->bind_param("s", $plate_no);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = "Another vehicle with the same plate number already exists.";
            } else {
                $stmt = $conn->prepare("INSERT INTO vehicles (plate_no, brand, model, km_per_liter, idling_rate, category, ownership, status, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssssss", $plate_no, $brand, $model, $km_per_liter, $idling_rate, $category, $ownership, $status, $remarks);
                if ($stmt->execute()) {
                    $success = true;
                } else {
                    $error = "Error adding vehicle: " . $stmt->error;
                }
            }
        }
    } else {
        $error = "All required fields must be filled.";
    }
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("UPDATE vehicles SET status = 'inactive' WHERE id = $id");
}

$result = $conn->query("SELECT id, plate_no, brand, model, km_per_liter, idling_rate, category, ownership, status, remarks FROM vehicles ORDER BY id DESC");
$vehicles = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Vehicle</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="includes/style.css">
</head>
<body>

<div class="container py-4">
  <?php include 'header.php'; ?>

  <?php if ($success): ?>
    <div id="vehicleAlertSuccess" class="alert alert-success alert-dismissible fade show" role="alert">
      Vehicle saved successfully.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php elseif ($error): ?>
    <div id="vehicleAlertError" class="alert alert-danger alert-dismissible fade show" role="alert">
      <?= $error ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <div class="row">
    <div class="col-lg-3 col-md-7 col-sm-12 mb-4">
      <div class="card rounded-4">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 id="formTitle"><strong>Add New Vehicle</strong></h6>
            <button type="button" id="cancelEditBtn" class="btn btn-warning px-2 d-none"><i class="fa fa-ban"></i> Cancel Edit</button>
          </div>
          <form method="POST" id="vehicleForm">
            <input type="hidden" name="form_mode" id="form_mode" value="add">
            <input type="hidden" name="vehicle_id" id="vehicle_id">

            <table class="table borderless w-100" style="max-width: 600px;">
              <tr>
                <td style="white-space: nowrap; padding-right: 10px;">
                  <label for="plate_no" class="form-label">Plate Number</label>
                </td>
                <td style="border-bottom: 1px dashed #f1f1f1;">
                  <input type="text" name="plate_no" id="plate_no" class="form-control" required pattern="[A-Za-z0-9\s\-]+">
                </td>
              </tr>
              <tr>
                <td style="white-space: nowrap; padding-right: 10px;">
                  <label for="brand" class="form-label">Brand</label>
                </td>
                <td style="border-bottom: 1px dashed #f1f1f1;">
                  <input type="text" name="brand" id="brand" class="form-control" required>
                </td>
              </tr>
              <tr>
                <td style="white-space: nowrap; padding-right: 10px;">
                  <label for="model" class="form-label">Model</label>
                </td>
                <td style="border-bottom: 1px dashed #f1f1f1;">
                  <input type="text" name="model" id="model" class="form-control" required>
                </td>
              </tr>
              <tr>
                <td style="white-space: nowrap; padding-right: 10px;">
                  <label class="form-label">Category</label>
                </td>
                <td style="border-bottom: 1px dashed #f1f1f1;">
                  <select name="category" id="category" class="form-select" required>
                    <option value="" disabled selected>-- Select --</option>
                    <option value="4-wheels">4-Wheels</option>
                    <option value="2-wheels">2-wheels</option>
                    <option value="trucks">Trucks</option>
                  </select>
                </td>
              </tr>
              <tr>
                <td style="white-space: nowrap; padding-right: 10px;">
                  <label for="km_per_liter" class="form-label">KM per Liter</label>
                  <button type="button" class="btn btn-sm px-2 border-0 bg-transparent" data-bs-toggle="modal" data-bs-target="#kmModal">
                    <i class="fa fa-car"></i>
                  </button>
                </td>
                <td style="border-bottom: 1px dashed #f1f1f1;">
                  <input type="number" step="0.01" name="km_per_liter" id="km_per_liter" class="form-control" placeholder="e.g. 12.50">
                </td>
              </tr>
              <tr id="idlingRateRow" style="display: none;">
                <td style="white-space: nowrap; padding-right: 10px;">
                  <label for="km_per_liter" class="form-label">Idle Rate (L/hr)</label>
                </td>
                <td style="border-bottom: 1px dashed #f1f1f1;">
                  <input type="number" step="0.01" name="idling_rate" id="idling_rate" class="form-control" placeholder="e.g. 1.5">
                </td>
              </tr>
              <tr>
                <td style="white-space: nowrap; padding-right: 10px;">
                  <label class="form-label">Ownership</label>
                </td>
                <td style="border-bottom: 1px dashed #f1f1f1;">
                  <select name="ownership" id="ownership" class="form-select" required>
                    <option value="" disabled selected>-- Select --</option>
                    <option value="coop-owned">Coop-Owned</option>
                    <option value="private">Private</option>
                  </select>
                </td>
              </tr>
              <tr>
                <td style="white-space: nowrap; padding-right: 10px;">
                  <label class="form-label">Status</label>
                </td>
                <td style="border-bottom: 1px dashed #f1f1f1;">
                  <select name="status" id="status" class="form-select" required>
                    <option value="" disabled selected>-- Select --</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                  </select>
                </td>
              </tr>
              <tr>
                <td style="white-space: nowrap; padding-right: 10px;">
                  <label class="form-label">Remarks</label>
                </td>
                <td>
                  <input type="text" name="remarks" id="remarks" class="form-control">
                </td>
              </tr>
              <!-- Add more rows here as needed -->
            </table>
            <div class="col-12 text-end">
              <button type="submit" name="add_vehicle" id="addBtn" class="btn btn-primary px-2"><i class="fas fa-save"></i> Save Vehicle</button>
              <button type="submit" name="update_vehicle" id="updateBtn" class="btn btn-success px-2 d-none"><i class="fas fa-save"></i> Save Changes</button>
              <a href="add_slip.php" class="btn btn-secondary px-2"><i class="fa fa-reply-all"></i> Back</a>
            </div>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-9 col-md-5 col-sm-12 mb-4">
      <div class="card rounded-4">
        <div class="card-body">
          <h6><strong>Vehicle List</strong></h6>
          <!-- <input type="text" id="vehicleSearch" class="form-control" placeholder="Search vehicle..."> -->
          <div class="table-responsive">
            <table class="styled-table" id="vehicleTable">
              <thead>
                <tr>
                  <th>#</th>
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
                <?php if (count($vehicles) === 0): ?>
                  <tr><td colspan="9" class="text-center text-muted">No vehicles found.</td></tr>
                <?php else: ?>
                  <?php foreach ($vehicles as $i => $v): ?>
                    <tr data-id="<?= $v['id'] ?>" 
                        data-plate_no="<?= htmlspecialchars($v['plate_no']) ?>" 
                        data-brand="<?= htmlspecialchars($v['brand']) ?>" 
                        data-model="<?= htmlspecialchars($v['model']) ?>" 
                        data-km_per_liter="<?= htmlspecialchars($v['km_per_liter']) ?>" 
                        data-idling_rate="<?= htmlspecialchars($v['idling_rate']) ?>"
                        data-category="<?= htmlspecialchars($v['category']) ?>" 
                        data-ownership="<?= htmlspecialchars($v['ownership']) ?>" 
                        data-status="<?= htmlspecialchars($v['status']) ?>" 
                        data-remarks="<?= htmlspecialchars($v['remarks']) ?>">
                      <td><?= $i + 1 ?></td>
                      <td><?= htmlspecialchars($v['plate_no']) ?></td>
                      <td><?= htmlspecialchars($v['brand']) ?></td>
                      <td><?= htmlspecialchars($v['model']) ?></td>
                      <td><?= htmlspecialchars($v['km_per_liter']) ?></td>
                      <td><?= htmlspecialchars($v['idling_rate']) ?></td>
                      <td><?= htmlspecialchars($v['category']) ?></td>
                      <td><?= htmlspecialchars($v['ownership']) ?></td>
                      <td><?= htmlspecialchars($v['status']) ?></td>
                      <td><?= htmlspecialchars($v['remarks']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- KM per Liter Modal -->
<div class="modal fade" id="kmModal" tabindex="-1" aria-labelledby="kmModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h6><strong>Vehicle Reference</strong></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <table class="styled-table">
          <thead class="table-light">
            <tr>
              <th width="5%">No</th>
              <th>Brand</th>
              <th>Model</th>
              <th width="20%">Fuel</th>
              <th width="10%">KM/L</th>
            </tr>
          </thead>
          <tbody>
            <tr class="select-km" data-kmpl="14">
              <td>1</td>
              <td>Ford</td>
              <td>Ecosport</td>
              <td>Gasoline</td>
              <td>14</td>
            </tr>
            <tr class="select-km" data-kmpl="10.1">
              <td>2</td>
              <td>Ford</td>
              <td>Territory</td>
              <td></td>
              <td>10.1</td>
            </tr>
            <tr class="select-km" data-kmpl="23">
              <td>3</td>
              <td>Honda</td>
              <td>Jazz</td>
              <td>Gasoline</td>
              <td>23</td>
            </tr>
            <tr class="select-km" data-kmpl="10.87">
              <td>4</td>
              <td>Hyundai</td>
              <td>Accent</td>
              <td>Gasoline</td>
              <td>10.87</td>
            </tr>
            <tr class="select-km" data-kmpl="19.5">
              <td>5</td>
              <td>Hyundai</td>
              <td>Accent</td>
              <td>Diesel</td>
              <td>19.5</td>
            </tr>
            <tr class="select-km" data-kmpl="15.3">
              <td>6</td>
              <td>Mazda</td>
              <td>3</td>
              <td>Gasoline</td>
              <td>15.3</td>
            </tr>
            <tr class="select-km" data-kmpl="11">
              <td>7</td>
              <td>Mitsubishi</td>
              <td>Lancer</td>
              <td></td>
              <td>11</td>
            </tr>
            <tr class="select-km" data-kmpl="15.3">
              <td>8</td>
              <td>Mitsubishi</td>
              <td>Mirage</td>
              <td>Gasoline</td>
              <td>15.3</td>
            </tr>
            <tr class="select-km" data-kmpl="10">
              <td>9</td>
              <td>Mitsubishi</td>
              <td>Montero</td>
              <td>Diesel</td>
              <td>10</td>
            </tr>
            <tr class="select-km" data-kmpl="30" style="color: red;">
              <td>10</td>
              <td>Motorcycle</td>
              <td></td>
              <td>Gasoline</td>
              <td>30</td>
            </tr>
            <tr class="select-km" data-kmpl="10">
              <td>11</td>
              <td>Strada</td>
              <td>Triton Pick-up</td>
              <td></td>
              <td>10</td>
            </tr>
            <tr class="select-km" data-kmpl="10">
              <td>12</td>
              <td>Toyota</td>
              <td>Avanza</td>
              <td>Gasoline</td>
              <td>10</td>
            </tr>
            <tr class="select-km" data-kmpl="8">
              <td>13</td>
              <td>Toyota</td>
              <td>Fortuner</td>
              <td>Diesel</td>
              <td>8</td>
            </tr>
            <tr class="select-km" data-kmpl="8.2">
              <td>14</td>
              <td>Toyota</td>
              <td>Hilux</td>
              <td>Diesel</td>
              <td>8.2</td>
            </tr>
            <tr class="select-km" data-kmpl="11">
              <td>15</td>
              <td>Toyota</td>
              <td>Innova</td>
              <td>Diesel</td>
              <td>11</td>
            </tr>
            <tr class="select-km" data-kmpl="8">
              <td>16</td>
              <td>Toyota</td>
              <td>Innova</td>
              <td>Gasoline</td>
              <td>8</td>
            </tr>
            <tr class="select-km" data-kmpl="18.5">
              <td>17</td>
              <td>Toyota</td>
              <td>Raize</td>
              <td>Gasoline</td>
              <td>18.5</td>
            </tr>
            <tr class="select-km" data-kmpl="10">
              <td>18</td>
              <td>Toyota</td>
              <td>Revo</td>
              <td>Diesel</td>
              <td>10</td>
            </tr>
            <tr class="select-km" data-kmpl="8">
              <td>19</td>
              <td>Toyota</td>
              <td>Revo 2.0</td>
              <td>Gasoline</td>
              <td>8</td>
            </tr>
            <tr class="select-km" data-kmpl="10">
              <td>20</td>
              <td>Toyota</td>
              <td>Wigo</td>
              <td>Gasoline</td>
              <td>10</td>
            </tr>
            <tr class="select-km" data-kmpl="15.4">
              <td>21</td>
              <td>Mitsubishi</td>
              <td>L300</td>
              <td></td>
              <td>15.4</td>
            </tr>
            <tr class="select-km" data-kmpl="13.64">
              <td>22</td>
              <td>Mitsubishi</td>
              <td>L200</td>
              <td></td>
              <td>13.64</td>
            </tr>
            <tr class="select-km" data-kmpl="8.1" style="color: blue;">
              <td>23</td>
              <td>Isuzu</td>
              <td>Elf Manlift Truck, 2006</td>
              <td></td>
              <td>8.1</td>
            </tr>
            <tr class="select-km" data-kmpl="10.6" style="color: blue;">
              <td>24</td>
              <td>Fuso</td>
              <td>Canter, 2007</td>
              <td></td>
              <td>10.6</td>
            </tr>
            <tr class="select-km" data-kmpl="11.6" style="color: blue;">
              <td>25</td>
              <td>Mitsubishi</td>
              <td>Canter, 2014</td>
              <td></td>
              <td>11.6</td>
            </tr>
            <tr class="select-km" data-kmpl="11.6" style="color: blue;">
              <td>26</td>
              <td>Fuso</td>
              <td>Basket</td>
              <td></td>
              <td>11.6</td>
            </tr>
            <tr class="select-km" data-kmpl="8.1" style="color: blue;">
              <td>27</td>
              <td>Isuzu</td>
              <td>Forwarder Drill Truck, 2006</td>
              <td></td>
              <td>8.1</td>
            </tr>
            <tr class="select-km" data-kmpl="8.1" style="color: blue;">
              <td>28</td>
              <td>Hino</td>
              <td>Truck, 2014</td>
              <td></td>
              <td>8.1</td>
            </tr>
            <tr class="select-km" data-kmpl="6.8" style="color: blue;">
              <td>29</td>
              <td>Foton</td>
              <td>Tornado</td>
              <td></td>
              <td>6.8</td>
            </tr>
            <tr class="select-km" data-kmpl="7.46" style="color: blue;">
              <td>30</td>
              <td>Fuso</td>
              <td>Canter, 2024</td>
              <td></td>
              <td>7.46</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>


<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script>
// idle rate toggle
document.addEventListener('DOMContentLoaded', function () {
  const categorySelect = document.getElementById('category');
  const idlingRateRow = document.getElementById('idlingRateRow');
  const idlingRateInput = document.getElementById('idling_rate');

  function toggleIdlingRate() {
    if (categorySelect.value === 'trucks') {
      idlingRateRow.style.display = '';
    } else {
      idlingRateRow.style.display = 'none';
      idlingRateInput.value = ''; // clear when hidden
    }
  }

  categorySelect.addEventListener('change', toggleIdlingRate);
  toggleIdlingRate(); // initialize on load
});


if ($.fn.DataTable.isDataTable('#vehicleTable')) {
  $('#vehicleTable').DataTable().destroy();
}
$(document).ready(function () {
  const table = $('#vehicleTable').DataTable({
    paging: true,
    searching: true,
    ordering: true,
    responsive: true,
    pageLength: 10,
    language: {
      lengthMenu: 'Rows per page: _MENU_',
      search: '',
      searchPlaceholder: 'Search...',
      infoCallback: function (settings, start, end, max, total, pre) {
        return 'Entries: ' + start + '–' + end + '  |  Total: ' + total;
      },
      paginate: {
        first: 'First',
        previous: '<',
        next: '>',
        last: 'Last'
      }
    },
    pagingType: "full_numbers",
    lengthMenu: [[10, 25, 50, 75, 100], [10, 25, 50, 75, 100]],
    dom: '<"d-flex justify-content-between align-items-center mb-2"lf>rt<"d-flex justify-content-between align-items-center mt-3"ip>'
  });


  $('#vehicleTable').on('draw.dt', function () {
    const pageInfo = table.page.info();
    const $paginate = $('.dataTables_paginate');
    const $first = $paginate.find('.first');
    const $previous = $paginate.find('.previous');
    const $next = $paginate.find('.next');
    const $last = $paginate.find('.last');

    // Hide "First" and "Previous" on first page
    if (pageInfo.page === 0) {
      $first.css('display', 'none');
      $previous.css('display', 'none');
    } else {
      $first.css('display', '');
      $previous.css('display', '');
    }

    // Hide "Next" and "Last" on last page
    if (pageInfo.page === pageInfo.pages - 1) {
      $next.css('display', 'none');
      $last.css('display', 'none');
    } else {
      $next.css('display', '');
      $last.css('display', '');
    }

    // Underline the current page only
    $paginate.find('.paginate_button').removeClass('active-underline');
    $paginate.find('.paginate_button.current').addClass('active-underline');
  });

  // Force initial pagination draw update
  $('#vehicleTable').trigger('draw.dt');

  // Optional CSS spacing fix
  $('#vehicleTable_length label select').css({ marginLeft: '5px' });
  const searchLabel = $('#vehicleTable_filter label');
  searchLabel.contents().filter(function () {
    return this.nodeType === 3;
  }).remove();
  searchLabel.find('input').attr('placeholder', 'Search...');
});

//for layout rearrangement
$(document).ready(function () {
  const wrapper = $('.dataTables_wrapper');
  const lengthControl = wrapper.find('.dataTables_length');
  const filterControl = wrapper.find('.dataTables_filter');

  // Wrap both controls in a flex container with spacing
  const topControls = $('<div class="top-controls"></div>');
  topControls.append(filterControl);   // Search first (left)
  topControls.append(lengthControl);   // Entries second (right)

  wrapper.prepend(topControls);
});

// Auto-dismiss alerts
const successAlert = document.getElementById('vehicleAlertSuccess');
if (successAlert) {
  setTimeout(() => bootstrap.Alert.getOrCreateInstance(successAlert).close(), 3000);
}
const errorAlert = document.getElementById('vehicleAlertError');
if (errorAlert) {
  setTimeout(() => bootstrap.Alert.getOrCreateInstance(errorAlert).close(), 5000);
}

if (window.history.replaceState) {
  window.history.replaceState(null, null, window.location.href);
}

// Edit vehicle on double click
[...document.querySelectorAll('#vehicleTable tbody tr')].forEach(row => {
  row.addEventListener('dblclick', () => {
    document.getElementById('vehicle_id').value = row.dataset.id;
    document.getElementById('plate_no').value = row.dataset.plate_no;
    document.getElementById('brand').value = row.dataset.brand;
    document.getElementById('model').value = row.dataset.model;
    document.getElementById('km_per_liter').value = row.dataset.km_per_liter;
    document.getElementById('idling_rate').value = row.dataset.idling_rate;
    document.getElementById('category').value = row.dataset.category;
    document.getElementById('ownership').value = row.dataset.ownership;
    document.getElementById('status').value = row.dataset.status;
    document.getElementById('remarks').value = row.dataset.remarks;

    // Show/hide Idling Rate based on category
    const idlingRateRow = document.getElementById('idlingRateRow');
    if (idlingRateRow) {  // ✅ check first to prevent error
      if (row.dataset.category === 'trucks') {
        idlingRateRow.style.display = '';
      } else {
        idlingRateRow.style.display = 'none';
        document.getElementById('idling_rate').value = ''; // clear if not trucks
      }
    }

    document.getElementById('formTitle').textContent = 'Edit Vehicle';
    document.getElementById('addBtn').classList.add('d-none');
    document.getElementById('updateBtn').classList.remove('d-none');
    document.getElementById('cancelEditBtn').classList.remove('d-none');
    document.getElementById('form_mode').value = 'edit';
  });
});

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

// Escape key handler
let escapePressedOnce = false;
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    const updateBtn = document.getElementById('updateBtn');
    const isEditing = updateBtn && getComputedStyle(updateBtn).display !== 'none';
    if (isEditing) {
      cancelBtn.click();
      escapePressedOnce = false;
    } else {
      window.location.href = 'add_slip.php';
    }
    e.preventDefault();
  }
}, true);

// KM per Liter modal selection
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.select-km').forEach(row => {
    row.addEventListener('click', function () {
      const kmpl = this.getAttribute('data-kmpl');
      document.getElementById('km_per_liter').value = kmpl;

      // close modal
      const kmModal = bootstrap.Modal.getInstance(document.getElementById('kmModal'));
      kmModal.hide();
    });
  });
});

</script>
</body>
</html>
