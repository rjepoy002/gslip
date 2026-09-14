<?php
require_once 'includes/config.php';
$conn = getDBConnection();

$success = false;
$error = '';

// adopting edit mode
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $area = !empty($_POST['area']) ? trim($_POST['area']) : null;
  $route = !empty($_POST['route']) ? trim($_POST['route']) : null;
  $origin = !empty($_POST['origin']) ? trim($_POST['origin']) : null;
  $destination = !empty($_POST['destination']) ? trim($_POST['destination']) : null;
  $distance_km = isset($_POST['distance_km']) && $_POST['distance_km'] !== '' ? floatval($_POST['distance_km']) : null;
  $fuel_allocation = isset($_POST['fuel_allocation']) && $_POST['fuel_allocation'] !== '' ? floatval($_POST['fuel_allocation']) : 0;
  $status = !empty($_POST['status']) ? $_POST['status'] : null;
  $remarks = !empty($_POST['remarks']) ? trim($_POST['remarks']) : null;

    if ($area && $origin && $destination && $distance_km) {
        if (isset($_POST['form_mode']) && $_POST['form_mode'] === 'edit' && isset($_POST['route_id']) && $_POST['route_id'] !== '') {
            $id = intval($_POST['route_id']);
            
            $check = $conn->prepare("SELECT id FROM routes WHERE origin = ? AND destination = ? AND id != ?");
            $check->bind_param("ssi", $origin, $destination, $id);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = "Another route with the same origin and destination already exists.";
            } else {
                $stmt = $conn->prepare("UPDATE routes SET route=?, origin=?, destination=?, distance_km=?, fuel_allocation=?, status=?, remarks=? WHERE id=?");
                $stmt->bind_param("sssddssi", $route, $origin, $destination, $distance_km, $fuel_allocation, $status, $remarks, $id);
                if ($stmt->execute()) {
                    $success = true;
                } else {
                    $error = "Error updating routes: " . $stmt->error;
                }
            }
        } else {
            $check = $conn->prepare("SELECT id FROM routes WHERE origin = ? AND destination = ?");
            $check->bind_param("ss", $origin, $destination);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = "Another routes with the same origin and destination already exists.";
            } else {
                $stmt = $conn->prepare("INSERT INTO routes (area, route, origin, destination, distance_km, fuel_allocation, status, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssddss", $area, $route, $origin, $destination, $distance_km, $fuel_allocation, $status, $remarks);
                if ($stmt->execute()) {
                    $success = true;
                } else {
                    $error = "Error adding routes: " . $stmt->error;
                }
            }
        }
    } else {
        $error = "All required fields must be filled.";
    }
}

$result = $conn->query("SELECT id, area, route, origin, destination, distance_km, fuel_allocation, status, remarks FROM routes ORDER BY id DESC");
$routes = $result->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add New Destination</title>
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
    <div id="routeAlertSuccess" class="alert alert-success alert-dismissible fade show" role="alert">
      Route saved successfully.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php elseif ($error): ?>
    <div id="routeAlertError" class="alert alert-danger alert-dismissible fade show" role="alert">
      <?= $error ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <div class="row">
    <div class="col-lg-3 col-md-7 col-sm-12 mb-4">
      <div class="card rounded-4">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 id="formTitle"><strong>Add New Destination</strong></h6>
            <button type="button" id="cancelEditBtn" class="btn btn-warning px-2 d-none"><i class="fa fa-ban"></i> Cancel Edit</button>
          </div>
          <form method="POST" id="routeForm">
            <input type="hidden" name="form_mode" id="form_mode" value="add">
            <input type="hidden" name="route_id" id="route_id">

            <table class="table borderless w-100" style="max-width: 600px;">
              <tr>
                <td style="white-space: nowrap; padding-right: 10px;">
                  <label class="form-label">Area</label>
                </td>
                <td style="border-bottom: 1px dashed #f1f1f1;">
                  <select name="area" id="area" class="form-select" required onchange="copyAreaToOrigin()">
                    <option value="" disabled selected>-- Select Area --</option>
                    <option value="Aborlan">Aborlan</option>
                    <option value="Araceli">Araceli</option>
                    <option value="Balabac">Balabac</option>
                    <option value="Brookes Point">Brookes Point</option>
                    <option value="Cagayancillo">Cagayancillo</option>
                    <option value="Cuyo">Cuyo</option>
                    <option value="Dumaran">Dumaran</option>
                    <option value="El Nido">El Nido</option>
                    <option value="Narra">Narra</option>
                    <option value="Puerto Princesa [Main Office]">Puerto Princesa [Main Office]</option>
                    <option value="Quezon">Quezon</option>
                    <option value="Rizal">Rizal</option>
                    <option value="Roxas">Roxas</option>
                    <option value="San Vicente">San Vicente</option>
                    <option value="Taytay">Taytay</option>
                  </select>
                </td>
              </tr>
              <tr>
                <td style="white-space: nowrap; padding-right: 10px;">
                  <label for="route_input" class="form-label">Route</label>
                </td>
                <td style="border-bottom: 1px dashed #f1f1f1;">
                  <input
                    type="text"
                    name="route"
                    id="route_input"
                    class="form-control"
                    maxlength="6"
                    placeholder="e.g., PX0453"
                  />
                  <div class="form-text" style="color: #cc0000; font-size: 0.70em;">Leave blank if not applicable</div>
                </td>
              </tr>
              <tr>
                <td style="white-space: nowrap; padding-right: 10px;">
                  <label for="origin" class="form-label">Origin</label>
                </td>
                <td style="border-bottom: 1px dashed #f1f1f1;">
                  <input type="text" name="origin" id="origin" class="form-control" required>
                </td>
              </tr>
              <tr>
                <td style="white-space: nowrap; padding-right: 10px;">
                  <label class="form-label">Destination</label>
                </td>
                <td style="border-bottom: 1px dashed #f1f1f1;">
                  <input type="text" name="destination" id="destination" class="form-control" required>
                </td>
              </tr>
              <tr>
                <td style="white-space: nowrap; padding-right: 10px;">
                  <label class="form-label">Distance (km)</label>
                </td>
                <td style="border-bottom: 1px dashed #f1f1f1;">
                  <input type="number" name="distance_km" id="distance_km" step="0.01" class="form-control" required>
                </td>
              </tr>
              <tr id="fuel_allocation_row">
                <td style="white-space: nowrap; padding-right: 10px;">
                  <label for="fuel_allocation" class="form-label">Fuel Allocation</label>
                </td>
                <td style="border-bottom: 1px dashed #f1f1f1;">
                  <input type="text" name="fuel_allocation" id="fuel_allocation" class="form-control">
                </td>
              </tr>
              <tr>
                <td style="white-space: nowrap; padding-right: 10px;">
                  <label class="form-label">Status</label>
                </td>
                <td style="border-bottom: 1px dashed #f1f1f1;">
                  <select name="status" id="status" class="form-select" required>
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
            </table>
            <div class="col-12 text-end">
              <button type="submit" name="add_vehicle" id="addBtn" class="btn btn-primary px-2"><i class="fas fa-save"></i> Save Destination</button>
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
          <h6><strong>Destination List</strong></h6>
          <div class="table-responsive">
            <table class="styled-table" id="routeTable">
              <thead>
                <tr>
                  <th>#</th>
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
                <?php if (count($routes) === 0): ?>
                  <tr><td colspan="9" class="text-center text-muted">No routes found.</td></tr>
                <?php else: ?>
                  <?php foreach ($routes as $i => $v): ?>
                    <tr data-id="<?= $v['id'] ?>" 
                        data-area="<?= htmlspecialchars($v['area']) ?>" 
                        data-route="<?= htmlspecialchars($v['route']) ?>" 
                        data-origin="<?= htmlspecialchars($v['origin']) ?>" 
                        data-destination="<?= htmlspecialchars($v['destination']) ?>" 
                        data-distance_km="<?= htmlspecialchars($v['distance_km']) ?>" 
                        data-fuel_allocation="<?= htmlspecialchars($v['fuel_allocation']) ?>" 
                        data-status="<?= htmlspecialchars($v['status']) ?>" 
                        data-remarks="<?= htmlspecialchars($v['remarks']) ?>">
                      <td><?= $i + 1 ?></td>
                      <td><?= htmlspecialchars($v['area']) ?></td>
                      <td><?= htmlspecialchars($v['route']) ?></td>
                      <td><?= htmlspecialchars($v['origin']) ?></td>
                      <td><?= htmlspecialchars($v['destination']) ?></td>
                      <td><?= htmlspecialchars($v['distance_km']) ?></td>
                      <td><?= htmlspecialchars($v['fuel_allocation']) ?></td>
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

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script>

if ($.fn.DataTable.isDataTable('#routeTable')) {
  $('#routeTable').DataTable().destroy();
}
$(document).ready(function () {
  const table = $('#routeTable').DataTable({
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


  $('#routeTable').on('draw.dt', function () {
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
  $('#routeTable').trigger('draw.dt');

  // Optional CSS spacing fix
  $('#routeTable_length label select').css({ marginLeft: '5px' });
  const searchLabel = $('#routeTable_filter label');
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

function copyAreaToOrigin() {
    const areaSelect = document.getElementById('area');
    const selectedText = areaSelect.options[areaSelect.selectedIndex].text; // get visible text
    document.getElementById('origin').value = selectedText;
}

// Auto-dismiss alerts
const successAlert = document.getElementById('routeAlertSuccess');
if (successAlert) {
  setTimeout(() => bootstrap.Alert.getOrCreateInstance(successAlert).close(), 3000);
}
const errorAlert = document.getElementById('routeAlertError');
if (errorAlert) {
  setTimeout(() => bootstrap.Alert.getOrCreateInstance(errorAlert).close(), 5000);
}

if (window.history.replaceState) {
  window.history.replaceState(null, null, window.location.href);
}

// Edit vehicle on double click
[...document.querySelectorAll('#routeTable tbody tr')].forEach(row => {
  row.addEventListener('dblclick', () => {
    document.getElementById('route_id').value = row.dataset.id;
    document.getElementById('area').value = row.dataset.area;
    document.getElementById('route_input').value = row.dataset.route;
    document.getElementById('origin').value = row.dataset.origin;
    document.getElementById('destination').value = row.dataset.destination;
    document.getElementById('distance_km').value = row.dataset.distance_km;
    document.getElementById('fuel_allocation').value = row.dataset.fuel_allocation;
    document.getElementById('status').value = row.dataset.status;
    document.getElementById('remarks').value = row.dataset.remarks;

    document.getElementById('formTitle').textContent = 'Edit Destination';
    document.getElementById('addBtn').classList.add('d-none');
    document.getElementById('updateBtn').classList.remove('d-none');
    document.getElementById('cancelEditBtn').classList.remove('d-none');
    document.getElementById('form_mode').value = 'edit';
  });
});

// Cancel edit
const cancelBtn = document.getElementById('cancelEditBtn');
cancelBtn.addEventListener('click', () => {
  document.getElementById('routeForm').reset();
  document.getElementById('route_id').value = '';
  document.getElementById('formTitle').textContent = 'Add New Destination';
  document.getElementById('addBtn').classList.remove('d-none');
  document.getElementById('updateBtn').classList.add('d-none');
  cancelBtn.classList.add('d-none');
  document.getElementById('form_mode').value = 'add';
});

// Escape key handler
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    const updateBtn = document.getElementById('updateBtn');
    const isEditing = updateBtn && getComputedStyle(updateBtn).display !== 'none';
    if (isEditing) {
      cancelBtn.click();
    } else {
      window.location.href = 'add_slip.php';
    }
    e.preventDefault();
  }
}, true);

</script>


</body>
</html>
