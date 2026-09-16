/* =========================================================
   DESTINATIONS MODULE (ROUTES-BASED – FINAL CLEAN VERSION)
========================================================= */

let activeDestinationRow = null;

const USER_ORIGIN =
  window.APP?.USER_ORIGIN || '';

const USER_ORIGIN_NAME =
  window.APP?.USER_ORIGIN_NAME || '';


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
     RESTORE SAVED DESTINATIONS
     FOR NEW ROWS / TEMPLATE ROWS
  --------------------------------------------------------- */

  if (!gasSlipId) {

    const json =
      activeDestinationRow
        .querySelector('.destinations-data')
        ?.value || '[]';

    let saved = [];

    try {

      saved =
        JSON.parse(json);

    } catch (err) {

      console.error(
        'Invalid destination JSON',
        err
      );

    }


    setTimeout(() => {

      saved.forEach(route => {

        const checkbox =
          document.querySelector(
            `#destinationList input[data-route-id="${route.route_id}"]`
          );

        if (checkbox) {
          checkbox.checked = true;
        }

      });


      updateSelectedDestinationsTable();


      /* Sync hidden field */

      const destinationData =
        activeDestinationRow.querySelector(
          '.destinations-data'
        );

      if (destinationData) {
        destinationData.value =
          JSON.stringify(saved);
      }


      const destCount =
        activeDestinationRow.querySelector(
          '.dest-count'
        );

      if (destCount) {
        destCount.textContent =
          saved.length;
      }

    }, 200);

  }


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
    categoryField.value;


  console.log(
    'Vehicle Category:',
    category
  );

  console.log(
    'Raw field:',
    categoryField
  );

  console.log(
    'Current value:',
    JSON.stringify(
      categoryField.value
    )
  );


  if (!category) {
    return;
  }


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

      label.style.display = 'flex';

    } else {

      label.style.display = 'none';

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


  list.innerHTML =
    '<em>Loading…</em>';


  if (!origin) {

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
      vehicleCategory: vehicleCategory
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


      if (
        !data.destinations ||
        data.destinations.length === 0
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

        return;

      }


      data.destinations

        .sort(
          (a, b) =>
            a.name.localeCompare(b.name)
        )

        .forEach(d => {

          const label =
            document.createElement(
              'label'
            );


          /*
           * IMPORTANT:
           *
           * Do not use Bootstrap "d-block"
           * here because d-block contains
           * display:block !important.
           *
           * That prevents the search function
           * from hiding the label.
           */

          label.className =
            'destination-item';


          /*
           * Keep every destination on its
           * own row and align the checkbox
           * with the destination text.
           */

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


          const isChecked =
            window.SAVED_ROUTE_IDS &&
            window.SAVED_ROUTE_IDS.includes(
              parseInt(d.route_id)
            );


          label.innerHTML = `
            <input
              type="checkbox"
              value="${d.name}"
              data-km="${d.km}"
              data-route-id="${d.route_id}"
              data-fuel-allocation="${d.fuel_allocation}"
              data-is-fixed-fuel="${d.is_fixed_fuel}"
              ${isChecked ? 'checked' : ''}
            >

            <span>
              ${d.name}
              <span class="text-muted small">
                (${d.km} km)
              </span>
            </span>
          `;


          list.appendChild(
            label
          );

        });


      /* -------------------------------------------------------
         UPDATE SELECTED TABLE
      ------------------------------------------------------- */

      updateSelectedDestinationsTable();


      /* -------------------------------------------------------
         APPLY SEARCH AFTER LIST IS BUILT
      ------------------------------------------------------- */

      filterDestinations();


      /* -------------------------------------------------------
         SYNC HIDDEN FIELD
      ------------------------------------------------------- */

      if (activeDestinationRow) {

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

    })

    .catch(err => {

      console.error(
        'Destination load error:',
        err
      );

      list.innerHTML =
        '<span class="text-danger">' +
        'Failed to load destinations' +
        '</span>';

    });

}


/* =========================================================
   CHECKBOX CHANGE → RELOAD FROM BACKEND
========================================================= */

document.addEventListener(
  'change',
  function (e) {

    if (
      e.target.id === 'useRoutes'
    ) {

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
          ${cb.value}
        </td>

        <td class="text-end">
          ${km.toFixed(2)}
        </td>

        <td class="text-center">

          <button
            type="button"
            class="btn btn-sm btn-outline-danger"
            onclick="removeDestination('${cb.value}')"
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

function removeDestination(name) {

  const checkboxes =
    document.querySelectorAll(
      '#destinationList input[type="checkbox"]'
    );


  checkboxes.forEach(
    cb => {

      if (
        cb.value === name
      ) {

        cb.checked = false;

      }

    }
  );


  updateSelectedDestinationsTable();

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
          ) || 0

      }));


    /* Debug route IDs */

    selected.forEach(
      r => {

        console.log(
          'Selected route_id:',
          r.route_id
        );

      }
    );


    /* -------------------------------------------------------
       SAVE TO HIDDEN FIELD
    ------------------------------------------------------- */

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
          ) || 0

      }));


    /* -------------------------------------------------------
       SAVE TO HIDDEN FIELD
    ------------------------------------------------------- */

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