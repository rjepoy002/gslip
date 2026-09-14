/* =========================================================
   VEHICLES MODULE
   - Filtering
   - Pagination
   - Continuous Row Numbering
   - FIXED: vehicle category stored on slip row
========================================================= */

let activeVehicleRow = null;

/* Pagination config */
const VEHICLES_PER_PAGE = 15;
let currentVehiclePage = 1;

/* =========================================================
   DOM READY — FILTER WIRING
========================================================= */
document.addEventListener('DOMContentLoaded', function () {
  document.getElementById('filterCategory')
    ?.addEventListener('change', resetAndFilter);

  document.getElementById('filterOwnership')
    ?.addEventListener('change', resetAndFilter);

  document.getElementById('vehicleSearch')
    ?.addEventListener('input', resetAndFilter);

  /* Initial render */
  filterVehicleTable();
});

function resetAndFilter() {
  currentVehiclePage = 1;
  filterVehicleTable();
}

/* =========================================================
   OPEN VEHICLE MODAL (CLICK)
========================================================= */
document.addEventListener('click', function (e) {
  const cell = e.target.closest('.vehicle-cell');
  if (!cell) return;

  activeVehicleRow = cell.closest('tr');

  bootstrap.Modal
    .getOrCreateInstance(document.getElementById('vehicleModal'))
    .show();

  setTimeout(filterVehicleTable, 0);
});

/* =========================================================
   OPEN VEHICLE MODAL (ENTER)
========================================================= */
document.addEventListener('keydown', function (e) {
  if (e.key !== 'Enter') return;

  const input = document.activeElement;
  if (!input || !input.classList.contains('vehicle-display')) return;
  if (input.closest('.modal')) return;

  e.preventDefault();
  activeVehicleRow = input.closest('tr');

  bootstrap.Modal
    .getOrCreateInstance(document.getElementById('vehicleModal'))
    .show();

  setTimeout(filterVehicleTable, 0);
});

/* =========================================================
   FILTER VEHICLES
========================================================= */
function filterVehicleTable() {
  const category =
    document.getElementById('filterCategory')?.value.toLowerCase() || '';
  const ownership =
    document.getElementById('filterOwnership')?.value.toLowerCase() || '';
  const search =
    document.getElementById('vehicleSearch')?.value.toLowerCase() || '';

  const allRows = Array.from(
    document.querySelectorAll('#vehicleTable tbody tr')
  );

  const visibleRows = allRows.filter(row => {
    const rowCategory  = row.dataset.category?.toLowerCase() || '';
    const rowOwnership = row.dataset.ownership?.toLowerCase() || '';
    const rowText      = row.innerText.toLowerCase();

    return (
      (!category || rowCategory === category) &&
      (!ownership || rowOwnership === ownership) &&
      (!search || rowText.includes(search))
    );
  });

  paginateVehicles(visibleRows, allRows);
}

/* =========================================================
   PAGINATION
========================================================= */
function paginateVehicles(visibleRows, allRows) {
  const start = (currentVehiclePage - 1) * VEHICLES_PER_PAGE;
  const end   = start + VEHICLES_PER_PAGE;

  allRows.forEach(row => row.style.display = 'none');

  const pageRows = visibleRows.slice(start, end);
  pageRows.forEach(row => row.style.display = '');

  updateVehicleRowNumbers(pageRows, start);
  renderVehiclePagination(visibleRows.length);
}

/* =========================================================
   CONTINUOUS ROW NUMBERING
========================================================= */
function updateVehicleRowNumbers(rowsOnPage, startIndex) {
  rowsOnPage.forEach((row, index) => {
    const cell = row.querySelector('.row-number');
    if (cell) {
      cell.textContent = startIndex + index + 1;
    }
  });
}

/* =========================================================
   PAGINATION CONTROLS
========================================================= */
function renderVehiclePagination(totalVisible) {
  let container = document.getElementById('vehiclePagination');

  if (!container) {
    container = document.createElement('div');
    container.id = 'vehiclePagination';
    container.className = 'd-flex justify-content-end gap-1 mt-2';
    document.getElementById('vehicleTable').after(container);
  }

  container.innerHTML = '';

  const totalPages = Math.ceil(totalVisible / VEHICLES_PER_PAGE);
  if (totalPages <= 1) return;

  for (let i = 1; i <= totalPages; i++) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = i;
    btn.className =
      'btn btn-sm ' +
      (i === currentVehiclePage
        ? 'btn-primary'
        : 'btn-outline-secondary');

    btn.addEventListener('click', () => {
      currentVehiclePage = i;
      filterVehicleTable();
    });

    container.appendChild(btn);
  }
}

/* =========================================================
   INITIALIZE EXISTING VEHICLE
   For Drafts / Templates
========================================================= */
function initializeExistingVehicle(slipRow) {
  if (!slipRow) return;

  const vehicleId =
    slipRow.querySelector('input[name="vehicle_id[]"]')?.value;

  if (!vehicleId) return;

  const vehicleRow = document.querySelector(
    `#vehicleTable tbody tr[data-id="${vehicleId}"]`
  );

  if (!vehicleRow) {
    console.warn(
      'Vehicle not found for initialization:',
      vehicleId
    );
    return;
  }

  const category =
    vehicleRow.dataset.category || '';

  const idlingRate =
    parseFloat(vehicleRow.dataset.idlingRate || 0);

  /* Get km/l from the 7th column */
  const kmpl =
    vehicleRow.children[6]?.textContent.trim() || '';

  /* Initialize hidden fields */
  const categoryInput =
    slipRow.querySelector('.vehicle-category');

  const efficiencyInput =
    slipRow.querySelector('.vehicle-efficiency');

  if (categoryInput) {
    categoryInput.value = category;
  }

  if (efficiencyInput) {
    efficiencyInput.value = kmpl;
  }

  /* Initialize row datasets */
  slipRow.dataset.vehicleCategory = category;
  slipRow.dataset.vehicleEfficiency = kmpl;
  slipRow.dataset.idlingRate = idlingRate;

  console.log('Existing vehicle initialized:', {
    vehicleId,
    category,
    kmpl,
    idlingRate
  });
}

/* =========================================================
   SELECT VEHICLE FROM MODAL (FIXED)
========================================================= */
function selectVehicleFromModal(row, id, plate, brand, model, kmpl, category) {
  if (!activeVehicleRow) return;

  const prevCategory =
  activeVehicleRow.dataset.vehicleCategory || null;

  const idlingRate =
    parseFloat(row.dataset.idlingRate || 0);

  /* Visible fields */
  activeVehicleRow.querySelector('input[name="vehicle_id[]"]').value = id;
  activeVehicleRow.querySelector('.vehicle-display').value =
    `${plate} - ${brand} ${model}`;
  
  // 🔥 ADD THIS LINE
  activeVehicleRow.querySelector('.vehicle-category').value = category;
  activeVehicleRow.querySelector('.vehicle-efficiency').value = kmpl;

  /* 🔑 CRITICAL FIX
     Store vehicle category on THE SAME SLIP ROW
     so destinations.js can read it
  */
  activeVehicleRow.dataset.vehicleCategory = category;
  activeVehicleRow.dataset.vehicleEfficiency = kmpl;
  activeVehicleRow.dataset.idlingRate = idlingRate;

  console.log("Setting category on:", activeVehicleRow);
  
  /* 🔥 RESET DEPENDENCIES IF CATEGORY CHANGED */
  if (prevCategory && prevCategory !== category) {

    /* RESET DESTINATIONS (already fixed) */
    const destInput =
      activeVehicleRow.querySelector('.destinations-data');
    if (destInput) destInput.value = '[]';

    const destCount =
      activeVehicleRow.querySelector('.dest-count');
    if (destCount) destCount.textContent = '0';

    delete activeVehicleRow.dataset.routeFuel;
    delete activeVehicleRow.dataset.estimatedKm;
    delete activeVehicleRow.dataset.destCount;
    delete activeVehicleRow.dataset.routeId;

    /* 🔥 RESET FUEL ITEMS (THIS WAS MISSING) */
    resetFuelItems();
  }


  bootstrap.Modal
    .getInstance(document.getElementById('vehicleModal'))
    .hide();
}

function resetFuelItems() {

  /* =========================
     CLEAR FUEL TABLE
  ========================= */
  const fuelBody =
    document.getElementById('fuelRequestBody');
  if (fuelBody) fuelBody.innerHTML = '';

  /* =========================
     RESET FUEL COUNT BADGE
  ========================= */
  const fuelCount =
    activeVehicleRow.querySelector('.fuel-count');
  if (fuelCount) fuelCount.textContent = '0';

  /* =========================
     CLEAR ANY ACTIVE CONTEXT
  ========================= */
  activeFuelRow = null;

  /* =========================
     OPTIONAL: CLEAR STORED DATA
  ========================= */
  const fuelData =
    activeVehicleRow.querySelector('.fuel-data');
  if (fuelData) fuelData.value = '[]';
}

/* =========================================================
   INITIALIZE PRELOADED VEHICLES
   Drafts / Templates
========================================================= */
document.addEventListener('DOMContentLoaded', function () {

  document.querySelectorAll('#gasSlipGrid tbody tr').forEach(row => {
    initializeExistingVehicle(row);
  });

});