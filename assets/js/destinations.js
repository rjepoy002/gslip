/* =========================================================
   DESTINATIONS MODULE (ROUTES-BASED – FINAL CLEAN VERSION)
========================================================= */

let activeDestinationRow = null;

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

    return Array.isArray(saved)
      ? saved
      : [];

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
      destinationName.includes(search)
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
    'center';

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


  label.innerHTML = `
    <input
      type="checkbox"
      value="${escapeHtml(name)}"
      data-km="${km}"
      data-route-id="${routeId ?? ''}"
      data-fuel-allocation="${fuelAllocation}"
      data-is-fixed-fuel="${isFixedFuel}"
      ${isChecked ? 'checked' : ''}
    >

    <span>
      ${escapeHtml(name)}
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

  const savedDestinations =
    getSavedDestinations();


  list.innerHTML =
    '<em>Loading…</em>';


  if (!origin) {

    /*
     * Even without an origin, show saved
     * destinations if they exist.
     */

    list.innerHTML = '';

    if (savedDestinations.length > 0) {

      savedDestinations.forEach(saved => {

        list.appendChild(
          createDestinationLabel(
            saved,
            true
          )
        );

      });

      updateSelectedDestinationsTable();
      filterDestinations();

      return;

    }


    list.innerHTML =
      '<em>No origin assigned</em>';

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

  fetch(
    `get_destinations.php?origin=${encodeURIComponent(origin)}&useRoutes=${useRoutes}&vehicleCategory=${encodeURIComponent(vehicleCategory)}`
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

      list.innerHTML = '';


      /*
       * Backend destinations.
       */

      let destinations =
        Array.isArray(data.destinations)
          ? [...data.destinations]
          : [];


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

      destinations.sort(
        (a, b) =>
          String(a.name || '')
            .localeCompare(
              String(b.name || '')
            )
      );


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

        savedDestinations.forEach(saved => {

          list.appendChild(
            createDestinationLabel(
              saved,
              true
            )
          );

        });


        updateSelectedDestinationsTable();
        filterDestinations();

      } else {

        list.innerHTML =
          '<span class="text-danger">' +
          'Failed to load destinations' +
          '</span>';

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


  if (!tbody || !activeDestinationRow) {
    return;
  }


  tbody.innerHTML = '';


  const checked =
    Array.from(
      document.querySelectorAll(
        '#destinationList input[type="checkbox"]:checked'
      )
    );


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


      tr.innerHTML = `
        <td class="text-center">
          ${index + 1}
        </td>

        <td>
          ${escapeHtml(cb.value)}
        </td>

        <td class="text-end">
          ${km.toFixed(2)}
        </td>

        <td class="text-center">

          <button
            type="button"
            class="btn btn-sm btn-outline-danger remove-destination-btn"
            data-destination-name="${escapeHtml(cb.value)}"
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
        cb.value === name
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

  if (!activeDestinationRow) {
    return;
  }


  const selected =
    Array.from(
      document.querySelectorAll(
        '#destinationList input[type="checkbox"]:checked'
      )
    )
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