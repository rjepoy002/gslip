<?php
require_once 'includes/config.php';
$success = false;
$error = '';

$conn = getDBConnection();

// Always start the session before using $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// echo "<pre>";
// print_r($_POST);
// echo "</pre>";
// exit;

// Fetch fuel items
$fuel_items = [];
$result = $conn->query("SELECT id, name, unit, container FROM fuel_items ORDER BY name ASC");
while ($row = $result->fetch_assoc()) {
    $fuel_items[$row['id']] = [
        'name' => $row['name'],
        'unit' => $row['unit'],
        'container' => $row['container']
    ];
}

// Fetch vehicles
$vehicle_options = [];
$result = $conn->query("SELECT id, plate_no, brand, model, km_per_liter, idling_rate, category, ownership FROM vehicles WHERE status = 'active'");
while ($row = $result->fetch_assoc()) {
    $vehicle_options[] = $row;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->begin_transaction();

    try {
        // Collect all form data
        //gas_slips table for input
        // Fetch user ID from session
        
        $user_id = $_SESSION['user_id']; // Example user ID, replace with actual session data
        $area = $_SESSION['area'] ?? 'Puerto Princesa [Main Office]'; // fallback if not set
        $formatted_user_id = str_pad($user_id, 2, '0', STR_PAD_LEFT); // Pad user ID to 2 digits

        $date_issued = DateTime::createFromFormat('m/d/Y', $_POST['date_issued'])->format('Y-m-d');
        $validity_until = $_POST['validity_until'];
        $vehicle_id = intval($_POST['vehicle_id']);
        $purpose = $_POST['purpose'];
        $requested_by = $_POST['requested_by'];
        $status = 'pending';

        // Fuel request arrays
        $fuel_item_ids = $_POST['fuel_item_id'] ?? [];
        $fuel_qtys = $_POST['quantity'] ?? [];
        $containers = $_POST['container'] ?? [];

        // === Insert into gas_slips table ===
        // Take the first destination as the "primary route_id"
        $primary_route_id = (!empty($_POST['destination'][0])) ? intval($_POST['destination'][0]) : null;

        // Debug: check values before insert
        // echo "<script>
        // alert('Primary Route ID: " . ($primary_route_id ?? 'NULL') . "\\n" .
        //       "Purpose: " . addslashes($purpose) . "\\n" .
        //       "Requested By: " . addslashes($requested_by) . "');
        // </script>";
            
        $stmt = $conn->prepare("INSERT INTO gas_slips 
            (date_issued, validity_until, vehicle_id, route_id, purpose, requested_by, area, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiissss", $date_issued, $validity_until, $vehicle_id, $primary_route_id, $purpose, $requested_by, $area, $status);

        if (!$stmt->execute()) {
            throw new Exception("Error inserting gas slip: " . $stmt->error);
        }

        $last_insert_id = $stmt->insert_id;

        // Create custom gas slip ID
        $formatted_id = str_pad($last_insert_id, 5, '0', STR_PAD_LEFT);
        $year = date('Y');
        $gas_slip_custom_id = "$year-$formatted_user_id-$formatted_id";

        // === Update gas_slip_id in main table ===
        $update_stmt = $conn->prepare("UPDATE gas_slips SET gas_slip_id = ? WHERE id = ?");
        $update_stmt->bind_param("si", $gas_slip_custom_id, $last_insert_id);
        $update_stmt->execute();

        // === Insert destinations into gas_slip_routes ===
        if (!empty($_POST['destination']) && is_array($_POST['destination'])) {
            $stmt_dest = $conn->prepare("INSERT INTO gas_slip_routes (gas_slip_id, route_id, sequence_no) VALUES (?, ?, ?)");
            foreach ($_POST['destination'] as $seq => $route_id) {
                if (!empty($route_id)) {
                    $seqNo = $seq + 1;
                    $stmt_dest->bind_param("iii", $last_insert_id, $route_id, $seqNo);
                    if (!$stmt_dest->execute()) {
                        throw new Exception("Error inserting route: " . $stmt_dest->error);
                    }
                }
            }
        }

        $stmt_fuel = $conn->prepare("INSERT INTO fuel_requests (gas_slip_id, fuel_item_id, quantity, container) VALUES (?, ?, ?, ?)");
        
        foreach ($fuel_item_ids as $index => $fuel_item_id) {
            // $qty = floatval($fuel_qtys[$index] ?? 0);
            $rawQty = trim($fuel_qtys[$index] ?? '');
    
            if (strtoupper($rawQty) === 'FT') {
                $qty = 'FT';
            } elseif (is_numeric($rawQty)) {
                $qty = floatval($rawQty);
            } else {
                $qty = 0; // or handle invalid input
            }
            $hasContainer = in_array($index, array_keys($containers)) ? 'Yes' : 'No'; // checkbox value

            if ($fuel_item_id && ($qty === 'FT' || $qty > 0)) {
                $stmt_fuel->bind_param("siss", $gas_slip_custom_id, $fuel_item_id, $qty, $hasContainer);
                if (!$stmt_fuel->execute()) {
                    throw new Exception("Error inserting fuel request: " . $stmt_fuel->error);
                }
            }
        }

        $conn->commit();
        $success = true;
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>GSIS - Gas Slip Issuance System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="includes/style.css">
  <!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> -->
   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  
</head>
<body>
<div class="container py-4">

<?php
include 'header.php';
?>

  <?php if ($success): ?>
  <div id="addslipAlertSuccess" class="alert alert-success alert-dismissible fade show" role="alert">
      Gas Slip submitted successfully.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php elseif ($error): ?>
    <div id="addslipAlertError" class="alert alert-danger alert-dismissible fade show" role="alert">
      <?= $error ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <form method="POST">
    <div class="row g-3 align-items-stretch">
      <!-- Left Column -->
      <div class="col-md-3">
        <div class="bg-white p-3 rounded shadow-sm mb-3" style="height: 545px;">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0 mt-2"><strong>1. Slip Details</strong></h6>
          </div>
          
          <div class="mb-3">
            <label class="form-label mt-2">Date</label>
            <input type="input" name="date_issued" id="date_issued" class="form-control" required value="<?= date('m/d/Y') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Validity Until</label>
            <input type="date" name="validity_until" id="validity_until" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Purpose</label>
            <textarea name="purpose" class="form-control" rows="5" required></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Requested By</label>
            <input type="text" name="requested_by" class="form-control" required>
          </div>
        </div>
      </div>

      <!-- Middle Column -->
      <div class="col-md-3">
        <div class="bg-white p-3 rounded shadow-sm mb-3">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0"><strong>2. Vehicle</strong></h6>
            <!-- <a href="add_vehicle.php" class="btn btn-link btn-sm mt-2">+ Add New <b>V</b>ehicle</a> -->
            <?php if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="add_vehicle.php" class="btn btn-link btn-sm mt-2">+ Add New <b>V</b>ehicle</a>
            <?php endif; ?>
          </div>

          <!-- Vehicle Display -->
          <div class="mt-2">
            <label for="vehicle_display" class="form-label mb-1" style="font-size: 13px;">Plate No. / Brand / Model:</label>
            <input type="hidden" name="vehicle_id" id="vehicle_id">
            <input type="hidden" id="vehicle_category" name="vehicle_category">
            <input type="hidden" id="vehicle_idling_rate" name="vehicle_idling_rate">
            <input type="text" class="form-control" id="vehicle_display" placeholder="-- Select Vehicle --" style="background-color: #ffffffff; color: #333;" readonly>
          </div>
        
          <!-- KM per Liter Display -->
          <div class="mt-2">
            <label for="km_per_liter" class="form-label mb-1" style="font-size: 13px;">Fuel Efficiency (km/L):</label>
            <input type="text" id="km_per_liter" class="form-control" placeholder="--.--" disabled style="background-color: #ffffffff; color: #333;">
          </div>

          <button type="button" class="btn btn-outline-secondary w-100 mt-2" data-bs-toggle="modal" data-bs-target="#vehicleModal" style="font-size: 14px;">
            Select Vehicle
          </button>
        </div>

        <div class="bg-white p-3 rounded shadow-sm mb-3" style="height: 380px;">
 
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0"><strong>3. Destination</strong></h6>
            <!-- <a href="add_route.php" class="btn btn-link btn-sm mt-2">+ Add New <b>D</b>estination</a> -->
            <?php if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="add_route.php" class="btn btn-link btn-sm mt-2">+ Add New <b>D</b>estination</a>
            <?php endif; ?>
          </div>
          <div class="form-check mb-3">
            <label class="form-check-label small">
              <input class="form-check-input" type="checkbox" id="useRouteCheckbox"> Use Route
            </label>
          </div>

          <div id="routeSelections">
            <div class="mb-2">
              <select name="origin" id="origin" class="form-select" disabled>
                <option value="">-- Select Origin --</option>
                <!-- Loaded dynamically -->
              </select>
            </div>

              <!-- Destinations container -->
            <div id="destinationsContainer">
              <div class="mb-2 destination-row">
                <select name="destination[]" class="form-select destination" required>
                  <option value="">-- Select Destination --</option>
                </select>
              </div>
            </div>

            <button type="button" id="addDestination" class="btn btn-sm btn-outline-secondary">
              + Add Destination
            </button>

            <div class="mb-3">
              <input type="hidden" name="route_id" id="route_id">
              <label class="form-label">Estimated Distance</label>
              <input type="text" class="form-control" id="distance" placeholder="--.--" readonly>
              <p style="display:none"><span id="fuel_allocation">--.--</span></p>
            </div>

          </div>

        </div>
      </div>

      <!-- Right Column -->
      <div class="col-md-6">
        <div class="bg-white p-3 rounded shadow-sm mb-3">
          
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0"><strong>4. Fuel Requests</strong></h6>
            <!-- <a href="add_fuel_item.php" class="btn btn-link btn-sm">+ Add New Fuel Item</a> -->
            <?php if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="add_fuel_item.php" class="btn btn-link btn-sm">+ Add New Fuel Item</a>
            <?php endif; ?>
          </div>

          <table class="styled-table" id="fuelTable">
            <thead>
              <tr>
                <th>Item</th>
                <th>Qty</th>
                <th>Unit</th>
                <th>Container</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody id="fuelBody"></tbody>
          </table>
          <button type="button" class="btn btn-outline-secondary w-100 mt-2" id="addFuelBtn" onclick="addFuelRow()" style="font-size: 14px;" disabled><b>+</b> Add Items</button>
          <div class="text-end mt-3 d-flex gap-2 justify-content-end">
            <button type="submit" class="btn btn-primary px-2"><i class="fa fa-paper-plane"></i> Submit</button>
            <a href="main.php" class="btn btn-secondary px-2"><i class="fa fa-reply-all"></i> Back</a>
          </div>
        </div>
      </div>
    </div>

  </form>
</div>

<!-- Vehicle Modal -->
<div class="modal fade" id="vehicleModal" aria-labelledby="vehicleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="vehicleModalLabel">Select Vehicle</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" id="vehicleModalClose" aria-label="Close"></button>
      </div>
      <div class="modal-body">

        <!-- Dropdown Filters -->
        <div class="filter-row" style="margin-bottom: 15px; display: flex; gap: 10px;">
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
        </div>

        <!-- Search Input -->
        <input type="text" id="vehicleSearch" class="form-control mb-3" placeholder="Search by Plate No, Brand, or Model">

        <table class="styled-table" id="vehicleTable">
          <thead>
            <tr>
              <th>Plate No</th>
              <th>Brand</th>
              <th>Model</th>
              <th>Category</th>
              <th>Ownership</th>
            </tr>
          </thead>
          <tbody>
            <?php
              $conn = getDBConnection();
              $sql = "SELECT * FROM vehicles WHERE status = 'active'";
              $result = mysqli_query($conn, $sql);
              while ($row = mysqli_fetch_assoc($result)):
            ?>
              <tr 
                data-category="<?= $row['category'] ?>" 
                data-ownership="<?= $row['ownership'] ?>"
                data-kmpl="<?= $row['km_per_liter'] ?>"
                data-idling_rate="<?= $row['idling_rate'] ?>"
                data-id="<?= $row['id'] ?>"
                onclick="selectVehicle('<?= $row['id'] ?>', '<?= $row['plate_no'] ?> - <?= $row['brand'] ?> <?= $row['model'] ?>', this.dataset.category, this.dataset.kmpl, this.dataset.idling_rate)"
              >
                <td><?= htmlspecialchars($row['plate_no']) ?></td>
                <td><?= htmlspecialchars($row['brand']) ?></td>
                <td><?= htmlspecialchars($row['model']) ?></td>
                <td><?= htmlspecialchars($row['category']) ?></td>
                <td><?= htmlspecialchars($row['ownership']) ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>

      </div>
    </div>
  </div>
</div>
<script>
const allVehicles = <?= json_encode($vehicle_options) ?>;
const fuelOptions = <?= json_encode($fuel_items) ?>;

document.getElementById('filterCategory').addEventListener('change', filterVehicleTable);
document.getElementById('filterOwnership').addEventListener('change', filterVehicleTable);
document.getElementById('vehicleSearch').addEventListener('input', filterVehicleTable);

// click vehicle modal if input is clicked
document.getElementById('vehicle_display').addEventListener('click', function () {
  document.querySelector('[data-bs-target="#vehicleModal"]').click();
});

document.addEventListener('DOMContentLoaded', function () {
  const useRouteCheckbox   = document.getElementById('useRouteCheckbox');
  const originSelect       = document.getElementById('origin');
  const destinationsCont   = document.getElementById('destinationsContainer');
  const addDestBtn         = document.getElementById('addDestination');
  const distanceInput      = document.getElementById('distance');
  const fuelAllocSpan      = document.getElementById('fuel_allocation');
  const routeIdsInput      = document.getElementById('route_id'); // hidden input to store CSV of matched route ids

   // Get session area from PHP
  const defaultArea = "<?php echo isset($_SESSION['area']) ? htmlspecialchars($_SESSION['area'], ENT_QUOTES) : ''; ?>";

  
  // --- Create a destination row (select + remove button) ---
  function makeDestRow() {
    const row = document.createElement('div');
    row.className = 'mb-2 destination-row d-flex gap-2 align-items-center';
    row.innerHTML = `
      <select name="destination[]" class="form-select destination" required>
        <option value="">-- Select Destination --</option>
      </select>
      <button type="button" class="btn btn-outline-danger btn-sm remove-dest" title="Remove">×</button>
    `;
    return row;
  }

  // --- Recalculate distance/fuel from backend ---
  function recalcDistanceFuel() {
    const origin = originSelect.value;

    // Grab both id and name from dataset
    const destinations = Array.from(destinationsCont.querySelectorAll('select.destination'))
        .map(s => ({
            id: s.value,
            name: s.selectedOptions[0].dataset.name
        }))
        .filter(v => v.id && v.name);
        
        Array.from(destinationsCont.querySelectorAll('select.destination')).forEach(s => {
            console.log('Select element:', s, 'Selected option:', s.selectedOptions[0]);
        });

        console.log('Origin:', origin, 'Destinations:', destinations);

    if (!origin || destinations.length === 0) {
      distanceInput.value = '--.--';
      fuelAllocSpan.textContent = '--.--';
      routeIdsInput.value = '';
      return;
    }

    fetch('get_distance.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ 
          origin, 
          destinations: destinations.map(d => d.name) // send only names
      })
    })

    .then(r => r.json())
    .then(data => {
      
      distanceInput.value = data.total_distance ? parseFloat(data.total_distance).toFixed(2) : '--.--';
      fuelAllocSpan.textContent = data.total_fuel ? parseFloat(data.total_fuel).toFixed(2) : '--.--';
      routeIdsInput.value = (data.route_ids || []).join(',');
    })
    .catch(err => {
      console.error('Error fetching distance/fuel:', err);
      distanceInput.value = '--.--';
      fuelAllocSpan.textContent = '--.--';
      routeIdsInput.value = '';
    });
  }

  // --- Fetch destinations for a given origin + row ---
  function fetchDestinationsForRow(origin, selectEl) {

    console.log("JS Origin BEFORE fetch:", origin); // 👈 check what we pass to PHP

    if (!origin) {
      selectEl.innerHTML = '<option value="">-- Select Destination --</option>';
      recalcDistanceFuel();
      return;
    }

    const url = useRouteCheckbox.checked
      ? `get_destinations.php?origin=${encodeURIComponent(origin)}`
      : `get_non_route_destinations.php?origin=${encodeURIComponent(origin)}`;

    fetch(url)
      .then(r => r.json())
      .then(data => {

          console.log("FULL JSON from PHP:", data); // 👈 log everything
          console.log("Origin received from PHP:", data.debug_origin);
          data.destinations.forEach((d, i) => {
            console.log(`${i+1}. ID=${d.id}, Name=${d.name}`);
          });

        selectEl.innerHTML = '<option value="">-- Select Destination --</option>';
          (data.destinations || []).forEach(d => {
            const opt = document.createElement('option');
            opt.value = d.id; // always keep route ID as value
            opt.dataset.name = d.name; // 👈 This is crucial
            if (useRouteCheckbox.checked) {
              // show route number + name
              opt.textContent = `${String(d.route_info).padStart(4,'0')}\u00A0\u00A0\u00A0\u00A0\u00A0${d.name}`;
            } else {
              // show only name
              opt.textContent = d.name;
            }

            selectEl.appendChild(opt);
          });
        updateDestinationDropdowns();
      })
      .catch(err => console.error('fetchDestinationsForRow error', err));
  }

  // Function to refresh destination dropdowns (exclude already selected)
  function updateDestinationDropdowns() {
    const allSelects = Array.from(destinationsCont.querySelectorAll('select.destination'));
    const selectedDestinations = allSelects.map(s => s.value).filter(Boolean);

    allSelects.forEach(sel => {
      const currentVal = sel.value;
      
      // Preserve BOTH value + label
      const options = Array.from(sel.options).map(o => ({
        value: o.value,
        label: o.textContent,
        name: o.dataset.name || o.textContent
      }));

      sel.innerHTML = '';
      options.forEach(optData => {
        if (optData.value === "" || !selectedDestinations.includes(optData.value) || optData.value === currentVal) {
          const opt = document.createElement('option');
          opt.value = optData.value;
          opt.textContent = optData.label || '-- Select Destination --';
          opt.dataset.name = optData.name; // 👈 preserve data-name
          sel.appendChild(opt);
        }
      });

      sel.value = currentVal;
    });
  }

  // --- When any destination changes ---
  destinationsCont.addEventListener('change', function (e) {
    if (e.target.classList.contains('destination')) {
      recalcDistanceFuel();
    }
  });

  // --- Add a new destination row ---
  addDestBtn.addEventListener('click', function () {
    const row = makeDestRow();
    destinationsCont.appendChild(row);
    fetchDestinationsForRow(originSelect.value, row.querySelector('select.destination'));
    recalcDistanceFuel();
  });

  // --- Remove destination row ---
  destinationsCont.addEventListener('click', function (e) {
    if (e.target.classList.contains('remove-dest')) {
      e.target.closest('.destination-row').remove();
      updateDestinationDropdowns();
      recalcDistanceFuel();
    }
  });

  // --- Fetch origins and initial setup ---
  function fetchOrigins() {
    const url = useRouteCheckbox.checked ? 'routes_fetch.php' : 'non_routes_fetch.php';

    fetch(url)
      .then(r => r.json())
      .then(data => {
        originSelect.innerHTML = '<option value="">-- Select Origin --</option>';
        (data.origins || []).forEach(o => {
          const opt = document.createElement('option');
          opt.value = o;
          opt.textContent = o;

          // Check if this matches session area
          if (o === defaultArea) {
            opt.selected = true;
          }

          originSelect.appendChild(opt);
        });

        // --- Simulate clicking all remove buttons ---
        const removeBtns = document.querySelectorAll('.remove-dest');
        removeBtns.forEach(btn => btn.click());

        // // ✅ Reset distance and fuel allocation
        // distanceInput.value = '--.--';
        // fuelAllocSpan.textContent = '--.--';
        // routeIdsInput.value = '';

        recalcDistanceFuel();

        // ⬇️ Automatically load destinations if a default area exists
        if (defaultArea) {
          const firstDestSelect = destinationsCont.querySelector('select.destination');
          fetchDestinationsForRow(defaultArea, firstDestSelect);
        }
      })
      .catch(err => console.error('fetchOrigins error', err));
  }


  fetchOrigins();

  // useRouteCheckbox.addEventListener('change', fetchOrigins);
  useRouteCheckbox.addEventListener('change', () => {
    // 🔹 Reset fields immediately
    distanceInput.value = '--.--';
    fuelAllocSpan.textContent = '--.--';
    routeIdsInput.value = '';

    // 🔹 Clear destinations instantly
    destinationsCont.innerHTML = `
      <div class="mb-2 destination-row">
        <select name="destination[]" class="form-select destination" required>
          <option value="">-- Select Destination --</option>
        </select>
      </div>
    `;

    // 🔹 Fetch origins (based on checkbox state)
    fetchOrigins();

    // 🔹 Prevent recalc from running too soon
    setTimeout(() => {
      distanceInput.value = '--.--';
      fuelAllocSpan.textContent = '--.--';
      routeIdsInput.value = '';
    }, 200);
  });


  
  originSelect.addEventListener('change', function () {
    destinationsCont.querySelectorAll('select.destination').forEach(sel => {
      fetchDestinationsForRow(this.value, sel);
      sel.value = '';
    });
    recalcDistanceFuel();
  });
});

// ==================== VEHICLE & FUEL HANDLING ====================

function filterVehicleTable() {
  const category = document.getElementById('filterCategory').value.toLowerCase();
  const ownership = document.getElementById('filterOwnership').value.toLowerCase();
  const search = document.getElementById('vehicleSearch').value.toLowerCase();

  const rows = document.querySelectorAll('#vehicleTable tbody tr');

  rows.forEach(row => {
    const rowCategory = row.getAttribute('data-category').toLowerCase();
    const rowOwnership = row.getAttribute('data-ownership').toLowerCase();
    const rowText = row.innerText.toLowerCase();

    const matchCategory = !category || rowCategory === category;
    const matchOwnership = !ownership || rowOwnership === ownership;
    const matchSearch = !search || rowText.includes(search);

    row.style.display = (matchCategory && matchOwnership && matchSearch) ? '' : 'none';
  });
}

function selectVehicle(id, displayText, category, kmpl, idling_rate) {
  const fuelBody = document.getElementById('fuelBody');

  document.getElementById('vehicle_id').value = id;
  document.getElementById('vehicle_display').value = displayText;
  document.getElementById('km_per_liter').value = kmpl;
  document.getElementById('vehicle_category').value = category; // 👈 new hidden field
  document.getElementById('vehicle_idling_rate').value = idling_rate; // 👈 new hidden field

  const useRouteCheckbox = document.getElementById('useRouteCheckbox');
  if (category.toLowerCase() === '2-wheels') {
    if (!useRouteCheckbox.checked) useRouteCheckbox.click();
  } else {
    if (useRouteCheckbox.checked) useRouteCheckbox.click();
  }

  // --- Clear existing fuel rows ---
  fuelBody.innerHTML = '';

  // --- Simulate clicking all remove buttons ---
  // const removeBtns = document.querySelectorAll('.remove-dest');
  // removeBtns.forEach(btn => btn.click());

  // --- Clear existing destination rows (multi-destination) ---
  // const destinationsCont = document.getElementById('destinationsCont');
  // if (destinationsCont) destinationsCont.innerHTML = '';

  // Enable Add Items button
  const addFuelBtn = document.getElementById('addFuelBtn');
  addFuelBtn.disabled = false;

  // Add the initial fuel row now that we know the category
  addFuelRow();

  document.body.focus();
  var modal = bootstrap.Modal.getInstance(document.getElementById('vehicleModal'));
  modal.hide();
  document.querySelector('[data-bs-target="#vehicleModal"]').focus();
}

document.getElementById('vehicleModalClose').addEventListener('click', function () {
  this.blur();
});

// Fuel row handling (unchanged from your script) ...
// [KEEP your addFuelRow() and updateFuelDropdowns() functions here]
// Function to add a new fuel row
function addFuelRow() {
  const tbody = document.getElementById('fuelBody');
  const row = document.createElement('tr');

  // Fuel Type <select>
  const select = document.createElement('select');
  select.name = 'fuel_item_id[]';
  select.className = 'form-select fuel_item'; // 👈 needed for filter
  select.innerHTML = '<option value="">-- Select --</option>';

  // get current vehicle category from hidden input
  const category = document.getElementById('vehicle_category')?.value || '';

  for (const id in fuelOptions) {
    const name = fuelOptions[id].name.toLowerCase();

    // Log both values
    console.log('Category:', category.toLowerCase(), 'Fuel name:', name);

    // 🚫 skip Diesel if vehicle is 2-wheels
    if (category.toLowerCase() === '2-wheels' && name === 'diesel') {
      continue;
    }

    select.innerHTML += `<option value="${id}">${fuelOptions[id].name}</option>`;
  }

  // Quantity <input>
  const qtyInput = document.createElement('input');
  qtyInput.type = 'text';
  qtyInput.name = 'quantity[]';
  qtyInput.className = 'form-control';
  qtyInput.required = true;

  // Unit <td>
  const unitCell = document.createElement('td');
  unitCell.className = 'unit text-center';

  // Container <td> with checkbox
  const containerCell = document.createElement('td');
  containerCell.className = 'text-center';

  const containerCheckbox = document.createElement('input');
  containerCheckbox.type = 'checkbox';
  containerCheckbox.name = 'container[]';
  containerCheckbox.value = 'yes';
  containerCheckbox.style.display = 'none'; // only shown if container is allowed
  containerCheckbox.style.width = '20px';
  containerCheckbox.style.height = '20px';
  containerCheckbox.style.cursor = 'pointer';

  containerCell.appendChild(containerCheckbox);

  // Remove button
  const removeBtn = document.createElement('button');
  removeBtn.type = 'button';
  removeBtn.className = 'btn btn-link text-danger p-1';
  removeBtn.innerHTML = '<i class="bi bi-trash fs-6"></i>';
  removeBtn.onclick = () => row.remove();

  // === On fuel item change ===
  select.onchange = async function () {
    const fuel = fuelOptions[this.value];
    const fuelName = fuel ? fuel.name.toLowerCase() : '';
    const useRouteCheckbox = document.getElementById('useRouteCheckbox')?.checked;
    const distance_km = document.getElementById('distance').value;
    const km_per_liter = document.getElementById('km_per_liter').value; //fuel_efficiency
    const idling_rate = document.getElementById('vehicle_idling_rate').value; // idling_rate
    const fuel_allocation = document.getElementById('fuel_allocation').textContent;

    // Set unit
    unitCell.textContent = fuel ? fuel.unit : '';

    // Show or hide container checkbox
    if (fuel && fuel.container === 'yes') {
      containerCheckbox.style.display = 'inline-block';
    } else {
      containerCheckbox.style.display = 'none';
      containerCheckbox.checked = false;
    }

    // Autofill quantity for diesel/unleaded
    if ((fuelName.includes('diesel') || fuelName.includes('unleaded'))) {
      try {
        console.log("Fuel_allocation qty:", fuel_allocation);
        if (useRouteCheckbox) {
          qtyInput.value = fuel_allocation ?? '';
        } else {
          if (!km_per_liter || parseFloat(km_per_liter) === 0) {
            alert("Vehicle has no valid KM per Liter. Please update vehicle data.");
            // $error = "Vehicle has no valid KM per Liter. Please update vehicle data.";
            qtyInput.value = '';
          } else {

            // const qty = parseFloat(distance_km) / parseFloat(km_per_liter);

            let qty = 0;

            switch(category){
              case 'trucks':
                qty = (parseFloat(distance_km) / parseFloat(km_per_liter)) + (parseFloat(idling_rate) * 6); // 6 hours idling for trucks
                break;
              default:
                qty = parseFloat(distance_km) / parseFloat(km_per_liter);
                break;
            }

            qtyInput.value = isNaN(qty) ? '' : qty.toFixed(2);
          }
        }
      } catch (err) {
        console.error("Error fetching fuel calculation:", err);
      }
    } else {
      qtyInput.value = ''; // clear if not diesel/unleaded or missing data
    }
  };

  // === Build and append row ===
  const fuelTypeCell = document.createElement('td');
  fuelTypeCell.appendChild(select);
  const qtyCell = document.createElement('td');
  qtyCell.appendChild(qtyInput);
  const actionCell = document.createElement('td');
  actionCell.className = 'text-center align-middle';
  actionCell.appendChild(removeBtn);

  row.appendChild(fuelTypeCell);
  row.appendChild(qtyCell);
  row.appendChild(unitCell);
  row.appendChild(containerCell);
  row.appendChild(actionCell);

  tbody.appendChild(row);
}

// Validity date handling (unchanged)
// Set default validity date to 1 day after today
const today = new Date();
const dateIssuedInput = document.getElementById('date_issued');
const validityInput = document.getElementById('validity_until');

function updateValidityMin() {
  // Convert MM/DD/YYYY to YYYY-MM-DD
  const [month, day, year] = dateIssuedInput.value.split('/');
  const formattedDate = `${year}-${month.padStart(2,'0')}-${day.padStart(2,'0')}`;

  validityInput.min = formattedDate;

  // If current value is before min, reset to min + 1 day
  const defaultValidity = new Date(formattedDate);
  defaultValidity.setDate(defaultValidity.getDate() + 1);
  const defaultStr = defaultValidity.toISOString().split('T')[0];

  if (!validityInput.value || validityInput.value < validityInput.min) {
    validityInput.value = defaultStr;
  }
}

// Initial setup
updateValidityMin();

// Update min whenever date_issued changes
dateIssuedInput.addEventListener('change', updateValidityMin);


// Hotkeys handling (unchanged)
document.addEventListener('keydown', function (e) {
  const isAdmin = <?= json_encode($_SESSION['role'] === 'admin') ?>;

  if (e.altKey && !e.shiftKey && !e.ctrlKey) {
    switch (e.code) {
      case 'Equal': // Alt+=
        e.preventDefault();
        addFuelRow();
        break;
      case 'Minus': // Alt+-
        e.preventDefault();
        const rows = document.querySelectorAll('#fuelBody tr');
        if (rows.length > 0) rows[rows.length - 1].remove();
        break;
      case 'KeyF': // Alt+F
          if (!isAdmin) return;
          e.preventDefault();
          window.location.href = 'add_fuel_item.php';
          break;
      case 'KeyV': // Alt+V
          if (!isAdmin) return;
          e.preventDefault();
          window.location.href = 'add_vehicle.php';
          break;
      case 'KeyD': // Alt+D
          if (!isAdmin) return;
          e.preventDefault();
          window.location.href = 'add_route.php';
          break;
}
  }
});

// Escape handling (unchanged)
let escapePressedOnce = false;

// Attach listener at the capture phase to catch early
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    const vehicleModal = document.getElementById('vehicleModal');
    const isModalOpen = vehicleModal && vehicleModal.classList.contains('show');
    const modalInstance = bootstrap.Modal.getInstance(vehicleModal);

    if (isModalOpen) {
      modalInstance.hide();
      escapePressedOnce = false; // Reset after modal is closed
    } else {
      // ✅ SweetAlert2 confirmation before redirect
      Swal.fire({
        title: 'Return to Main Page?',
        text: 'Any unsaved changes will be lost.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, return',
        cancelButtonText: 'Stay here'
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.href = 'main.php';
        }
      });
    }

    e.preventDefault(); // prevent form inputs from catching Escape
  }
}, true); // capture = true to ensure it catches even in form inputs

// Auto-dismiss alerts (unchanged)
// Auto-dismiss alerts
const successAlert = document.getElementById('addslipAlertSuccess');
if (successAlert) {
  setTimeout(() => bootstrap.Alert.getOrCreateInstance(successAlert).close(), 3000);
}
const errorAlert = document.getElementById('addslipAlertError');
if (errorAlert) {
  setTimeout(() => bootstrap.Alert.getOrCreateInstance(errorAlert).close(), 5000);
}

// Add initial fuel row (unchanged)
// --- Add initial fuel row when page loads ---
// document.addEventListener('DOMContentLoaded', function () {
//   addFuelRow();
// });

</script>


</body>
</html>
