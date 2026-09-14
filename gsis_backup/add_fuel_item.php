<?php
require_once 'includes/config.php';

$conn = getDBConnection();
$error = '';
$success = '';
$duplicate_js_alert = '';

// if (isset($_GET['delete'])) {
//     $id = intval($_GET['delete']);
//     $stmt = $conn->prepare("DELETE FROM fuel_items WHERE id = ?");
//     $stmt->bind_param("i", $id);
//     if ($stmt->execute()) {
//         $success = "Fuel item deleted.";
//     } else {
//         $error = "Error deleting fuel item: " . $stmt->error;
//     }
// }

// new code to handle edit mode
// Add or update vehicle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['fuel_name']);
    $unit = trim($_POST['unit']);
    $container = trim($_POST['container']);
    $status = trim($_POST['status']);
    $remarks = trim($_POST['remarks']);

    if ($name && $unit && $container && $status) {
        if (isset($_POST['form_mode']) && $_POST['form_mode'] === 'edit' && isset($_POST['fuelitem_id']) && $_POST['fuelitem_id'] !== '') {
            $id = intval($_POST['fuelitem_id']);
            
            $check = $conn->prepare("SELECT id FROM fuel_items WHERE name = ? AND id != ?");
            $check->bind_param("si", $name, $id);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = "Another vehicle with the same plate number already exists.";
            } else {
                $stmt = $conn->prepare("UPDATE fuel_items SET name=?, unit=?, container=?, status=?, remarks=? WHERE id=?");
                $stmt->bind_param("sssssi", $name, $unit, $container, $status, $remarks, $id);
                if ($stmt->execute()) {
                    $success = true;
                } else {
                    $error = "Error updating vehicle: " . $stmt->error;
                }
            }
        } else {
            $check = $conn->prepare("SELECT id FROM fuel_items WHERE name = ?");
            $check->bind_param("s", $name);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = "Another fuel item with the same name already exists.";
            } else {
                $stmt = $conn->prepare("INSERT INTO fuel_items (name, unit, container, status, remarks) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $name, $unit, $container, $status, $remarks);
                if ($stmt->execute()) {
                    $success = true;
                } else {
                    $error = "Error adding fuel item: " . $stmt->error;
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

// Check if we are in edit mode




// Fetch all fuel items
$fuel_items = [];
$result = $conn->query("SELECT * FROM fuel_items ORDER BY name ASC");
while ($row = $result->fetch_assoc()) {
    $fuel_items[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Fuel Item</title>
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
    <div id="fuelitemAlertSuccess" class="alert alert-success alert-dismissible fade show" role="alert">
      Fuel Item saved successfully.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php elseif ($error): ?>
    <div id="fuelitemAlertError" class="alert alert-danger alert-dismissible fade show" role="alert">
      <?= $error ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>
  
  <div class="row">
    <div class="col-lg-3 col-md-7 col-sm-12 mb-4">
      <div class="card rounded-4">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 id="formTitle"><strong>Add Fuel Item</strong></h6>
            <button type="button" id="cancelEditBtn" class="btn btn-warning px-2 d-none"><i class="fa fa-ban"></i> Cancel Edit</button>
          </div>

          <form method="POST" class="mb-4" id="fuelitemForm">
            <input type="hidden" name="form_mode" id="form_mode" value="add">
            <input type="hidden" name="fuelitem_id" id="fuelitem_id">
            <table class="table borderless w-100" style="max-width: 600px;">
              <tr>
                <td style="white-space: nowrap; padding-right: 10px;">
                    <label class="form-label">Fuel Name</label>
                </td>
                <td style="border-bottom: 1px dashed #f1f1f1;">
                  <input type="text" name="fuel_name" id="fuel_name" class="form-control" placeholder="Fuel Name" required autofocus>
                </td>
              </tr>
              <tr>
                <td style="white-space: nowrap; padding-right: 10px;">
                    <label class="form-label">Unit (e.g. L, pcs)</label>
                </td>
                <td style="border-bottom: 1px dashed #f1f1f1;">
                  <input type="text" name="unit" id="unit" class="form-control" placeholder="Unit (e.g. L, pcs)" required>
                </td>
              </tr>
              <tr>
                <td style="white-space: nowrap; padding-right: 10px;">
                    <label class="form-label">Container</label>
                </td>
                <td style="border-bottom: 1px dashed #f1f1f1;">
                  <select name="container" id="container" class="form-select" required>
                    <option value="" disabled selected>-- Select --</option>
                    <option value="yes">Applicable</option>
                    <option value="no">Not Applicable</option>
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
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
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
              <!-- <button type="submit" name="add_route" id="addRoute" class="btn btn-primary px-4">Add Route</button> -->
              <button type="submit" name="add_fuelitem" id="addBtn" class="btn btn-primary px-2"><i class="fas fa-save"></i> Save Item</button>
              <button type="submit" name="update_fuelitem" id="updateBtn" class="btn btn-success px-2 d-none"><i class="fas fa-save"></i> Save Changes</button>
              <a href="add_slip.php" class="btn btn-secondary px-2"><i class="fa fa-reply-all"></i> Back</a>
            </div>
          </form>

        </div>
      </div>
    </div>
    <div class="col-lg-9 col-md-5 col-sm-12 mb-4">
      <div class="card rounded-4">
        <div class="card-body">
          <h6><strong>Fuel Items</strong></h6>
          <div class="table-responsive">
            <table class="styled-table" id="fuelitemTable">
              <thead>
                <tr>
                  <th style="width: 5%;">#</th>
                  <th style="width: 35%;">Fuel Name</th>
                  <th style="width: 10%;">Unit</th>
                  <th style="width: 20%;">Container</th>
                  <th style="width: 10%;">Status</th>
                  <th style="width: 20%;">Remarks</th>
                  <!-- <th style="width: 10%;">Action</th> -->
                </tr>
              </thead>
              <tbody>
                <?php if (empty($fuel_items)): ?>
                  <tr><td colspan="4" class="text-center text-muted">No fuel items found.</td></tr>
                <?php else: ?>
                  <?php foreach ($fuel_items as $index => $item): ?>
                    <tr data-id="<?= $item['id'] ?>"
                        data-name="<?= htmlspecialchars($item['name']) ?>"
                        data-unit="<?= htmlspecialchars($item['unit']) ?>" 
                        data-container="<?= htmlspecialchars($item['container']) ?>" 
                        data-status="<?= htmlspecialchars($item['status']) ?>" 
                        data-remarks="<?= htmlspecialchars($item['remarks']) ?>" 
                      >
                      <td><?= $index + 1 ?></td>
                      <td><?= htmlspecialchars($item['name']) ?></td>
                      <td><?= htmlspecialchars($item['unit']) ?></td>
                      <td>
                          <?= (strtolower($item['container']) === 'yes') ? 'Applicable' : 'Not Applicable' ?>
                      </td>
                      <td><?= htmlspecialchars($item['status']) ?></td>
                      <td><?= htmlspecialchars($item['remarks']) ?></td>
                      <!-- <td>
                        <a href="?delete=<?= $item['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this item?')">Delete</a>
                      </td> -->
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

<?php if ($duplicate_js_alert): ?>
<script><?= $duplicate_js_alert ?></script>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script>
  if ($.fn.DataTable.isDataTable('#fuelitemTable')) {
    $('#fuelitemTable').DataTable().destroy();
  }
  $(document).ready(function () {
    const table = $('#fuelitemTable').DataTable({
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


    $('#fuelitemTable').on('draw.dt', function () {
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
    $('#fuelitemTable').trigger('draw.dt');

    // Optional CSS spacing fix
    $('#fuelitemTable_length label select').css({ marginLeft: '5px' });
    const searchLabel = $('#fuelitemTable_filter label');
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

//Edit vehicle on double click
[...document.querySelectorAll('#fuelitemTable tbody tr')].forEach(row => {
  row.addEventListener('dblclick', () => {
    document.getElementById('fuel_name').value = row.dataset.name;
    document.getElementById('unit').value = row.dataset.unit;
    document.getElementById('container').value = row.dataset.container;
    document.getElementById('status').value = row.dataset.status;
    document.getElementById('remarks').value = row.dataset.remarks;
    document.getElementById('fuelitem_id').value = row.dataset.id; // <-- IMPORTANT

    document.getElementById('formTitle').textContent = 'Edit Fuel Item';
    document.getElementById('addBtn').classList.add('d-none');
    document.getElementById('updateBtn').classList.remove('d-none');
    document.getElementById('cancelEditBtn').classList.remove('d-none');
    document.getElementById('form_mode').value = 'edit';
  });
});


// Cancel edit
const cancelBtn = document.getElementById('cancelEditBtn');
cancelBtn.addEventListener('click', () => {
  document.getElementById('fuelitemForm').reset();
  document.getElementById('fuelitem_id').value = '';
  document.getElementById('formTitle').textContent = 'Add Fuel Item';
  document.getElementById('addBtn').classList.remove('d-none');
  document.getElementById('updateBtn').classList.add('d-none');
  cancelBtn.classList.add('d-none');
  document.getElementById('form_mode').value = 'add';
});

// Auto-dismiss alerts
const successAlert = document.getElementById('fuelitemAlertSuccess');
if (successAlert) {
  setTimeout(() => bootstrap.Alert.getOrCreateInstance(successAlert).close(), 3000);
}
const errorAlert = document.getElementById('fuelitemAlertError');
if (errorAlert) {
  setTimeout(() => bootstrap.Alert.getOrCreateInstance(errorAlert).close(), 5000);
}

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
</script>

</body>
</html>
