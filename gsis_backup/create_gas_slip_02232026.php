<?php
require_once 'includes/auth.php';
require_once 'includes/gas_slip_data.php';

$success = isset($_GET['success']);
$error   = $_GET['error'] ?? '';

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
      <form id="gasSlipForm" method="post" action="save_gas_slip.php">
        <div class="panel-container">
            <div class="page-header mb-2">
            <h1 class="mb-1">Create Gas Slip</h1>
            <p class="page-subtitle mb-1">
                Enter gas slip details directly in the table below. Each row represents one gas slip.
            </p>
            <br>
            
            <div class="text-muted small">
                <strong>Date Issued:</strong> <?= date('F d, Y') ?>
            </div>
            </div>

            <table class="styled-table excel-table" id="gasSlipGrid">

                <thead>
                <tr class="group-header">
                <th rowspan="2">#</th>

                <th colspan="3">Slip Details</th>
                <th colspan="1">Vehicle</th>
                <th rowspan="2">Destinations</th>
                <th rowspan="2">Fuel Request</th>
                </tr>

                <tr class="sub-header">
                <th>Validity Until</th>
                <th>Purpose</th>
                <th>Requested By</th>

                <th>Plate / Brand / Model</th>
                </tr>
                </thead>

                <tbody>
                <tr>
                <td class="text-center row-index">
                  <span class="row-number">1</span>
                  <button type="button"
                          class="btn btn-sm text-danger remove-row"
                          title="Remove row"
                          style="visibility:hidden; font-weight: 800;">
                    ✕
                  </button>
                </td>

                <!-- SLIP DETAILS -->
                <td><input type="date" name="validity_until[]" class="table-input"></td>
                <td><input type="text" name="purpose[]" class="excel-input"></td>
                <td><input type="text" name="requested_by[]" class="excel-input"></td>

                <!-- VEHICLE -->
                <td class="vehicle-cell">
                  <input type="hidden" name="vehicle_id[]">
                  <input type="text"
                        class="excel-input vehicle-display"
                        placeholder="Select Vehicle"
                        readonly>
                  <input type="hidden"
                        class="vehicle-efficiency">
                </td>

                <!-- DESTINATIONS -->
                <td class="text-center destination-cell">
                <input type="hidden" name="destinations[]" class="destinations-data">
                <button type="button" class="btn btn-sm btn-outline-primary">
                    👁 View (<span class="dest-count">0</span>)
                </button>
                </td>

                <!-- FUEL REQUEST -->
                <td class="text-center fuel-cell">

                  <!-- REQUIRED: must default to [] -->
                  <input type="hidden"
                        name="fuel_requests[]"
                        class="fuel-data"
                        value="[]">

                  <button type="button"
                          class="btn btn-sm btn-outline-primary fuel-btn">
                    👁 View (<span class="fuel-count">0</span>)
                  </button>

                </td>

                </tr>
                </tbody>

                <tfoot>
                  <tr>
                    <td colspan="7">
                      <div class="d-flex align-items-center justify-content-between">
                        <!-- Left -->
                        <button type="button"
                                id="btnAddRow"
                                class="btn btn-sm btn-outline-primary">
                          <i class="fas fa-plus"></i> Add Row
                        </button>

                        <!-- Right -->
                        <button type="submit"
                                id="btnSave"
                                class="btn btn-primary">
                          Save Gas Slip
                        </button>
                      </div>
                    </td>

                  </tr>
                </tfoot>

            </table>



        </div>
      </form>
    </main>

</div>

<!-- /* ================================ 
   VEHICLE SELECTION MODAL
================================ */-->
<div class="modal fade" id="vehicleModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Select Vehicle</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <!-- Filters -->
        <div class="d-flex gap-2 mb-3">
          <select id="filterCategory" class="form-select">
            <option value="">All Categories</option>
            <option value="4-wheels">4-wheels</option>
            <option value="2-wheels">2-wheels</option>
            <option value="trucks">Trucks</option>
          </select>

          <select id="filterOwnership" class="form-select">
            <option value="">All Ownership</option>
            <option value="coop-owned">Coop-owned</option>
            <option value="private">Private</option>
          </select>

          <input type="text" id="vehicleSearch"
                 class="form-control"
                 placeholder="Search vehicle">
        </div>

        <!-- Vehicle Table -->
        <table class="styled-table w-100" id="vehicleTable">
          <thead>
            <tr>
              <th style="width:40px">#</th>
              <th>Plate</th>
              <th>Brand</th>
              <th>Model</th>
              <th>Category</th>
              <th>Ownership</th>
              <th>Fuel Eff. (Km/L)</th>
            </tr>
          </thead>
          <tbody>

            <?php foreach ($vehicle_options as $v): ?>
              <tr
                data-category="<?= $v['category'] ?>"
                data-ownership="<?= $v['ownership'] ?>"
                data-idling-rate="<?= $v['idling_rate'] ?>"
                onclick="selectVehicleFromModal(
                  this,
                  <?= (int)$v['id'] ?>,
                  '<?= htmlspecialchars($v['plate_no']) ?>',
                  '<?= htmlspecialchars($v['brand']) ?>',
                  '<?= htmlspecialchars($v['model']) ?>',
                  '<?= $v['km_per_liter'] ?>',
                  '<?= $v['category'] ?>'
                )"
                style="cursor:pointer"
              >
                <td class="row-number"></td>
                <td><?= htmlspecialchars($v['plate_no']) ?></td>
                <td><?= htmlspecialchars($v['brand']) ?></td>
                <td><?= htmlspecialchars($v['model']) ?></td>
                <td><?= htmlspecialchars($v['category']) ?></td>
                <td><?= htmlspecialchars($v['ownership']) ?></td>
                <td><?= htmlspecialchars($v['km_per_liter']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

      </div>
    </div>
  </div>
</div>

<!-- ================================
     DESTINATIONS MODAL
================================ -->
<div class="modal fade" id="destinationsModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Select Destinations</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

      <!-- Origin -->
      <div class="mb-3 d-flex align-items-center gap-2">
        <!-- <strong class="text-nowrap fs-5">Origin:</strong> -->
        <h6 class="text-nowrap mb-2">Origin:</h6>
        <span id="destOriginDisplay"
              class="origin-text fs-5 fw-semibold"></span>
      </div>


        <!-- Selected Destinations -->
        <div class="mb-3">
          <!-- <h6 class="mb-2">Selected Destinations</h6> -->

            <table class="styled-table excel-table"
                  id="selectedDestinationsTable">
              <thead class="table-light">
                <tr>
                  <th style="width:40px">#</th>
                  <th>Destination</th>
                  <th>KM</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colspan="3"
                      class="text-center text-muted">
                    No destinations selected
                  </td>
                </tr>
              </tbody>
            </table>
          
        </div>
        
        <!-- Header row -->
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h6 class="mb-0 text-nowrap">Available Destinations:</h6>

          <div class="form-check mb-0">
            <input
              class="form-check-input"
              type="checkbox"
              id="useRoutes"
            >
            <label class="form-check-label" for="useRoutes">
              Use approved routes
            </label>
          </div>
        </div>

        <!-- Destination list -->
        <div id="destinationList"
            class="border rounded p-2"
            style="max-height:260px; overflow:auto;">
          <!-- checkboxes injected here -->
        </div>

      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          Cancel
        </button>
        <button type="button" class="btn btn-primary" id="saveDestinationsBtn">
          Save Destinations
        </button>
      </div>

    </div>
  </div>
</div>

<!-- ===================== -->
<!-- 🔥 FUEL REQUEST MODAL -->
<!-- ===================== -->
<div class="modal fade" id="fuelModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Fuel Requests</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <table class="styled-table excel-table">
          <thead class="table-light">
            <tr>
              <th style="width:40px">#</th>
              <th>Fuel Item</th>
              <th style="width:120px">Qty</th>
              <th style="width:100px">Container</th>
              <th style="width:80px">Action</th>
            </tr>
          </thead>

          <tbody id="fuelRequestBody">
            <!-- rows injected by JS -->
          </tbody>
        </table>

        <button type="button"
                class="btn btn-sm btn-outline-success mt-2"
                id="addFuelRowBtn"
                onclick="addFuelRow()">
          + Add Fuel Row
        </button>

      </div>

      <div class="modal-footer">
        <button type="button"
                class="btn btn-secondary"
                data-bs-dismiss="modal">
          Cancel
        </button>
        <button type="button"
                class="btn btn-primary"
                id="saveFuelBtn">
          Save Fuel Requests
        </button>
      </div>

    </div>
  </div>
</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>

<script>
  window.APP = {
    USER_ORIGIN: <?= json_encode($_SESSION['area'] ?? '') ?>
  };

  window.APP_FUEL_ITEMS = <?= json_encode(
    is_array($fuel_items)
      ? array_map(function ($f) {
          return [
            'id'        => (int) ($f['id'] ?? 0),
            'name'      => (string) ($f['name'] ?? ''),
            'unit'      => (string) ($f['unit'] ?? ''),
            'container' => strtolower($f['container'] ?? '') === 'yes'
          ];
        }, $fuel_items)
      : []
  ) ?>;
</script>

<script src="assets/js/sidebar.js"></script>
<script src="assets/js/grid.js"></script>
<script src="assets/js/destinations.js"></script>
<script src="assets/js/fuel_requests.js"></script>
<script src="assets/js/vehicles.js"></script>
<script src="assets/js/sweetalert2.min.js"></script>

<script>
  // ===============================
  // FORM SAFETY GUARDS (SWEETALERT)
  // ===============================
  document.getElementById('destOriginDisplay').textContent = USER_ORIGIN;

  const form = document.getElementById('gasSlipForm');
  const saveBtn = document.getElementById('btnSave');

  if (form && saveBtn) {
    let isDirty = false;

    // Mark form as dirty
    form.querySelectorAll('input, select, textarea').forEach(el => {
      el.addEventListener('change', () => isDirty = true);
    });

    // Leave warning
    window.addEventListener('beforeunload', (e) => {
      if (!isDirty) return;
      e.preventDefault();
      e.returnValue = '';
    });

    // Submit handler
    form.addEventListener('submit', (e) => {
      if (saveBtn.dataset.confirmed === 'true') return;

      e.preventDefault();

      let errors = [];
      const rows = document.querySelectorAll('#gasSlipGrid tbody tr');

      rows.forEach((row, index) => {

        const rowNum = index + 1;

        const vehicle = row.querySelector('[name="vehicle_id[]"]')?.value;
        const destData = row.querySelector('.destinations-data')?.value;
        const purpose = row.querySelector('[name="purpose[]"]')?.value.trim();
        const requestedBy = row.querySelector('[name="requested_by[]"]')?.value.trim();
        const fuelData = row.querySelector('.fuel-data')?.value;

        const fuelItems = fuelData ? JSON.parse(fuelData) : [];
        const destinations = destData ? JSON.parse(destData) : [];

        let rowErrors = [];

        if (!vehicle) rowErrors.push('Vehicle');
        if (destinations.length === 0) rowErrors.push('Destination');
        if (!purpose) rowErrors.push('Purpose');
        if (!requestedBy) rowErrors.push('Requested By');
        if (fuelItems.length === 0) rowErrors.push('Fuel Items');

        if (rowErrors.length > 0) {
          errors.push(`Row ${rowNum}: ${rowErrors.join(', ')}`);
          row.classList.add('table-danger');
        } else {
          row.classList.remove('table-danger');
        }

      });

      if (errors.length > 0) {
        Swal.fire({
          icon: 'error',
          title: 'Incomplete Gas Slip',
          html: errors.join('<br>')
        });
        return;
      }

      // Confirmation dialog
      Swal.fire({
        title: 'Save Gas Slip?',
        text: 'Please confirm that all details are correct.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Save',
        cancelButtonText: 'Review',
        reverseButtons: true,
        focusCancel: true
      }).then(result => {
        if (result.isConfirmed) {
          saveBtn.dataset.confirmed = 'true';
          saveBtn.disabled = true;
          isDirty = false;
          form.submit();
        }
      });

    });
  }


  document.getElementById('btnAddRow').addEventListener('click', () => {
    const tbody = document.querySelector('#gasSlipGrid tbody');
    const rows  = tbody.querySelectorAll('tr');
    const newRow = rows[0].cloneNode(true);

    // clear inputs
    newRow.querySelectorAll('input').forEach(i => i.value = '');

    // ✅ RESET counters
    const destCount = newRow.querySelector('.dest-count');
    if (destCount) destCount.textContent = '0';

    const fuelCount = newRow.querySelector('.fuel-count');
    if (fuelCount) fuelCount.textContent = '0';

    // Optional: clear hidden JSON fields if you use them
    newRow.querySelectorAll('input[type="hidden"]').forEach(i => i.value = '');

    // Auto set validity
    setValidityPlusOne(newRow);

    tbody.appendChild(newRow);
    renumberRows();
  });

  function setValidityPlusOne(row) {
    const issuedInput   = row.querySelector('input[name="date_issued[]"]');
    const validityInput = row.querySelector('input[name="validity_until[]"]');

    if (!validityInput) return;

    const baseDate = issuedInput?.value
      ? new Date(issuedInput.value)
      : new Date();

    baseDate.setDate(baseDate.getDate() + 1);
    validityInput.value = baseDate.toISOString().split('T')[0];
  }

  function renumberRows() {
    const rows = document.querySelectorAll('#gasSlipGrid tbody tr');

    rows.forEach((row, index) => {
      row.querySelector('.row-number').textContent = index + 1;

      const removeBtn = row.querySelector('.remove-row');
      removeBtn.style.visibility = index === 0 ? 'hidden' : 'visible';
    });
  }


  document.addEventListener('click', function (e) {
    if (!e.target.classList.contains('remove-row')) return;

    const tbody = document.querySelector('#gasSlipGrid tbody');
    const rows  = tbody.querySelectorAll('tr');

    if (rows.length === 1) return;

    e.target.closest('tr').remove();
    renumberRows();
  });


</script>

</body>
</html>
