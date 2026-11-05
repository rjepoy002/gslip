/* =========================================================
   DESTINATIONS MODULE (ROUTES-BASED – FINAL CLEAN VERSION)
========================================================= */

let activeDestinationRow = null;
let destinationLoadVersion = 0;
let destinationLoadController = null;
let renderedDestinationRow = null;
let destinationsLoading = false;

const USER_ORIGIN =
  window.APP?.USER_ORIGIN || '';

const USER_ORIGIN_NAME =
  window.APP?.USER_ORIGIN_NAME || '';


/* =========================================================
   GET SAVED DESTINATIONS FROM ACTIVE ROW
========================================================= */

function getSavedDestinations() {

  if (!activeDestinationRow) {
    return [];
  }

  const destinationData =
    activeDestinationRow.querySelector(
      '.destinations-data'
    );

  if (!destinationData) {
    return [];
  }

  const json =
    destinationData.value || '[]';

  try {

    const saved = JSON.parse(json);

    if (!Array.isArray(saved)) return [];
    // Seed only unresolved IDs; persisted display snapshots must not overwrite
    // fresher metadata returned by the current destination request.
    rememberDestinationRouteCodes(saved.filter(d => !destinationRouteCodes.has(Number(d.route_id))));
    return saved;

  } catch (err) {

    console.error(
      'Invalid destination JSON:',
      err
    );

    return [];

  }

}


/* =========================================================
   ESCAPE HTML
========================================================= */

function escapeHtml(value) {

  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

}


/* =========================================================
   OPEN DESTINATIONS MODAL
========================================================= */

document.addEventListener('click', function (e) {

  // Ignore clicks inside modal
  if (e.target.closest('#destinationsModal')) {
    return;
  }

  if (e.target.closest('#selectedDestinationsTable')) {
    return;
  }

  const cell =
    e.target.closest('.destination-cell');

  if (!cell) {
    return;
  }

  activeDestinationRow =
    cell.closest('tr');


  /* ---------------------------------------------------------
     RESET SEARCH WHEN MODAL OPENS
  --------------------------------------------------------- */

  const destinationSearch =
    document.getElementById('destinationSearch');

  if (destinationSearch) {
    destinationSearch.value = '';
  }


  /* ---------------------------------------------------------
     GAS SLIP ID
  --------------------------------------------------------- */

  const gasSlipId =
    cell.dataset.gasSlipId || null;


  /* ---------------------------------------------------------
     ORIGIN
  --------------------------------------------------------- */

  const originDisplay =
    document.getElementById('destOriginDisplay');

  if (originDisplay) {

    originDisplay.textContent =
      USER_ORIGIN_NAME || '—';

  }


  /* ---------------------------------------------------------
     APPLY VEHICLE ROUTE RULES
  --------------------------------------------------------- */

  applyRouteRules();


  /* ---------------------------------------------------------
     LOAD DESTINATIONS
  --------------------------------------------------------- */

  loadDestinationsForOrigin(USER_ORIGIN);


  /* ---------------------------------------------------------
     SHOW MODAL
  --------------------------------------------------------- */

  bootstrap.Modal
    .getOrCreateInstance(
      document.getElementById(
        'destinationsModal'
      )
    )
    .show();

});


/* =========================================================
   SET CHECKBOX DEFAULT BASED ON VEHICLE
========================================================= */

function applyRouteRules() {

  const useRoutes =
    document.getElementById('useRoutes');

  if (!useRoutes || !activeDestinationRow) {
    return;
  }


  const categoryField =
    activeDestinationRow.querySelector(
      '.vehicle-category'
    );


  if (!categoryField) {

    console.log(
      'vehicle-category not found'
    );

    return;

  }


  const category =
    categoryField.value
      ?.trim()
      .toLowerCase() || '';


  console.log(
    'Vehicle Category:',
    category
  );


  if (!category) {
    return;
  }


  /*
   * 2-wheel vehicles default to
   * approved/fixed routes.
   */

  useRoutes.checked =
    category === '2-wheels';

}


/* =========================================================
   FILTER DESTINATIONS
========================================================= */

function filterDestinations() {

  const searchInput =
    document.getElementById(
      'destinationSearch'
    );

  const destinationList =
    document.getElementById(
      'destinationList'
    );


  if (!searchInput || !destinationList) {
    return;
  }


  const search =
    searchInput.value
      .trim()
      .toLowerCase();


  const items =
    destinationList.querySelectorAll(
      'label'
    );


  items.forEach(label => {

    const checkbox =
      label.querySelector(
        'input[type="checkbox"]'
      );


    if (!checkbox) {
      return;
    }


    const destinationName =
      (
        checkbox.value || ''
      ).toLowerCase();


    if (
      search === '' ||
      destinationName.includes(search) ||
      String(checkbox.dataset.routeCode || '').toLowerCase().includes(search)
    ) {

      label.style.display =
        'flex';

    } else {

      label.style.display =
        'none';

    }

  });

}


/* =========================================================
   DESTINATION SEARCH EVENT
========================================================= */

document.addEventListener(
  'input',
  function (e) {

    if (
      e.target &&
      e.target.id === 'destinationSearch'
    ) {

      filterDestinations();

    }

  }
);


/* =========================================================
   CREATE DESTINATION LABEL
========================================================= */

const destinationRouteCollator = new Intl.Collator(undefined, {numeric: true, sensitivity: 'base'});
function compareAvailableDestinations(a, b) {
  const codeA = String(a.route_code || '').trim();
  const codeB = String(b.route_code || '').trim();
  if (Boolean(codeA) !== Boolean(codeB)) return codeA ? -1 : 1;
  return destinationRouteCollator.compare(codeA, codeB) ||
    destinationRouteCollator.compare(String(a.name || ''), String(b.name || '')) ||
    (Number(a.route_id) || 0) - (Number(b.route_id) || 0);
}
// Display metadata stays outside the saved gas-slip/template payload.
const destinationRouteCodes = new Map();
function rememberDestinationRouteCodes(destinations, routeCodes = {}) {
  for (const [id, code] of Object.entries(routeCodes)) destinationRouteCodes.set(Number(id), code || '');
  for (const destination of destinations) {
    const id = Number(destination.route_id) || 0;
    if (id && Object.prototype.hasOwnProperty.call(destination, 'route_code') && destination.route_code !== undefined) destinationRouteCodes.set(id, destination.route_code || '');
  }
}
function withDestinationRouteCode(destination) {
  const id = Number(destination.route_id) || 0;
  return {...destination, route_code: destinationRouteCodes.has(id) ? destinationRouteCodes.get(id) : destination.route_code};
}
function destinationDisplayName(destination) {
  const resolved = withDestinationRouteCode(destination);
  const code = String(resolved.route_code || '').trim();
  return (code ? code + ' - ' : '') + String(resolved.name || '');
}
function destinationKey(id, name) {
  return Number(id) > 0 ? 'route:' + Number(id) : 'name:' + String(name || '');
}
function orderedCheckedDestinations() {
  const checked = Array.from(document.querySelectorAll('#destinationList input[type="checkbox"]:checked'));
  const order = new Map(getSavedDestinations().map((d, index) => [destinationKey(d.route_id, d.name), index]));
  return checked.sort((a, b) => (order.get(destinationKey(a.dataset.routeId, a.value)) ?? Infinity) -
    (order.get(destinationKey(b.dataset.routeId, b.value)) ?? Infinity));
}
function showUnresolvedDestinationCodes(list) {
  if (Array.from(list.querySelectorAll('input[data-route-id]')).some(cb =>
      Number(cb.dataset.routeId) > 0 && !destinationRouteCodes.has(Number(cb.dataset.routeId)))) {
    const notice = document.createElement('div');
    notice.className = 'text-warning small';
    notice.setAttribute('role', 'status');
    notice.textContent = 'Some route codes could not be loaded. Reopen destinations to retry.';
    list.appendChild(notice);
  }
}

function createDestinationLabel(d, isChecked) {

  const label =
    document.createElement(
      'label'
    );


  /*
   * IMPORTANT:
   *
   * Do not use Bootstrap "d-block"
   * because d-block contains
   * display:block !important.
   */

  label.className =
    'destination-item';


  label.style.display =
    'flex';

  label.style.alignItems =
    'flex-start';

  label.style.gap =
    '4px';

  label.style.width =
    '100%';

  label.style.marginBottom =
    '4px';


  const routeId =
    parseInt(d.route_id) || null;

  const km =
    parseFloat(d.km) || 0;

  const fuelAllocation =
    parseFloat(d.fuel_allocation) || 0;

  const isFixedFuel =
    parseInt(d.is_fixed_fuel) || 0;

  const name =
    d.name || '';
  const routeCode = String(withDestinationRouteCode(d).route_code || '').trim();


  label.innerHTML = `
    <input
      type="checkbox"
      value="${escapeHtml(name)}"
      data-km="${km}"
      data-route-id="${routeId ?? ''}"
      data-route-code="${escapeHtml(routeCode)}"
      style="flex-shrink:0; margin-top:4px;"
      data-fuel-allocation="${fuelAllocation}"
      data-is-fixed-fuel="${isFixedFuel}"
      ${isChecked ? 'checked' : ''}
    >

    <span style="min-width:0; overflow-wrap:anywhere;">
      ${escapeHtml(destinationDisplayName(d))}
      <span class="text-muted small">
        (${km} km)
      </span>
    </span>
  `;


  return label;

}


/* =========================================================
   LOAD DESTINATIONS
========================================================= */

function loadDestinationsForOrigin(origin) {

  const list =
    document.getElementById(
      'destinationList'
    );


  if (!list) {
    return;
  }


  /*
   * IMPORTANT:
   *
   * Read the saved destinations BEFORE
   * clearing/rebuilding the destination list.
   *
   * This is what allows custom destinations
   * to survive route/filter changes.
   */

  if (renderedDestinationRow === activeDestinationRow && !destinationsLoading) syncDestinationData();
  const requestRow = activeDestinationRow;
  const version = ++destinationLoadVersion;
  if (destinationLoadController) destinationLoadController.abort();
  destinationLoadController = new AbortController();
  const isCurrent = () => version === destinationLoadVersion && activeDestinationRow === requestRow && requestRow?.isConnected;
  const savedDestinations =
    getSavedDestinations();


  destinationsLoading = true;
  renderedDestinationRow = null;
  document.getElementById('saveDestinationsBtn').disabled = true;
  const selectedBody = document.querySelector('#selectedDestinationsTable tbody');
  if (selectedBody) selectedBody.innerHTML = '<tr><td colspan="4">Loading destinations...</td></tr>';
  list.innerHTML =
    '<em>Loading…</em>';


  if (!origin) {

    /*
     * Even without an origin, show saved
     * destinations if they exist.
     */

    list.innerHTML = '';

    if (savedDestinations.length > 0) {

      savedDestinations.map(withDestinationRouteCode).sort(compareAvailableDestinations).forEach(saved => {

        list.appendChild(
          createDestinationLabel(
            saved,
            true
          )
        );

      });

      destinationsLoading = false;
      document.getElementById('saveDestinationsBtn').disabled = false;
      renderedDestinationRow = requestRow;
      showUnresolvedDestinationCodes(list);
      updateSelectedDestinationsTable();
      filterDestinations();

      return;

    }


    destinationsLoading = false;
    document.getElementById('saveDestinationsBtn').disabled = false;
    renderedDestinationRow = requestRow;
    list.innerHTML =
      '<em>No origin assigned</em>';
    updateSelectedDestinationsTable();

    return;

  }


  const useRoutes =
    document.getElementById(
      'useRoutes'
    )?.checked
      ? 1
      : 0;


  /* -------------------------------------------------------
     GET VEHICLE CATEGORY
  ------------------------------------------------------- */

  const vehicleCategory =
    activeDestinationRow
      ?.querySelector('.vehicle-category')
      ?.value
      ?.trim()
      .toLowerCase() || '';


  console.log(
    'Loading destinations:',
    {
      useRoutes: useRoutes,
      vehicleCategory: vehicleCategory,
      savedDestinations: savedDestinations
    }
  );


  /* -------------------------------------------------------
     LOAD FROM BACKEND
  ------------------------------------------------------- */

  const selectedRouteIds = savedDestinations.map(d => parseInt(d.route_id) || 0).filter(Boolean).join(',');
  return fetch(
    `get_destinations.php?origin=${encodeURIComponent(origin)}&useRoutes=${useRoutes}&vehicleCategory=${encodeURIComponent(vehicleCategory)}&selected_route_ids=${encodeURIComponent(selectedRouteIds)}`,
    {cache: 'no-store', signal: destinationLoadController.signal}
  )

    .then(res => {

      if (!res.ok) {

        throw new Error(
          'HTTP ' + res.status
        );

      }

      return res.json();

    })

    .then(data => {
      if (!isCurrent()) return;

      rememberDestinationRouteCodes(data.destinations || [], data.route_codes || {});
      list.innerHTML = '';


      /*
       * Backend destinations.
       */

      let destinations =
        Array.isArray(data.destinations)
          ? [...data.destinations]
          : [];
      destinations = destinations.map(withDestinationRouteCode);
      destinationsLoading = false;
      document.getElementById('saveDestinationsBtn').disabled = false;
      renderedDestinationRow = requestRow;


      /* ---------------------------------------------------
         MERGE SAVED DESTINATIONS
      ---------------------------------------------------

         A saved custom destination may NOT be returned
         by get_destinations.php because the current
         filter may be different.

         Example:

         Saved:
             Destination A

         Current filter:
             2-wheel / fixed fuel

         If Destination A is not a fixed-fuel route,
         the backend will not return it.

         We therefore add the saved destination back
         into the list.
      --------------------------------------------------- */

      savedDestinations.forEach(saved => {

        const savedRouteId =
          parseInt(saved.route_id) || null;

        const savedName =
          String(
            saved.name || ''
          )
            .trim()
            .toLowerCase();


        const alreadyExists =
          destinations.some(d => {

            const backendRouteId =
              parseInt(d.route_id) || null;

            const backendName =
              String(
                d.name || ''
              )
                .trim()
                .toLowerCase();


            /*
             * Match by route ID when possible.
             */

            if (
              savedRouteId !== null &&
              backendRouteId !== null &&
              savedRouteId === backendRouteId
            ) {

              return true;

            }


            /*
             * Also match by destination name.
             */

            if (
              !(savedRouteId !== null && backendRouteId !== null) &&
              savedName !== '' &&
              backendName !== '' &&
              savedName === backendName
            ) {

              return true;

            }


            return false;

          });


        if (!alreadyExists) {

          /*
           * Add the saved destination even if
           * the backend filter did not return it.
           */

          destinations.push({
            route_id:
              savedRouteId,
            route_code: destinationRouteCodes.get(savedRouteId),

            name:
              saved.name || '',

            km:
              parseFloat(saved.km) || 0,

            fuel_allocation:
              parseFloat(
                saved.fuel_allocation
              ) || 0,

            is_fixed_fuel:
              parseInt(
                saved.is_fixed_fuel
              ) || 0,

            saved_destination:
              true
          });

        }

      });


      /* ---------------------------------------------------
         NO DESTINATIONS
      --------------------------------------------------- */

      if (
        destinations.length === 0
      ) {

        list.innerHTML = `
          <em class="text-muted">
            No ${useRoutes
              ? 'route-based'
              : vehicleCategory === '2-wheels'
                ? 'Fixed Fuel'
                : 'manual'
            } destinations available.
          </em>
        `;


        updateSelectedDestinationsTable();

        return;

      }


      /* ---------------------------------------------------
         SORT DESTINATIONS
      --------------------------------------------------- */

      destinations.sort(compareAvailableDestinations);


      /* ---------------------------------------------------
         BUILD DESTINATION LIST
      --------------------------------------------------- */

      destinations.forEach(d => {

        const routeId =
          parseInt(d.route_id) || null;

        const destinationName =
          String(
            d.name || ''
          )
            .trim()
            .toLowerCase();


        /*
         * A destination is checked if it was
         * previously saved.
         */

        const isChecked =
          savedDestinations.some(saved => {

            const savedRouteId =
              parseInt(
                saved.route_id
              ) || null;

            const savedName =
              String(
                saved.name || ''
              )
                .trim()
                .toLowerCase();


            /*
             * Match by route ID.
             */

            if (
              routeId !== null &&
              savedRouteId !== null &&
              routeId === savedRouteId
            ) {

              return true;

            }


            /*
             * Match by destination name.
             *
             * This is especially useful for
             * custom/non-route destinations.
             */

            if (
              !(routeId !== null && savedRouteId !== null) &&
              destinationName !== '' &&
              savedName !== '' &&
              destinationName === savedName
            ) {

              return true;

            }


            return false;

          });


        list.appendChild(
          createDestinationLabel(
            d,
            isChecked
          )
        );

      });


      showUnresolvedDestinationCodes(list);

      /* ---------------------------------------------------
         UPDATE SELECTED TABLE
      --------------------------------------------------- */

      updateSelectedDestinationsTable();


      /* ---------------------------------------------------
         APPLY SEARCH
      --------------------------------------------------- */

      filterDestinations();


      /*
       * IMPORTANT:
       *
       * Do NOT overwrite the hidden field here.
       *
       * Previously this code rebuilt the hidden field
       * from only the currently returned backend
       * destinations. That could erase saved custom
       * destinations.
       *
       * The hidden field is updated only when the user
       * explicitly saves/changes the selection.
       */

    })

    .catch(err => {
      if (!isCurrent() || err.name === 'AbortError') return;
      destinationsLoading = false;
      document.getElementById('saveDestinationsBtn').disabled = false;
      renderedDestinationRow = requestRow;

      console.error(
        'Destination load error:',
        err
      );


      /*
       * If backend loading fails, do not lose
       * previously saved destinations.
       */

      list.innerHTML = '';


      if (
        savedDestinations.length > 0
      ) {

        savedDestinations.map(withDestinationRouteCode).sort(compareAvailableDestinations).forEach(saved => {

          list.appendChild(
            createDestinationLabel(
              saved,
              true
            )
          );

        });


        showUnresolvedDestinationCodes(list);
        updateSelectedDestinationsTable();
        filterDestinations();

      } else {

        list.innerHTML =
          '<span class="text-danger">' +
          'Failed to load destinations' +
          '</span>';
        const selectedBody = document.querySelector('#selectedDestinationsTable tbody');
        if (selectedBody) selectedBody.innerHTML = '<tr><td colspan="4">Unable to load destinations. Reopen to retry.</td></tr>';

      }

    });

}


/* =========================================================
   CHECKBOX SYNC
========================================================= */

document.getElementById(
  'destinationList'
)?.addEventListener(
  'change',
  function (e) {

    if (
      e.target.type === 'checkbox'
    ) {

      syncDestinationData();
      updateSelectedDestinationsTable();

    }

  }
);


/* =========================================================
   CHECKBOX → RELOAD FROM BACKEND
========================================================= */

document.addEventListener(
  'change',
  function (e) {

    if (
      e.target.id === 'useRoutes'
    ) {

      /*
       * Before reloading, the currently selected
       * destinations are already stored in the
       * hidden field when the user changes a checkbox.
       *
       * loadDestinationsForOrigin() will read those
       * saved values and merge them back.
       */

      loadDestinationsForOrigin(
        USER_ORIGIN
      );

    }

  }
);


/* =========================================================
   SELECTED DESTINATIONS TABLE
========================================================= */

function updateSelectedDestinationsTable() {

  const tbody =
    document.querySelector(
      '#selectedDestinationsTable tbody'
    );


  if (!tbody || !activeDestinationRow || destinationsLoading || renderedDestinationRow !== activeDestinationRow) {
    return;
  }


  tbody.innerHTML = '';


  const checked = orderedCheckedDestinations();


  let estimatedDistance = 0;

  let rowCount = 0;


  /* Longest route tracking */

  let longestRouteFuel = 0;

  let longestRouteId = null;

  let longestRouteIsFixedFuel = 0;


  checked.forEach(
    (cb, index) => {

      const km =
        parseFloat(
          cb.dataset.km
        ) || 0;


      const fuelAlloc =
        parseFloat(
          cb.dataset.fuelAllocation
        ) || 0;


      const routeId =
        parseInt(
          cb.dataset.routeId
        ) || null;


      const isFixedFuel =
        parseInt(
          cb.dataset.isFixedFuel
        ) || 0;


      /*
       * If this destination has the longest
       * distance, use its fuel allocation.
       */

      if (
        km > estimatedDistance
      ) {

        estimatedDistance =
          km;

        longestRouteFuel =
          fuelAlloc;

        longestRouteId =
          routeId;

        longestRouteIsFixedFuel =
          isFixedFuel;

      }


      const tr =
        document.createElement(
          'tr'
        );

      // Display-only metadata, resolved by route ID; preserve checkbox order/value.
      const selectedName = destinationDisplayName({route_id: routeId, route_code: cb.dataset.routeCode, name: cb.value});

      tr.innerHTML = `
        <td class="text-center">
          ${index + 1}
        </td>

        <td>
          ${escapeHtml(selectedName)}
        </td>

        <td class="text-end">
          ${km.toFixed(2)}
        </td>

        <td class="text-center">

          <button
            type="button"
            class="btn btn-sm btn-outline-danger remove-destination-btn"
            data-destination-name="${escapeHtml(cb.value)}"
            data-route-id="${cb.dataset.routeId || ''}"
          >
            ❌
          </button>

        </td>
      `;


      tbody.appendChild(
        tr
      );


      rowCount++;

    }
  );


  /* -------------------------------------------------------
     KEEP TABLE HEIGHT STABLE
  ------------------------------------------------------- */

  while (
    rowCount < 2
  ) {

    tbody.insertAdjacentHTML(
      'beforeend',
      `
      <tr>

        <td class="text-center">
          ${rowCount + 1}
        </td>

        <td>&nbsp;</td>

        <td>&nbsp;</td>

        <td></td>

      </tr>
      `
    );


    rowCount++;

  }


  /* -------------------------------------------------------
     ESTIMATED DISTANCE
  ------------------------------------------------------- */

  tbody.insertAdjacentHTML(
    'beforeend',
    `
    <tr>

      <td
        colspan="2"
        class="fw-bold text-end"
      >
        Estimated Distance
      </td>

      <td
        class="fw-bold text-end"
      >
        ${estimatedDistance.toFixed(2)}
      </td>

      <td></td>

    </tr>
    `
  );


  /* -------------------------------------------------------
     STORE DATA FOR FUEL LOGIC
  ------------------------------------------------------- */

  activeDestinationRow.dataset.estimatedKm =
    estimatedDistance;

  activeDestinationRow.dataset.destCount =
    checked.length;

  activeDestinationRow.dataset.routeFuel =
    longestRouteFuel;

  activeDestinationRow.dataset.routeId =
    longestRouteId;

  activeDestinationRow.dataset.isFixedFuel =
    longestRouteIsFixedFuel;

}


/* =========================================================
   REMOVE DESTINATION
========================================================= */

document.addEventListener(
  'click',
  function (e) {

    const button =
      e.target.closest(
        '.remove-destination-btn'
      );

    if (!button) {
      return;
    }


    e.preventDefault();
    e.stopPropagation();


    const name =
      button.dataset.destinationName;


    if (!name) {
      return;
    }


    const checkboxes =
      document.querySelectorAll(
        '#destinationList input[type="checkbox"]'
      );


    checkboxes.forEach(cb => {

      if (
        (button.dataset.routeId ? cb.dataset.routeId === button.dataset.routeId : cb.value === name)
      ) {

        cb.checked = false;

      }

    });


    /*
     * Update selected destinations table
     */

    updateSelectedDestinationsTable();


    /*
     * Update hidden destination data
     */

    syncDestinationData();

  }
);


/* =========================================================
   SYNC CURRENT CHECKED DESTINATIONS
   TO HIDDEN FIELD
========================================================= */

function syncDestinationData() {

  if (!activeDestinationRow || destinationsLoading || renderedDestinationRow !== activeDestinationRow) {
    return;
  }


  const selected = orderedCheckedDestinations()
    .map(cb => ({

      route_id:
        parseInt(
          cb.dataset.routeId
        ) || null,

      name:
        cb.value,

      km:
        parseFloat(
          cb.dataset.km
        ) || 0,

      fuel_allocation:
        parseFloat(
          cb.dataset.fuelAllocation
        ) || 0,

      is_fixed_fuel:
        parseInt(
          cb.dataset.isFixedFuel
        ) || 0

    }));


  const destinationData =
    activeDestinationRow.querySelector(
      '.destinations-data'
    );


  if (destinationData) {

    destinationData.value =
      JSON.stringify(
        selected
      );

  }


  const destCount =
    activeDestinationRow.querySelector(
      '.dest-count'
    );


  if (destCount) {

    destCount.textContent =
      selected.length;

  }

}


/* =========================================================
   SAVE DESTINATIONS
========================================================= */

document.getElementById(
  'saveDestinationsBtn'
)?.addEventListener(
  'click',
  function () {

    console.log(
      'save destination clicked'
    );


    if (destinationsLoading) return;

    if (!activeDestinationRow) {
      return;
    }


    /* -------------------------------------------------------
       SAVE CURRENT SELECTION
    ------------------------------------------------------- */

    syncDestinationData();


    /* -------------------------------------------------------
       DEBUG ROUTE IDS
    ------------------------------------------------------- */

    const selected =
      Array.from(
        document.querySelectorAll(
          '#destinationList input[type="checkbox"]:checked'
        )
      );


    selected.forEach(
      cb => {

        console.log(
          'Selected route_id:',
          parseInt(
            cb.dataset.routeId
          ) || null
        );

      }
    );


    /* -------------------------------------------------------
       UPDATE ROUTE VALUES
    ------------------------------------------------------- */

    updateSelectedDestinationsTable();


    /* -------------------------------------------------------
       RECOMPUTE DIESEL / UNLEADED
    ------------------------------------------------------- */

    if (
      typeof recomputeFuelRequestsForRow ===
      'function'
    ) {

      recomputeFuelRequestsForRow(
        activeDestinationRow
      );

    }


    /* -------------------------------------------------------
       CLOSE MODAL
    ------------------------------------------------------- */

    bootstrap.Modal
      .getInstance(
        document.getElementById(
          'destinationsModal'
        )
      )
      .hide();

  }
);


/* =========================================================
   AUTO-SAVE ON MODAL CLOSE
========================================================= */

document.getElementById(
  'destinationsModal'
)?.addEventListener(
  'hidden.bs.modal',
  function () {

    if (!activeDestinationRow) {
      return;
    }


    /*
     * Save whatever is currently checked.
     *
     * This also preserves custom destinations
     * because they are now kept in the list even
     * when the backend filter changes.
     */

    ++destinationLoadVersion;
    if (destinationLoadController) destinationLoadController.abort();
    if (destinationsLoading) { destinationsLoading = false; renderedDestinationRow = null; document.getElementById('saveDestinationsBtn').disabled = false; return; }

    syncDestinationData();


    /* -------------------------------------------------------
       UPDATE ROUTE VALUES
    ------------------------------------------------------- */

    updateSelectedDestinationsTable();


    /* -------------------------------------------------------
       RECOMPUTE DIESEL / UNLEADED
    ------------------------------------------------------- */

    if (
      typeof recomputeFuelRequestsForRow ===
      'function'
    ) {

      recomputeFuelRequestsForRow(
        activeDestinationRow
      );

    }

  }
);
