/* =========================================================
   FUEL REQUESTS MODULE
   STEP 1: 2-WHEELS ONLY
========================================================= */

let activeFuelRow = null;

// fuel items from DB
const fuelOptions = window.APP_FUEL_ITEMS || [];

function recomputeFuelRequestsForRow(gasSlipRow) {
  if (!gasSlipRow) return;

  const fuelDataInput = gasSlipRow.querySelector('.fuel-data');
  if (!fuelDataInput) return;

  let fuelRows = [];

  try {
    fuelRows = JSON.parse(fuelDataInput.value || '[]');
  } catch (err) {
    console.error('Invalid fuel JSON:', err);
    return;
  }

  const vehicleCategory = (
    gasSlipRow.querySelector('.vehicle-category')?.value ||
    gasSlipRow.dataset.vehicleCategory ||
    ''
  ).trim().toLowerCase();

  const vehicleEfficiency =
    parseFloat(gasSlipRow.dataset.vehicleEfficiency || 0);

  const routeFuel =
    parseFloat(gasSlipRow.dataset.routeFuel || 0);

  const estimatedKm =
    parseFloat(gasSlipRow.dataset.estimatedKm || 0);

  const idlingRate =
    parseFloat(gasSlipRow.dataset.idlingRate || 0);

  console.log('DRAFT/ROW COMPUTATION DATA:', {
    vehicleCategory: gasSlipRow.dataset.vehicleCategory,
    vehicleEfficiency: gasSlipRow.dataset.vehicleEfficiency,
    idlingRate: gasSlipRow.dataset.idlingRate,
    hiddenCategory: gasSlipRow.querySelector('.vehicle-category')?.value
  });

  const destCount =
    parseInt(gasSlipRow.dataset.destCount || 0, 10);

  const routeId =
    parseInt(gasSlipRow.dataset.routeId || 0, 10);

  const isFixedFuel =
  parseInt(
    gasSlipRow.dataset.isFixedFuel || 0,
    10
  );

  let changed = false;

  /* =============================================
     REMOVE INVALID FUEL ITEMS FOR VEHICLE
     2-WHEELS MUST NOT HAVE DIESEL
  ============================================= */
  fuelRows = fuelRows.filter(item => {

    const fuel = fuelOptions.find(
      f => String(f.id) === String(item.id)
    );

    if (!fuel) return true;

    const fuelName = fuel.name.toLowerCase();

    if (
      vehicleCategory === '2-wheels' &&
      fuelName === 'diesel'
    ) {
      console.log(
        'Removing Diesel: not allowed for 2-wheels'
      );

      changed = true;
      return false;
    }

    return true;
  });

  /* =============================================
     RECOMPUTE DIESEL / UNLEADED
  ============================================= */
  fuelRows.forEach(item => {

    const fuel = fuelOptions.find(
      f => String(f.id) === String(item.id)
    );

    if (!fuel) return;

    const fuelName = fuel.name.toLowerCase();

    // Only recompute Diesel and Unleaded
    if (
      fuelName !== 'diesel' &&
      fuelName !== 'unleaded'
    ) {
      return;
    }

    // Do not overwrite manually entered container quantity
    if (String(item.container).toLowerCase() === 'yes') {
      return;
    }

    let fuelLiters = 0;

    /* FIXED FUEL ROUTE */
    if (isFixedFuel === 1) {
      item.qty = routeFuel.toFixed(2);
      changed = true;
      return;
    }

    switch (vehicleCategory) {

      case '2-wheels':

        if (routeFuel <= 0 || destCount <= 0) return;

        fuelLiters = routeFuel;

        if (destCount > 1) {
          fuelLiters += (destCount - 1) * 0.6;
        }

        break;

      case '4-wheels':

        if (estimatedKm <= 0 || vehicleEfficiency <= 0) return;

        fuelLiters = Math.ceil(
          estimatedKm / vehicleEfficiency
        );

        break;

      case 'trucks':

        if (estimatedKm <= 0 || vehicleEfficiency <= 0) return;

        const idlingFuel = idlingRate * 6;
        const travelFuel =
          estimatedKm / vehicleEfficiency;

        fuelLiters = Math.ceil(
          idlingFuel + travelFuel
        );

        break;

      default:
        console.log(
          'Cannot recompute: unknown vehicle category',
          vehicleCategory
        );
        return;
    }

    item.qty = fuelLiters.toFixed(2);
    changed = true;
  });

  /* =============================================
     SAVE UPDATED FUEL DATA
  ============================================= */
  fuelDataInput.value = JSON.stringify(fuelRows);

  const fuelCount = gasSlipRow.querySelector('.fuel-count');
  
  if (fuelCount) {
    fuelCount.textContent = fuelRows.length;
  }
  
  console.log(
    'Fuel checked/recomputed after destination change:',
    {
      changed,
      vehicleCategory,
      fuelRows
    }
  );
}

/* =========================================================
   OPEN FUEL REQUEST MODAL
========================================================= */
document.addEventListener('click', function (e) {
  const btn = e.target.closest('.fuel-btn');
  if (!btn) return;

  e.preventDefault();
  e.stopPropagation();

  const cell = btn.closest('.fuel-cell');
  if (!cell) return;

  activeFuelRow = cell.closest('tr');
  // console.log(activeFuelRow.outerHTML);

  if (!activeFuelRow) return;

  loadFuelRows();

  const modalEl = document.getElementById('fuelModal');
  if (!modalEl || !window.bootstrap) return;

  bootstrap.Modal.getOrCreateInstance(modalEl).show();
});


/* =========================================================
   LOAD EXISTING FUEL DATA
========================================================= */
function loadFuelRows() {
  const tbody = document.getElementById('fuelRequestBody');
  tbody.innerHTML = '';

  let saved = [];
  try {
    saved = JSON.parse(
      activeFuelRow.querySelector('.fuel-data').value || '[]'
    );
  } catch {}

  // ✅ update counter during edit load
  activeFuelRow.querySelector('.fuel-count').textContent =
    saved.length;

  if (!saved.length) {
    addFuelRow();
  } else {
    saved.forEach(row => addFuelRow(row));
  }

  renumberFuelRows();
}

/* =========================================================
   ADD FUEL ROW
========================================================= */
function addFuelRow(data = {}) {
  const tbody = document.getElementById('fuelRequestBody');

  const vehicleCategory = (
    activeFuelRow.querySelector('.vehicle-category')?.value ||
    activeFuelRow.dataset.vehicleCategory ||
    ''
  ).trim().toLowerCase();

  const vehicleEfficiency =
    parseFloat(activeFuelRow.dataset.vehicleEfficiency || 0);

  /* ------------------------------
     FILTER FUEL ITEMS (2-WHEELS)
  ------------------------------ */
  const allowedFuelOptions = fuelOptions.filter(f => {
    const name = f.name.toLowerCase();

    // 🚲 2-wheels → EXCLUDE diesel
    if (vehicleCategory === '2-wheels' && name === 'diesel') {
      return false;
    }

    return true;
  });

  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td class="row-num text-center"></td>

    <td>
      <select class="form-select form-select-sm fuel-select">
        <option value="">— Select Fuel —</option>
        ${allowedFuelOptions.map(f =>
          `<option value="${f.id}">
            ${f.name} (${f.unit})
          </option>`
        ).join('')}
      </select>
    </td>

    <td>
      <input type="number"
            class="form-control form-control-sm fuel-qty"
            placeholder="0.00"
            min="0"
            step="1.00"
            inputmode="decimal"
            readonly>
    </td>

    <td class="text-center" hidden>
      <input type="checkbox"
             class="form-check-input fuel-container">
    </td>

    <td class="text-center">
      <button type="button"
              class="btn btn-sm btn-outline-danger fuel-remove">
        ❌
      </button>
    </td>
  `;

  tbody.appendChild(tr);

  const select = tr.querySelector('.fuel-select');
  const qtyInput = tr.querySelector('.fuel-qty');

  const containerCheckbox =
    tr.querySelector('.fuel-container');

  containerCheckbox.addEventListener('change', function () {

    const fuel = allowedFuelOptions.find(
      f => String(f.id) === select.value
    );

    if (!fuel) return;

    const fuelName = fuel.name.toLowerCase();

    // Only Diesel / Unleaded can be unlocked
    if (fuelName === 'diesel' || fuelName === 'unleaded') {

      qtyInput.readOnly = !this.checked;

      if (!this.checked) {
        select.dispatchEvent(new Event('change'));
      }
    }
  });
  /* =====================================================
     VEHICLES AUTO COMPUTE (YOUR FORMULA)
  ===================================================== */
  select.addEventListener('change', function () {

    let fuelLiters = 0; // ✅ REQUIRED
    
    const fuel = allowedFuelOptions.find(
      f => String(f.id) === this.value
    );

    console.log('select.value:', this.value);
    console.log('fuel found?', fuel);

    if (!fuel) return;

    const fuelName = fuel.name.toLowerCase();
    const isFuel = fuelName === 'diesel' || fuelName === 'unleaded';
    
    console.log('fuelName:', fuelName);
    console.log('isFuel:', isFuel);

    if (!isFuel) {
      qtyInput.readOnly = false;
      qtyInput.value = '';
      return;
    }
    
    qtyInput.readOnly =
      !tr.querySelector('.fuel-container').checked;

    const routeFuel = parseFloat(activeFuelRow.dataset.routeFuel || 0);
    const estimatedKm = parseFloat(activeFuelRow.dataset.estimatedKm || 0);
    const idlingRate = parseFloat(activeFuelRow.dataset.idlingRate || 0);
    const destCount = parseInt(activeFuelRow.dataset.destCount || 0, 10);

    console.log('routeFuel:', routeFuel);
    console.log('destCount:', destCount);
    console.log('estimatedKm:', estimatedKm);
    console.log('vehicleEfficiency:', vehicleEfficiency);

    // const routeId = parseInt(
    //   activeFuelRow.dataset.routeId || 0,
    //   10
    // );

    const isFixedFuel =
    parseInt(
      activeFuelRow.dataset.isFixedFuel || 0,
      10
    );

    // Fixed Fuel Route
    if (
      isFixedFuel === 1 &&
      (fuelName === 'diesel' || fuelName === 'unleaded')
    ) {
      qtyInput.value = routeFuel.toFixed(2);
      return;
    }

    switch (vehicleCategory) {
      case '2-wheels':
        if (routeFuel <= 0 || destCount <= 0) return;

        fuelLiters = routeFuel;
        if (destCount > 1) {
          fuelLiters += (destCount - 1) * 0.6;
        }
        break;
      
      case '4-wheels':
        // formula: max(estimated distance) / km/l
          fuelLiters = estimatedKm / vehicleEfficiency;
          fuelLiters = Math.ceil(fuelLiters);

        break;

      case 'trucks':
        // [a] km/l = vary based on vehicle consumption
        // [b] distance = value based on origin and destination 
        // [c] idling rate = vary based on average consumption per truck
        // [d] idling time = 6 hours (fixed)
        // [e] idling fuel = c × d
        // [f] travel fuel = b ÷ a
        // [d] fuel_liters = e+f
          const idlingFuel = idlingRate * 6;
          const travelFuel = estimatedKm / vehicleEfficiency;
          fuelLiters = idlingFuel + travelFuel;
          fuelLiters = Math.ceil(fuelLiters);

        break;
      
      default:
        return;
    }
      console.log('RECOMPUTED:', fuelLiters.toFixed(2));
      qtyInput.value = fuelLiters.toFixed(2);
      console.log({
        vehicleCategory,
        routeFuel,
        estimatedKm,
        vehicleEfficiency,
        destCount,
        idlingRate,
        fuelLiters
      });
      
  });

  // restore saved data (edit mode)
  console.log('FULL DATA:', data);
  if (data.id) {

    console.log('data.id:', data.id);
    console.log('data.qty:', data.qty);
    console.log('data.container:', data.container);

    select.value = data.id;
    qtyInput.value = data.qty || '';
    // tr.querySelector('.fuel-container').checked =
    //   data.container === 'Yes';
    const containerCheck =
      tr.querySelector('.fuel-container');

      containerCheck.checked =
      String(data.container).toLowerCase() === 'yes';

    qtyInput.readOnly = !containerCheck.checked;
  }


  // remove row
  tr.querySelector('.fuel-remove').onclick = () => {
    tr.remove();
    renumberFuelRows();
  };

  renumberFuelRows();
}

/* =========================================================
   RENUMBER ROWS
========================================================= */
function renumberFuelRows() {
  document
    .querySelectorAll('#fuelRequestBody .row-num')
    .forEach((td, i) => td.textContent = i + 1);
}

/* =========================================================
   SAVE FUEL REQUESTS
========================================================= */
document.getElementById('saveFuelBtn')
  ?.addEventListener('click', function () {

    if (!activeFuelRow) return;

    const rows = [];
    const used = new Set();

    document
      .querySelectorAll('#fuelRequestBody tr')
      .forEach(tr => {
        const id = tr.querySelector('.fuel-select').value;
        const qty = tr.querySelector('.fuel-qty').value.trim();
        const container =
          tr.querySelector('.fuel-container').checked ? 'Yes' : 'No';

        if (!id || !qty || used.has(id)) return;
        used.add(id);

        rows.push({ id, qty, container });
      });

    activeFuelRow.querySelector('.fuel-data').value =
      JSON.stringify(rows);
      console.log(rows);

    activeFuelRow.querySelector('.fuel-count').textContent =
      rows.length;

    bootstrap.Modal.getInstance(
      document.getElementById('fuelModal')
    ).hide();
  });

/* =========================================================
   INITIALIZE FUEL COUNTS (EDIT MODE)
========================================================= */
document.querySelectorAll('.fuel-cell').forEach(cell => {

  const fuelData =
    cell.querySelector('.fuel-data')?.value || '[]';

  let rows = [];

  try {
    rows = JSON.parse(fuelData);
  } catch {}

  const countEl = cell.querySelector('.fuel-count');

  if (countEl) {
    countEl.textContent = rows.length;
  }

});