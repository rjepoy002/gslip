/* =========================================================
   DESTINATIONS MODULE (ROUTES-BASED – FINAL CLEAN VERSION)
========================================================= */

let activeDestinationRow = null;
const USER_ORIGIN = window.APP?.USER_ORIGIN || '';

/* =========================================================
   OPEN DESTINATIONS MODAL (EDIT-AWARE VERSION)
========================================================= */
document.addEventListener('click', function (e) {

  if (e.target.closest('#selectedDestinationsTable')) return;

  const cell = e.target.closest('.destination-cell');
  if (!cell) return;

  activeDestinationRow = cell.closest('tr');

  const gasSlipId = activeDestinationRow.dataset.gasSlipId || null;

  document.getElementById('destOriginDisplay').textContent =
    USER_ORIGIN || '—';

  // STEP 1: set checkbox defaults
  applyRouteRules();

  // STEP 2: load available destinations
  loadDestinationsForOrigin(USER_ORIGIN);

  // STEP 3: if editing draft, preload saved routes from DB
  if (gasSlipId) {

    fetch('fetch_saved_routes.php?id=' + gasSlipId)
      .then(res => res.json())
      .then(saved => {

        // wait until checkboxes are rendered
        setTimeout(() => {

          saved.forEach(route => {

            const checkbox = document.querySelector(
              `#destinationList input[data-route-id="${route.route_id}"]`
            );

            if (checkbox) {
              checkbox.checked = true;
            }

          });

          updateSelectedDestinationsTable();

        }, 200);

      });

  }

  bootstrap.Modal
    .getOrCreateInstance(document.getElementById('destinationsModal'))
    .show();

});
/* =========================================================
   SET CHECKBOX DEFAULT BASED ON VEHICLE
========================================================= */
function applyRouteRules() {
  const useRoutes = document.getElementById('useRoutes');
  if (!useRoutes || !activeDestinationRow) return;

  // const categoryold = activeDestinationRow.dataset.vehicleCategory || '';
  const categoryField = activeDestinationRow.querySelector('.vehicle-category');
  if (!categoryField) {
    console.log('vehicle-category not found');
    return;
  }

  const category = categoryField.value;

  // 👇 Log value in console
  console.log('Vehicle Category:', category);
  console.log('Raw field:', categoryField);
  console.log('Current value:', JSON.stringify(categoryField.value));

  // default only — user can override
  if (category === '2-wheels') {
    useRoutes.checked = true;
  } else {
    useRoutes.checked = false;
  }
}

/* =========================================================
   LOAD DESTINATIONS (BACKEND-DRIVEN + EDIT PRELOAD SAFE)
========================================================= */
function loadDestinationsForOrigin(origin) {

  const list = document.getElementById('destinationList');
  list.innerHTML = '<em>Loading…</em>';

  if (!origin) {
    list.innerHTML = '<em>No origin assigned</em>';
    return;
  }

  const useRoutes =
    document.getElementById('useRoutes')?.checked ? 1 : 0;

  fetch(
    `get_destinations.php?origin=${encodeURIComponent(origin)}&useRoutes=${useRoutes}`
  )
    .then(res => {
      if (!res.ok) throw new Error('HTTP ' + res.status);
      return res.json();
    })
    .then(data => {

      list.innerHTML = '';

      if (!data.destinations || data.destinations.length === 0) {
        list.innerHTML = `
          <em class="text-muted">
            No ${useRoutes ? 'route-based' : 'manual'} destinations available.
          </em>
        `;
        return;
      }

      data.destinations
        .sort((a, b) => a.name.localeCompare(b.name))
        .forEach(d => {

          const label = document.createElement('label');
          label.className = 'd-block';

          // 🔥 DB-based preload check
          const isChecked =
            window.SAVED_ROUTE_IDS &&
            window.SAVED_ROUTE_IDS.includes(parseInt(d.route_id));

          label.innerHTML = `
            <input type="checkbox"
                   value="${d.name}"
                   data-km="${d.km}"
                   data-route-id="${d.route_id}"
                   data-fuel-allocation="${d.fuel_allocation}"
                   ${isChecked ? 'checked' : ''}>
            ${d.name}
            <span class="text-muted small">(${d.km} km)</span>
          `;

          list.appendChild(label);

        });

      // 🔥 rebuild selected table AFTER preload
      updateSelectedDestinationsTable();

    })
    .catch(err => {
      console.error(err);
      list.innerHTML =
        '<span class="text-danger">Failed to load destinations</span>';
    });
}

/* =========================================================
   CHECKBOX CHANGE → RELOAD FROM BACKEND
========================================================= */
document.getElementById('useRoutes')
  ?.addEventListener('change', () => {
    loadDestinationsForOrigin(USER_ORIGIN);
  });

/* =========================================================
   SELECTED DESTINATIONS TABLE
========================================================= */
function updateSelectedDestinationsTable() {
  const tbody =
    document.querySelector('#selectedDestinationsTable tbody');

  tbody.innerHTML = '';

  const checked = Array.from(
    document.querySelectorAll(
      '#destinationList input[type="checkbox"]:checked'
    )
  );

  let estimatedDistance = 0;
  let rowCount = 0;

  // ✅ NEW: longest-route tracking
  let longestRouteFuel = 0;
  let longestRouteId = null;

  checked.forEach((cb, index) => {
    const km = parseFloat(cb.dataset.km) || 0;
    const fuelAlloc =
      parseFloat(cb.dataset.fuelAllocation) || 0;
    const routeId =
      parseInt(cb.dataset.routeId) || null;

    // ✅ if this destination has longer distance, it wins
    if (km > estimatedDistance) {
      estimatedDistance = km;
      longestRouteFuel = fuelAlloc;
      longestRouteId = routeId;
    }

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="text-center">${index + 1}</td>
      <td>${cb.value}</td>
      <td class="text-end">${km.toFixed(2)}</td>
      <td class="text-center">
        <button type="button"
                class="btn btn-sm btn-outline-danger"
                onclick="removeDestination('${cb.value}')">
          ❌
        </button>
      </td>
    `;
    tbody.appendChild(tr);
    rowCount++;
  });

  // keep table height stable
  while (rowCount < 2) {
    tbody.insertAdjacentHTML('beforeend', `
      <tr>
        <td class="text-center">${rowCount + 1}</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td></td>
      </tr>
    `);
    rowCount++;
  }

  // estimated distance row (longest km)
  tbody.insertAdjacentHTML('beforeend', `
    <tr>
      <td colspan="2" class="fw-bold text-end">Estimated Distance</td>
      <td class="fw-bold text-end">${estimatedDistance.toFixed(2)}</td>
      <td></td>
    </tr>
  `);

  // ✅ STORE DATA FOR FUEL LOGIC
  activeDestinationRow.dataset.estimatedKm = estimatedDistance;
  activeDestinationRow.dataset.destCount = checked.length;
  activeDestinationRow.dataset.routeFuel = longestRouteFuel;
  activeDestinationRow.dataset.routeId = longestRouteId;
}


/* =========================================================
   REMOVE DESTINATION
========================================================= */
function removeDestination(name) {
  const cb = document.querySelector(
    `#destinationList input[value="${name}"]`
  );
  if (cb) cb.checked = false;
  updateSelectedDestinationsTable();
}

/* =========================================================
   CHECKBOX SYNC
========================================================= */
document.getElementById('destinationList')
  ?.addEventListener('change', e => {
    if (e.target.type === 'checkbox')
      updateSelectedDestinationsTable();
  });

/* =========================================================
   SAVE DESTINATIONS
========================================================= */
document.getElementById('saveDestinationsBtn')
  ?.addEventListener('click', function () {

    console.log('save destination clicked');
    
    if (!activeDestinationRow) return;

    const selected = Array.from(
      document.querySelectorAll(
        '#destinationList input[type="checkbox"]:checked'
      )
    ).map(cb => ({
      route_id: parseInt(cb.dataset.routeId) || null, // ⭐ IMPORTANT
      name: cb.value,
      km: parseFloat(cb.dataset.km) || 0
    }));

    // 🔍 DEBUG: verify route IDs
    selected.forEach(r => {
      console.log('Selected route_id:', r.route_id);
    });

    activeDestinationRow.querySelector('.destinations-data').value =
      JSON.stringify(selected);

    activeDestinationRow.querySelector('.dest-count').textContent =
      selected.length;

    bootstrap.Modal
      .getInstance(document.getElementById('destinationsModal'))
      .hide();
  });
