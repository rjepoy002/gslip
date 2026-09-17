
$(function () {


/* =========================================
   DATATABLE INITIALIZATION
========================================= */

if (
    $.fn.DataTable.isDataTable(
        '#RoutesTable'
    )
) {

    $('#RoutesTable')
        .DataTable()
        .destroy();

}


const table =
    $('#RoutesTable').DataTable({

        paging: true,

        searching: true,

        ordering: true,

        responsive: true,

        pageLength: 15,

        pagingType: "full_numbers",

        lengthMenu: [
            [15, 25, 50, 75, 100],
            [15, 25, 50, 75, 100]
        ],

        language: {

            lengthMenu:
                'Rows per page: _MENU_',

            search: '',

            searchPlaceholder:
                'Search...',

            infoCallback:
                function (
                    settings,
                    start,
                    end,
                    max,
                    total
                ) {

                    return (
                        'Entries: ' +
                        start +
                        '–' +
                        end +
                        '  |  Total: ' +
                        total
                    );

                },

            paginate: {

                first: 'First',

                previous: '<',

                next: '>',

                last: 'Last'

            }

        },

        dom:
            '<"d-flex justify-content-between align-items-center mb-2"fl>' +
            'rt' +
            '<"d-flex justify-content-between align-items-center mt-3"ip>'

    });


/* =========================================
   ROW CLICK → EDIT MODE
========================================= */

$(document).on(
    'click',
    '.Routes-row',
    function () {

        const routeId =
            $(this).data('id');


        const modalElement =
            document.getElementById(
                'addRoutesModal'
            );


        const modal =
            new bootstrap.Modal(
                modalElement
            );


        /* Fill route form */

        $('#routes_id').val(
            routeId
        );

        $('#area').val(
            $(this).data('area')
        );


        /* Get saved Route value */

        const savedRoute =
            (
                $(this).data('route') || ''
            )
            .toString()
            .trim();


        /*
         * Automatically check 2-Wheels Route
         * when a Route value exists.
         */

        const twoWheelsCheckbox =
            document.getElementById(
                'twoWheelsRoute'
            );


        if (twoWheelsCheckbox) {

            twoWheelsCheckbox.checked =
                savedRoute !== '';

            twoWheelsCheckbox.dispatchEvent(
                new Event('change')
            );

        }


        /* Restore saved Route value */

        $('#route_input').val(
            savedRoute
        );


        /* Continue loading fields */

        $('#origin').val(
            $(this).data('origin')
        );

        $('#destination').val(
            $(this).data('destination')
        );

        $('#distance_km').val(
            $(this).data('distance_km')
        );

        $('#fuel_allocation').val(
            $(this).data('fuel_allocation')
        );


        /* Restore Fixed Fuel */

        $('#is_fixed_fuel').prop(
            'checked',
            Number(
                $(this).data('is_fixed_fuel')
            ) === 1
        );


        toggleFixedFuel();


        $('#status').val(
            $(this).data('status')
        );

        $('#remarks').val(
            $(this).data('remarks')
        );


        $('#formTitle').text(
            'Edit Routes'
        );

        $('#addBtn').addClass(
            'd-none'
        );

        $('#updateBtn').removeClass(
            'd-none'
        );

        $('#form_mode').val(
            'edit'
        );


        /* Show loading overlay */

        showRouteLoading(
            'Loading saved route...'
        );


        /* Open modal */

        modal.show();


        /*
         * Wait until modal is visible.
         */

        $(modalElement).one(
            'shown.bs.modal',
            async function () {

                /*
                 * Fixed Fuel routes do not
                 * load saved map locations.
                 */

                const fixedFuelCheckbox =
                    document.getElementById(
                        'is_fixed_fuel'
                    );


                if (
                    fixedFuelCheckbox &&
                    fixedFuelCheckbox.checked
                ) {

                    toggleFixedFuel();


                    const distanceInput =
                        document.getElementById(
                            'distance_km'
                        );


                    if (distanceInput) {
                        distanceInput.value = '0.1';
                    }


                    const statusElement =
                        document.getElementById(
                            'routePlannerStatus'
                        );


                    if (statusElement) {

                        statusElement.textContent =
                            'Fixed Fuel route. Map is disabled.';

                    }


                    hideRouteLoading();

                    return;
                }


                try {

                    const response =
                        await fetch(
                            'routes.php?action=get_route_locations&route_id=' +
                            encodeURIComponent(
                                routeId
                            )
                        );


                    const data =
                        await response.json();


                    if (
                        data.status !==
                        'success'
                    ) {

                        console.error(
                            'Unable to load route locations:',
                            data.message
                        );

                        hideRouteLoading();

                        return;
                    }


                    const locations =
                        data.locations || [];


                    if (
                        locations.length === 0
                    ) {

                        console.warn(
                            'No saved locations found for route:',
                            routeId
                        );

                        hideRouteLoading();

                        return;
                    }


                    /* Clear previous map data */

                    clearRoutePlanner();


                    /* Make sure map is ready */

                    if (!routeMap) {

                        console.error(
                            'Route map is not initialized.'
                        );

                        hideRouteLoading();

                        return;
                    }


                    /*
                     * Get origin
                     */

                    const savedOrigin =
                        locations.find(
                            location =>
                                location.location_type ===
                                'origin'
                        );


                    if (!savedOrigin) {

                        console.error(
                            'No origin found for saved route.'
                        );

                        hideRouteLoading();

                        return;
                    }


                    /*
                     * Add origin pin
                     */

                    setRouteOrigin(
                        parseFloat(
                            savedOrigin.latitude
                        ),
                        parseFloat(
                            savedOrigin.longitude
                        )
                    );


                    /*
                     * Add destination pins
                     */

                    const destinations =
                        locations
                        .filter(
                            location =>
                                location.location_type ===
                                'destination'
                        )
                        .sort(
                            (a, b) =>
                                parseInt(
                                    a.sequence
                                ) -
                                parseInt(
                                    b.sequence
                                )
                        );


                    destinations.forEach(
                        function (location) {

                            addRouteDestination(
                                parseFloat(
                                    location.latitude
                                ),
                                parseFloat(
                                    location.longitude
                                )
                            );

                        }
                    );


                    /*
                     * Generate complete route.
                     */

                    await finalizeRoute();


                    /*
                     * Zoom map to saved pins
                     */

                    const mapBounds =
                        locations.map(
                            location => [
                                parseFloat(
                                    location.latitude
                                ),
                                parseFloat(
                                    location.longitude
                                )
                            ]
                        );


                    routeMap.invalidateSize();


                    if (
                        mapBounds.length > 1
                    ) {

                        routeMap.fitBounds(
                            mapBounds,
                            {
                                padding: [60, 60],
                                maxZoom: 15
                            }
                        );

                    } else if (
                        mapBounds.length === 1
                    ) {

                        routeMap.setView(
                            mapBounds[0],
                            15
                        );

                    }


                    const statusElement =
                        document.getElementById(
                            'routePlannerStatus'
                        );


                    if (statusElement) {

                        statusElement.textContent =
                            'Saved route loaded.';

                    }


                    console.log(
                        'Saved route loaded successfully.',
                        locations
                    );


                    routeMap.invalidateSize();


                    /*
                     * Allow map to paint before
                     * removing loading overlay.
                     */

                    await new Promise(
                        resolve => {

                            requestAnimationFrame(
                                () => {

                                    requestAnimationFrame(
                                        () => {

                                            setTimeout(
                                                resolve,
                                                500
                                            );

                                        }
                                    );

                                }
                            );

                        }
                    );


                    hideRouteLoading();


                } catch (error) {

                    console.error(
                        'Error loading saved route:',
                        error
                    );

                    hideRouteLoading();

                }

            }
        );

    }
);


/* =========================================
   SAVE Routes (AJAX)
========================================= */

$('#RoutesForm').on(
    'submit',
    function (e) {

        e.preventDefault();


        $.ajax({

            url: 'routes.php',

            type: 'POST',

            data: $(this).serialize(),

            dataType: 'json',


            success:
                function (response) {

                    if (
                        response.status ===
                        'success'
                    ) {

                        Swal.fire({

                            icon: 'success',

                            title: 'Success',

                            text:
                                response.message,

                            timer: 2000,

                            showConfirmButton:
                                false

                        }).then(
                            () => {
                                location.reload();
                            }
                        );

                    } else {

                        Swal.fire({

                            icon: 'error',

                            title: 'Error',

                            text:
                                response.message

                        });

                    }

                },


            error:
                function (xhr) {

                    let message =
                        "Unknown server error.";


                    try {

                        const json =
                            JSON.parse(
                                xhr.responseText
                            );


                        if (json.message) {

                            message =
                                json.message;

                        }

                    } catch (e) {

                        message =
                            xhr.responseText;

                    }


                    Swal.fire({

                        icon: 'error',

                        title: 'Server Error',

                        text: message

                    });

                }

        });

    }
);

});


/* =========================================================
   RESET ROUTES FORM
========================================================= */

function resetRoutesForm() {

    $('#RoutesForm')[0].reset();

    $('#routes_id').val('');

    $('#form_mode').val('add');

    $('#formTitle').text(
        'Add New Routes'
    );

    // Default Area to user's assigned area
    $('#area').val(userAreaName);

    // Update Origin based on selected Area
    copyAreaToOrigin();

    $('#addBtn').removeClass(
        'd-none'
    );

    $('#updateBtn').addClass(
        'd-none'
    );


    /*
     * Reset Route / Fuel Allocation
     * visibility.
     */

    toggleTwoWheelsRoute();

    /*
     * Reset Fixed Fuel state.
     */

    toggleFixedFuel();
}


/* =========================================================
   AUTO-FILL ORIGIN FROM AREA
========================================================= */

function copyAreaToOrigin() {

    const areaSelect =
        document.getElementById('area');

    const selectedText =
        areaSelect
        .options[
            areaSelect.selectedIndex
        ]
        .text;

    document.getElementById(
        'origin'
    ).value =
        selectedText;
}


/* =========================================================
   TOGGLE 2-WHEELS ROUTE
========================================================= */

function toggleTwoWheelsRoute() {

    const twoWheelsCheckbox =
        document.getElementById(
            'twoWheelsRoute'
        );

    const routeRow =
        document.getElementById(
            'route_row'
        );

    const routeInput =
        document.getElementById(
            'route_input'
        );

    const fuelAllocationRow =
        document.getElementById(
            'fuel_allocation_row'
        );

    const fuelInput =
        document.getElementById(
            'fuel_allocation'
        );

    const fixedFuelRow =
        document.getElementById(
            'fixed_fuel_row'
        );

    const fixedFuelCheckbox =
        document.getElementById(
            'is_fixed_fuel'
        );


    if (
        !twoWheelsCheckbox ||
        !routeRow ||
        !routeInput ||
        !fuelAllocationRow ||
        !fuelInput
    ) {
        return;
    }


    const isTwoWheels =
        twoWheelsCheckbox.checked;


    /* ---------------------------------------------------------
       SHOW / HIDE 2-WHEELS FIELDS
    --------------------------------------------------------- */

    routeRow.style.display =
        isTwoWheels
            ? ''
            : 'none';

    fuelAllocationRow.style.display =
        isTwoWheels
            ? ''
            : 'none';


    /* ---------------------------------------------------------
       SHOW / HIDE FIXED FUEL
    --------------------------------------------------------- */

    if (fixedFuelRow) {

        fixedFuelRow.style.display =
            isTwoWheels
                ? ''
                : 'none';

    }


    /* ---------------------------------------------------------
       2-WHEELS ON
    --------------------------------------------------------- */

    if (isTwoWheels) {

        routeInput.required =
            true;

        fuelInput.required =
            true;

    }


    /* ---------------------------------------------------------
       2-WHEELS OFF
    --------------------------------------------------------- */

    else {

        routeInput.required =
            false;

        fuelInput.required =
            false;


        routeInput.value =
            '';

        fuelInput.value =
            '';


        /* Turn Fixed Fuel OFF */

        if (fixedFuelCheckbox) {

            fixedFuelCheckbox.checked =
                false;

        }

    }


    /* ---------------------------------------------------------
       APPLY FIXED FUEL BEHAVIOR
    --------------------------------------------------------- */

    if (typeof toggleFixedFuel === 'function') {

        toggleFixedFuel();

    }

}


/* =========================================================
   TOGGLE FIXED FUEL
========================================================= */

function toggleFixedFuel() {

    const fixedFuelCheckbox =
        document.getElementById(
            'is_fixed_fuel'
        );

    const distanceInput =
        document.getElementById(
            'distance_km'
        );

    const routeRow =
        document.getElementById(
            'route_row'
        );

    const routeInput =
        document.getElementById(
            'route_input'
        );

    const routeMapElement =
        document.getElementById(
            'routeMap'
        );

    const routeSearchInput =
        document.getElementById(
            'routeSearchInput'
        );

    const routeSearchBtn =
        document.getElementById(
            'routeSearchBtn'
        );

    const useCurrentLocationBtn =
        document.getElementById(
            'useCurrentLocationBtn'
        );

    const finalizeButton =
        document.getElementById(
            'finalizeRouteBtn'
        );


    if (!fixedFuelCheckbox) {
        return;
    }


    const isFixedFuel =
        fixedFuelCheckbox.checked;


    if (isFixedFuel) {

        /* -----------------------------------------------------
           HIDE ROUTE
        ----------------------------------------------------- */

        if (routeRow) {

            routeRow.style.display =
                'none';

        }


        /* -----------------------------------------------------
           Route is not required for Fixed Fuel.
        ----------------------------------------------------- */

        if (routeInput) {

            routeInput.required =
                false;

        }


        /* -----------------------------------------------------
           HIDE DISTANCE
        ----------------------------------------------------- */

        if (distanceInput) {

            const distanceRow =
                distanceInput.closest(
                    '.form-floating'
                );

            if (distanceRow) {

                distanceRow.style.display =
                    'none';

            }

        }


        /* -----------------------------------------------------
           Fixed Fuel always uses distance 0.
        ----------------------------------------------------- */

        if (distanceInput) {

            distanceInput.value =
                '0.1';

        }


        /* -----------------------------------------------------
           Disable map.
        ----------------------------------------------------- */

        if (routeMapElement) {

            routeMapElement.style.pointerEvents =
                'none';

            routeMapElement.style.opacity =
                '0.5';

        }


        /* -----------------------------------------------------
           Disable map controls.
        ----------------------------------------------------- */

        if (routeSearchInput) {

            routeSearchInput.disabled =
                true;

        }

        if (routeSearchBtn) {

            routeSearchBtn.disabled =
                true;

        }

        if (useCurrentLocationBtn) {

            useCurrentLocationBtn.disabled =
                true;

        }

        if (finalizeButton) {

            finalizeButton.disabled =
                true;

        }


        /* -----------------------------------------------------
           Clear existing map route.
        ----------------------------------------------------- */

        clearRoutePlanner();


        /* -----------------------------------------------------
           Distance must remain zero.
        ----------------------------------------------------- */

        if (distanceInput) {

            distanceInput.value =
                '0.1';

        }


    } else {

        /* -----------------------------------------------------
           SHOW ROUTE
        ----------------------------------------------------- */

        if (routeRow) {

            const twoWheelsCheckbox =
                document.getElementById(
                    'twoWheelsRoute'
                );

            routeRow.style.display =
                twoWheelsCheckbox &&
                twoWheelsCheckbox.checked
                    ? ''
                    : 'none';

        }


        /* -----------------------------------------------------
           Restore Route requirement according
           to the 2-Wheels Route setting.
        ----------------------------------------------------- */

        if (routeInput) {

            const twoWheelsCheckbox =
                document.getElementById(
                    'twoWheelsRoute'
                );

            routeInput.required =
                twoWheelsCheckbox
                    ? twoWheelsCheckbox.checked
                    : false;

        }


        /* -----------------------------------------------------
           SHOW DISTANCE
        ----------------------------------------------------- */

        if (distanceInput) {

            const distanceRow =
                distanceInput.closest(
                    '.form-floating'
                );

            if (distanceRow) {

                distanceRow.style.display =
                    '';

            }

        }


        /* -----------------------------------------------------
           Enable map again.
        ----------------------------------------------------- */

        if (routeMapElement) {

            routeMapElement.style.pointerEvents =
                '';

            routeMapElement.style.opacity =
                '';

        }


        /* -----------------------------------------------------
           Enable map controls.
        ----------------------------------------------------- */

        if (routeSearchInput) {

            routeSearchInput.disabled =
                false;

        }

        if (routeSearchBtn) {

            routeSearchBtn.disabled =
                false;

        }

        if (useCurrentLocationBtn) {

            useCurrentLocationBtn.disabled =
                false;

        }


        /* -----------------------------------------------------
           Distance will be calculated
           after route finalization.
        ----------------------------------------------------- */

        if (distanceInput) {

            distanceInput.value =
                '';

        }


        updateDestinationList();

    }

}


/* =========================================================
   INITIALIZE CHECKBOX LISTENERS
========================================================= */

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const twoWheelsCheckbox =
            document.getElementById(
                'twoWheelsRoute'
            );


        if (twoWheelsCheckbox) {

            twoWheelsCheckbox.addEventListener(
                'change',
                toggleTwoWheelsRoute
            );

            toggleTwoWheelsRoute();

        }


        const fixedFuelCheckbox =
            document.getElementById(
                'is_fixed_fuel'
            );


        if (fixedFuelCheckbox) {

            fixedFuelCheckbox.addEventListener(
                'change',
                toggleFixedFuel
            );

            toggleFixedFuel();

        }

    }
);


/* =========================================================
   ROUTE LOADING OVERLAY
========================================================= */

function showRouteLoading(
    message = 'Loading Route...'
) {

    const overlay =
        document.getElementById(
            'routeLoadingOverlay'
        );

    const messageElement =
        document.getElementById(
            'routeLoadingMessage'
        );


    if (!overlay) {

        console.error(
            'routeLoadingOverlay not found.'
        );

        return;
    }


    if (messageElement) {

        messageElement.textContent =
            message;

    }


    overlay.classList.remove(
        'd-none'
    );

}


function hideRouteLoading() {

    const overlay =
        document.getElementById(
            'routeLoadingOverlay'
        );


    if (!overlay) {
        return;
    }


    overlay.classList.add(
        'd-none'
    );

}
