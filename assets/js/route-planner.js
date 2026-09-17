/* =========================================================
   ROUTE MAP INITIALIZATION
========================================================= */

let routeMap = null;


const addRoutesModal =
    document.getElementById(
        'addRoutesModal'
    );


if (addRoutesModal) {

    addRoutesModal.addEventListener(
        'shown.bs.modal',
        function () {

            console.log(
                'Add Routes modal opened'
            );


            /* Check Leaflet */

            if (
                typeof L === 'undefined'
            ) {

                console.error(
                    'Leaflet JavaScript is not loaded.'
                );

                return;
            }


            /* Create map only once */

            if (!routeMap) {

                routeMap =
                    L.map(
                        'routeMap',
                        {
                            minZoom: 5,
                            maxZoom: 18
                        }
                    )
                    .setView(
                        [9.7392, 118.7353],
                        13
                    );


                /* =========================================
                   BASE MAP LAYERS
                ========================================= */

                const streetLayer =
                    L.tileLayer(
                        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                        {
                            maxNativeZoom: 18,
                            maxZoom: 18,
                            attribution:
                                '&copy; OpenStreetMap contributors'
                        }
                    );


                const satelliteLayer =
                    L.tileLayer(
                        'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
                        {
                            maxNativeZoom: 17,
                            maxZoom: 18,
                            attribution:
                                'Tiles &copy; Esri'
                        }
                    );


                /* DEFAULT MAP */

                streetLayer.addTo(
                    routeMap
                );


                /* MAP SWITCHER */

                const baseMaps = {

                    "Street Map":
                        streetLayer,

                    "Satellite":
                        satelliteLayer

                };


                L.control.layers(
                    baseMaps,
                    null,
                    {
                        position:
                            'topright',

                        collapsed:
                            false
                    }
                )
                .addTo(
                    routeMap
                );


                console.log(
                    'Route map created'
                );


                /* =========================================
                   MAP CLICK
                ========================================= */

                routeMap.on(
                    'click',
                    function (e) {

                        /*
                         * Fixed Fuel should never
                         * create map pins.
                         */

                        const fixedFuelCheckbox =
                            document.getElementById(
                                'is_fixed_fuel'
                            );


                        if (
                            fixedFuelCheckbox &&
                            fixedFuelCheckbox.checked
                        ) {

                            return;

                        }


                        /*
                         * Prevent adding pins after
                         * route finalization.
                         */

                        if (
                            routeFinalized
                        ) {

                            const status =
                                document.getElementById(
                                    'routePlannerStatus'
                                );


                            if (status) {

                                status.textContent =
                                    'Route is finalized. Clear the route to create a new route.';

                            }


                            return;
                        }


                        /*
                         * First pin = origin.
                         */

                        if (!originLatLng) {

                            setRouteOrigin(
                                e.latlng.lat,
                                e.latlng.lng
                            );

                        } else {

                            addRouteDestination(
                                e.latlng.lat,
                                e.latlng.lng
                            );

                        }

                    }
                );

            }


            /* Fix map size */

            setTimeout(
                function () {

                    routeMap.invalidateSize();

                },
                300
            );

        }
    );

} else {

    console.error(
        'addRoutesModal not found.'
    );

}


/* =========================================================
   MULTI-DESTINATION PINS
========================================================= */

let originMarker = null;

let originLatLng = null;

let destinationMarkers = [];

let destinationPoints = [];

let routeLayers = [];

let routeFinalized = false;


/* =========================================================
   SET ORIGIN
========================================================= */

function setRouteOrigin(
    lat,
    lng
) {

    originLatLng =
        L.latLng(
            lat,
            lng
        );


    if (originMarker) {

        routeMap.removeLayer(
            originMarker
        );

    }


    originMarker =
        L.marker(
            originLatLng,
            {
                draggable:
                    false
            }
        )
        .addTo(
            routeMap
        )
        .bindPopup(
            'Starting Point'
        )
        .openPopup();


    updateDestinationList();


    document.getElementById(
        'routePlannerStatus'
    ).textContent =
        'Starting point set. Add one or more destinations.';


    generateRoadRoutes();

}


/* =========================================================
   USE CURRENT LOCATION
========================================================= */

const useCurrentLocationBtn =
    document.getElementById(
        'useCurrentLocationBtn'
    );


if (useCurrentLocationBtn) {

    useCurrentLocationBtn.addEventListener(
        'click',
        function () {

            /*
             * Fixed Fuel cannot use location.
             */

            const fixedFuelCheckbox =
                document.getElementById(
                    'is_fixed_fuel'
                );


            if (
                fixedFuelCheckbox &&
                fixedFuelCheckbox.checked
            ) {

                return;

            }


            if (routeFinalized) {

                const status =
                    document.getElementById(
                        'routeLocationStatus'
                    );


                if (status) {

                    status.textContent =
                        'Route is finalized. Clear the route to start again.';

                }


                return;
            }


            if (
                !navigator.geolocation
            ) {

                const status =
                    document.getElementById(
                        'routeLocationStatus'
                    );


                if (status) {

                    status.textContent =
                        'Geolocation is not supported by this browser.';

                }


                return;
            }


            useCurrentLocationBtn.disabled =
                true;


            useCurrentLocationBtn.innerHTML =
                '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';


            useCurrentLocationBtn.title =
                'Getting current location...';


            const status =
                document.getElementById(
                    'routeLocationStatus'
                );


            if (status) {

                status.textContent =
                    'Getting your current location...';

            }


            navigator.geolocation.getCurrentPosition(

                function (position) {

                    const lat =
                        position.coords.latitude;

                    const lng =
                        position.coords.longitude;


                    routeMap.setView(
                        [lat, lng],
                        16
                    );


                    if (!originLatLng) {

                        setRouteOrigin(
                            lat,
                            lng
                        );

                    } else {

                        if (status) {

                            status.textContent =
                                'Starting point is already set.';

                        }

                    }


                    useCurrentLocationBtn.disabled =
                        false;


                    useCurrentLocationBtn.innerHTML =
                        '<i class="bi bi-crosshair"></i>';

                    useCurrentLocationBtn.title =
                        'Use Current Location';

                },


                function (error) {

                    let message =
                        'Unable to get your current location.';


                    switch (
                        error.code
                    ) {

                        case error.PERMISSION_DENIED:

                            message =
                                'Location permission was denied.';

                            break;


                        case error.POSITION_UNAVAILABLE:

                            message =
                                'Location information is unavailable.';

                            break;


                        case error.TIMEOUT:

                            message =
                                'Location request timed out.';

                            break;

                    }


                    if (status) {

                        status.textContent =
                            message;

                    }


                    useCurrentLocationBtn.disabled =
                        false;


                    useCurrentLocationBtn.innerHTML =
                        '<i class="bi bi-crosshair"></i>';

                    useCurrentLocationBtn.title =
                        'Use Current Location';

                },


                {
                    enableHighAccuracy:
                        true,

                    timeout:
                        15000,

                    maximumAge:
                        0
                }

            );

        }
    );

}


/* =========================================================
   ADD DESTINATION
========================================================= */

function addRouteDestination(
    lat,
    lng
) {

    /*
     * Fixed Fuel cannot use map.
     */

    const fixedFuelCheckbox =
        document.getElementById(
            'is_fixed_fuel'
        );


    if (
        fixedFuelCheckbox &&
        fixedFuelCheckbox.checked
    ) {

        return;

    }


    if (!originLatLng) {

        alert(
            'Please set the starting point first.'
        );

        return;
    }


    const destinationNumber =
        destinationPoints.length + 1;


    const latLng =
        L.latLng(
            lat,
            lng
        );


    destinationPoints.push({
        lat: lat,
        lng: lng
    });


    const marker =
        L.marker(
            latLng
        )
        .addTo(
            routeMap
        )
        .bindPopup(
            'Destination ' +
            destinationNumber
        );


    destinationMarkers.push(
        marker
    );


    updateDestinationList();

    generateRoadRoutes();

}


/* =========================================================
   CLEAR ROAD ROUTES
========================================================= */

function clearRouteLines() {

    routeLayers.forEach(
        function (layer) {

            if (
                routeMap &&
                routeMap.hasLayer(
                    layer
                )
            ) {

                routeMap.removeLayer(
                    layer
                );

            }

        }
    );


    routeLayers = [];

}


/* =========================================================
   GET ROAD ROUTE FROM OSRM
========================================================= */

async function getRoadRoute(
    start,
    end
) {

    const url =
        'https://router.project-osrm.org/' +
        'route/v1/driving/' +
        `${start.lng},${start.lat};${end.lng},${end.lat}` +
        '?overview=full' +
        '&geometries=geojson';


    try {

        const response =
            await fetch(
                url
            );


        const data =
            await response.json();


        if (
            data.code !== 'Ok' ||
            !data.routes ||
            !data.routes.length
        ) {

            console.error(
                'OSRM route error:',
                data
            );

            return null;
        }


        return data.routes[0];


    } catch (error) {

        console.error(
            'OSRM routing error:',
            error
        );

        return null;

    }

}


/* =========================================================
   GENERATE ROAD ROUTE
========================================================= */

async function generateRoadRoutes() {

    /*
     * Fixed Fuel does not use road routes.
     */

    const fixedFuelCheckbox =
        document.getElementById(
            'is_fixed_fuel'
        );


    if (
        fixedFuelCheckbox &&
        fixedFuelCheckbox.checked
    ) {

        clearRouteLines();

        return;

    }


    if (
        !originLatLng ||
        destinationPoints.length === 0
    ) {

        clearRouteLines();

        return;
    }


    clearRouteLines();


    const locations = [

        {
            lat:
                originLatLng.lat,

            lng:
                originLatLng.lng
        },

        ...destinationPoints

    ];


    let totalDistance =
        0;


    for (
        let i = 0;
        i < locations.length - 1;
        i++
    ) {

        const start =
            locations[i];

        const end =
            locations[i + 1];


        const route =
            await getRoadRoute(
                start,
                end
            );


        if (!route) {
            continue;
        }


        totalDistance +=
            route.distance / 1000;


        const routeLayer =
            L.geoJSON(
                route.geometry,
                {
                    style: {
                        weight: 5,
                        opacity: 0.85
                    }
                }
            )
            .addTo(
                routeMap
            );


        routeLayers.push(
            routeLayer
        );

    }


    const routeDistance =
        document.getElementById(
            'routeTotalDistance'
        );


    if (routeDistance) {

        routeDistance.textContent =
            totalDistance.toFixed(2) +
            ' km';

    }


    const returnDistance =
        document.getElementById(
            'routeReturnDistance'
        );


    if (returnDistance) {

        returnDistance.textContent =
            '0.00 km';

    }


    const finalDistance =
        document.getElementById(
            'routeFinalDistance'
        );


    if (finalDistance) {

        finalDistance.textContent =
            '0.00 km';

    }


    const distanceInput =
        document.getElementById(
            'distance_km'
        );


    if (distanceInput) {

        distanceInput.value =
            '';

    }

}


/* =========================================================
   FINALIZE ROUTE
========================================================= */

async function finalizeRoute() {

    /*
     * Fixed Fuel routes do not finalize a map route.
     */

    const fixedFuelCheckbox =
        document.getElementById(
            'is_fixed_fuel'
        );


    if (
        fixedFuelCheckbox &&
        fixedFuelCheckbox.checked
    ) {

        const distanceInput =
            document.getElementById(
                'distance_km'
            );


        if (distanceInput) {

            distanceInput.value =
                '0.1';

        }


        return true;

    }


    if (!originLatLng) {

        alert(
            'Please set the starting point first.'
        );

        return false;
    }


    if (
        destinationPoints.length === 0
    ) {

        alert(
            'Please add at least one destination.'
        );

        return false;
    }


    if (routeFinalized) {

        return false;

    }


    const finalizeButton =
        document.getElementById(
            'finalizeRouteBtn'
        );


    if (!finalizeButton) {

        return false;

    }


    finalizeButton.disabled =
        true;

    finalizeButton.textContent =
        'Calculating Return Route...';


    const status =
        document.getElementById(
            'routePlannerStatus'
        );


    if (status) {

        status.textContent =
            'Calculating route and return trip...';

    }


    clearRouteLines();


    const locations = [

        {
            lat:
                originLatLng.lat,

            lng:
                originLatLng.lng
        },

        ...destinationPoints

    ];


    let outgoingDistance =
        0;


    /*
     * ORIGIN → DESTINATION 1 →
     * DESTINATION 2...
     */

    for (
        let i = 0;
        i < locations.length - 1;
        i++
    ) {

        const route =
            await getRoadRoute(
                locations[i],
                locations[i + 1]
            );


        if (!route) {

            continue;

        }


        outgoingDistance +=
            route.distance / 1000;


        const routeLayer =
            L.geoJSON(
                route.geometry,
                {
                    style: {
                        color: '#0d6efd',
                        weight: 5,
                        opacity: 0.85
                    }
                }
            )
            .addTo(
                routeMap
            );


        routeLayers.push(
            routeLayer
        );

    }


    /*
     * LAST DESTINATION → INITIAL PIN
     */

    const lastDestination =
        locations[
            locations.length - 1
        ];


    const startingPoint =
        locations[0];


    const returnRoute =
        await getRoadRoute(
            lastDestination,
            startingPoint
        );


    if (!returnRoute) {

        alert(
            'Unable to calculate the return route.'
        );


        finalizeButton.disabled =
            false;


        finalizeButton.textContent =
            'Finalize Route';


        return false;

    }


    const returnDistance =
        returnRoute.distance / 1000;


    const returnRouteLayer =
        L.geoJSON(
            returnRoute.geometry,
            {
                style: {
                    color: '#dc3545',
                    weight: 4,
                    opacity: 0.9,
                    dashArray: '10, 10'
                }
            }
        )
        .addTo(
            routeMap
        );


    routeLayers.push(
        returnRouteLayer
    );


    /*
     * FINAL DISTANCE
     */

    const finalDistance =
        outgoingDistance +
        returnDistance;


    /*
     * UPDATE DISPLAYS
     */

    const routeTotalDistance =
        document.getElementById(
            'routeTotalDistance'
        );


    if (routeTotalDistance) {

        routeTotalDistance.textContent =
            outgoingDistance.toFixed(2) +
            ' km';

    }


    const routeReturnDistance =
        document.getElementById(
            'routeReturnDistance'
        );


    if (routeReturnDistance) {

        routeReturnDistance.textContent =
            returnDistance.toFixed(2) +
            ' km';

    }


    const routeFinalDistance =
        document.getElementById(
            'routeFinalDistance'
        );


    if (routeFinalDistance) {

        routeFinalDistance.textContent =
            finalDistance.toFixed(2) +
            ' km';

    }


    /*
     * SAVE FINAL DISTANCE
     */

    const distanceInput =
        document.getElementById(
            'distance_km'
        );


    if (distanceInput) {

        distanceInput.value =
            finalDistance.toFixed(2);

    }


    /* =====================================================
       COMPUTE FUEL ALLOCATION - 2 WHEELS

       Formula:
       (Final Distance / 30 km/L)
       + (Number of Destination Pins × 0.6 L)
    ===================================================== */

    const fuelAllocationInput =
        document.getElementById(
            'fuel_allocation'
        );


    const twoWheelsCheckbox =
        document.getElementById(
            'twoWheelsRoute'
        );


    const fixedFuelCheckbox2 =
        document.getElementById(
            'is_fixed_fuel'
        );


    if (
        fuelAllocationInput &&
        twoWheelsCheckbox &&
        twoWheelsCheckbox.checked
    ) {

        const isFixedFuel =
            fixedFuelCheckbox2 &&
            fixedFuelCheckbox2.checked;


        if (!isFixedFuel) {

            const destinationCount =
                destinationPoints.length;


            const fuelAllocation =
                (finalDistance / 30) +
                (
                    destinationCount *
                    0.6
                );


            fuelAllocationInput.value =
                fuelAllocation.toFixed(2);

        }

        /*
         * Fixed Fuel:
         * keep manually entered fuel allocation.
         */

    } else if (fuelAllocationInput) {

        fuelAllocationInput.value =
            '';

    }


    /*
     * SAVE ROUTE DATA
     */

    const routeDataInput =
        document.getElementById(
            'route_data'
        );


    if (routeDataInput) {

        routeDataInput.value =
            JSON.stringify({

                origin:
                    startingPoint,

                destinations:
                    destinationPoints,

                route_distance:
                    outgoingDistance,

                return_distance:
                    returnDistance,

                final_distance:
                    finalDistance

            });

    }


    /*
     * MARK FINALIZED
     */

    routeFinalized =
        true;


    updateDestinationList();


    finalizeButton.textContent =
        'Route Finalized';


    if (status) {

        status.textContent =
            'Route finalized. Return trip added to the starting point.';

    }


    /*
     * FIT COMPLETE ROUTE
     */

    if (
        routeLayers.length > 0
    ) {

        const group =
            L.featureGroup(
                routeLayers
            );


        routeMap.fitBounds(
            group.getBounds(),
            {
                padding: [30, 30]
            }
        );

    }


    return true;

}


document
    .getElementById(
        'finalizeRouteBtn'
    )
    ?.addEventListener(
        'click',
        async function () {

            await finalizeRoute();

        }
    );


/* =========================================================
   UPDATE DESTINATION LIST
========================================================= */

function updateDestinationList() {

    const list =
        document.getElementById(
            'routeDestinationList'
        );


    if (!list) {

        return;

    }


    list.innerHTML =
        '';


    if (!originLatLng) {

        list.innerHTML = `
            <div class="text-muted small p-2">
                No starting point selected.
            </div>
        `;


        const sequenceCount =
            document.getElementById(
                'routeSequenceCount'
            );


        if (sequenceCount) {

            sequenceCount.textContent =
                '0 Locations';

        }


        return;

    }


    /* STARTING POINT */

    const originItem =
        document.createElement(
            'div'
        );


    originItem.className =
        'list-group-item d-flex justify-content-between align-items-center';


    originItem.innerHTML = `
        <div>
            <strong>1. Starting Point</strong><br>
            <small class="text-muted">
                ${originLatLng.lat.toFixed(6)},
                ${originLatLng.lng.toFixed(6)}
            </small>
        </div>

        <button
            type="button"
            class="btn btn-danger btn-sm remove-origin-btn"
            ${routeFinalized ? 'disabled' : ''}
        >
            Clear Route
        </button>
    `;


    list.appendChild(
        originItem
    );


    /* DESTINATIONS */

    destinationPoints.forEach(
        function (
            point,
            index
        ) {

            const item =
                document.createElement(
                    'div'
                );


            item.className =
                'list-group-item d-flex justify-content-between align-items-center';


            item.innerHTML = `
                <div>
                    <strong>${index + 2}. Destination</strong><br>
                    <small class="text-muted">
                        ${point.lat.toFixed(6)},
                        ${point.lng.toFixed(6)}
                    </small>
                </div>

                <button
                    type="button"
                    class="btn btn-danger btn-sm remove-destination-btn"
                    data-index="${index}"
                    ${routeFinalized ? 'disabled' : ''}
                >
                    Remove
                </button>
            `;


            list.appendChild(
                item
            );

        }
    );


    /* UPDATE COUNTS */

    const sequenceCount =
        document.getElementById(
            'routeSequenceCount'
        );


    if (sequenceCount) {

        const count =
            destinationPoints.length +
            1;


        sequenceCount.textContent =
            `${count} ${
                count === 1
                    ? 'Location'
                    : 'Locations'
            }`;

    }


    /* UPDATE FINALIZE BUTTON */

    const finalizeButton =
        document.getElementById(
            'finalizeRouteBtn'
        );


    if (finalizeButton) {

        finalizeButton.disabled =
            !originLatLng ||
            destinationPoints.length === 0 ||
            routeFinalized;

    }

}


/* =========================================================
   REMOVE STARTING POINT
========================================================= */

document.addEventListener(
    'click',
    function (e) {

        const removeOriginButton =
            e.target.closest(
                '.remove-origin-btn'
            );


        if (!removeOriginButton) {

            return;

        }


        if (routeFinalized) {

            return;

        }


        if (originMarker) {

            routeMap.removeLayer(
                originMarker
            );

        }


        destinationMarkers.forEach(
            function (marker) {

                if (
                    marker &&
                    routeMap.hasLayer(
                        marker
                    )
                ) {

                    routeMap.removeLayer(
                        marker
                    );

                }

            }
        );


        clearRouteLines();


        originMarker =
            null;

        originLatLng =
            null;

        destinationMarkers =
            [];

        destinationPoints =
            [];


        const routeDistance =
            document.getElementById(
                'routeTotalDistance'
            );


        if (routeDistance) {

            routeDistance.textContent =
                '0.00 km';

        }


        const returnDistance =
            document.getElementById(
                'routeReturnDistance'
            );


        if (returnDistance) {

            returnDistance.textContent =
                '0.00 km';

        }


        const finalDistance =
            document.getElementById(
                'routeFinalDistance'
            );


        if (finalDistance) {

            finalDistance.textContent =
                '0.00 km';

        }


        const distanceInput =
            document.getElementById(
                'distance_km'
            );


        if (distanceInput) {

            distanceInput.value =
                '';

        }


        const routeData =
            document.getElementById(
                'route_data'
            );


        if (routeData) {

            routeData.value =
                '';

        }


        updateDestinationList();


        const status =
            document.getElementById(
                'routePlannerStatus'
            );


        if (status) {

            status.textContent =
                'Starting point removed. Click the map or search for a new starting point.';

        }

    }
);


/* =========================================================
   REMOVE SELECTED DESTINATION
========================================================= */

document.addEventListener(
    'click',
    function (e) {

        const removeButton =
            e.target.closest(
                '.remove-destination-btn'
            );


        if (!removeButton) {

            return;

        }


        if (routeFinalized) {

            return;

        }


        const index =
            parseInt(
                removeButton.dataset.index,
                10
            );


        if (isNaN(index)) {

            return;

        }


        const marker =
            destinationMarkers[index];


        if (
            marker &&
            routeMap &&
            routeMap.hasLayer(
                marker
            )
        ) {

            routeMap.removeLayer(
                marker
            );

        }


        destinationMarkers.splice(
            index,
            1
        );


        destinationPoints.splice(
            index,
            1
        );


        routeFinalized =
            false;


        clearRouteLines();


        const finalizeButton =
            document.getElementById(
                'finalizeRouteBtn'
            );


        if (finalizeButton) {

            finalizeButton.disabled =
                false;

            finalizeButton.textContent =
                'Finalize Route';

        }


        const returnDistance =
            document.getElementById(
                'routeReturnDistance'
            );


        if (returnDistance) {

            returnDistance.textContent =
                '0.00 km';

        }


        const finalDistance =
            document.getElementById(
                'routeFinalDistance'
            );


        if (finalDistance) {

            finalDistance.textContent =
                '0.00 km';

        }


        const distanceInput =
            document.getElementById(
                'distance_km'
            );


        if (distanceInput) {

            distanceInput.value =
                '';

        }


        updateDestinationList();


        generateRoadRoutes();


        const status =
            document.getElementById(
                'routePlannerStatus'
            );


        if (status) {

            status.textContent =
                'Destination removed. Route has been recalculated.';

        }

    }
);


/* =========================================================
   LOCATION SEARCH - PHILIPPINES
========================================================= */

const routeSearchInput =
    document.getElementById(
        'routeSearchInput'
    );


const routeSearchBtn =
    document.getElementById(
        'routeSearchBtn'
    );


const routeSearchResults =
    document.getElementById(
        'routeSearchResults'
    );


async function searchRouteLocation() {

    /*
     * Fixed Fuel cannot search map locations.
     */

    const fixedFuelCheckbox =
        document.getElementById(
            'is_fixed_fuel'
        );


    if (
        fixedFuelCheckbox &&
        fixedFuelCheckbox.checked
    ) {

        return;

    }


    const query =
        routeSearchInput.value.trim();


    if (!query) {

        routeSearchResults.innerHTML = `
            <div class="list-group-item text-muted">
                Enter a location to search.
            </div>
        `;

        return;

    }


    if (routeFinalized) {

        routeSearchResults.innerHTML = `
            <div class="list-group-item text-danger">
                Route is finalized. Clear the route before adding locations.
            </div>
        `;

        return;

    }


    routeSearchBtn.disabled =
        true;

    routeSearchBtn.textContent =
        'Searching...';


    routeSearchResults.innerHTML = `
        <div class="list-group-item text-muted">
            Searching locations...
        </div>
    `;


    try {

        const url =
            'https://nominatim.openstreetmap.org/search?' +
            'format=jsonv2' +
            '&limit=10' +
            '&countrycodes=ph' +
            '&addressdetails=1' +
            '&q=' +
            encodeURIComponent(
                query
            );


        const response =
            await fetch(
                url,
                {
                    headers: {
                        'Accept':
                            'application/json'
                    }
                }
            );


        if (!response.ok) {

            throw new Error(
                'Search service returned an error.'
            );

        }


        const results =
            await response.json();


        routeSearchResults.innerHTML =
            '';


        if (!results.length) {

            routeSearchResults.innerHTML = `
                <div class="list-group-item text-muted">
                    No Philippine locations found.
                </div>
            `;

            return;

        }


        results.forEach(
            function (result) {

                const button =
                    document.createElement(
                        'button'
                    );


                button.type =
                    'button';


                button.className =
                    'list-group-item list-group-item-action';


                button.textContent =
                    result.display_name;


                button.addEventListener(
                    'click',
                    function () {

                        selectRouteSearchResult(
                            parseFloat(
                                result.lat
                            ),
                            parseFloat(
                                result.lon
                            ),
                            result.display_name
                        );

                    }
                );


                routeSearchResults.appendChild(
                    button
                );

            }
        );


    } catch (error) {

        console.error(
            'Location search error:',
            error
        );


        routeSearchResults.innerHTML = `
            <div class="list-group-item text-danger">
                Unable to search locations. Please try again.
            </div>
        `;

    } finally {

        routeSearchBtn.disabled =
            false;

        routeSearchBtn.textContent =
            'Search';

    }

}


/* =========================================================
   SELECT SEARCH RESULT
========================================================= */

function selectRouteSearchResult(
    lat,
    lng,
    locationName
) {

    const fixedFuelCheckbox =
        document.getElementById(
            'is_fixed_fuel'
        );


    if (
        fixedFuelCheckbox &&
        fixedFuelCheckbox.checked
    ) {

        return;

    }


    if (!routeMap) {

        return;

    }


    routeMap.setView(
        [lat, lng],
        16
    );


    routeSearchResults.innerHTML =
        '';


    routeSearchInput.value =
        locationName;


    if (!originLatLng) {

        setRouteOrigin(
            lat,
            lng
        );

    } else {

        addRouteDestination(
            lat,
            lng
        );

    }

}


/* =========================================================
   SEARCH BUTTON
========================================================= */

if (routeSearchBtn) {

    routeSearchBtn.addEventListener(
        'click',
        searchRouteLocation
    );

}


/* =========================================================
   ENTER KEY
========================================================= */

if (routeSearchInput) {

    routeSearchInput.addEventListener(
        'keydown',
        function (e) {

            if (e.key === 'Enter') {

                e.preventDefault();

                searchRouteLocation();

            }

        }
    );

}


/* =========================================================
   RESET ROUTE PLANNER WHEN MODAL CLOSES
========================================================= */

if (addRoutesModal) {

    addRoutesModal.addEventListener(
        'hidden.bs.modal',
        function () {

            clearRoutePlanner();

        }
    );

}


/* =========================================================
   CLEAR ROUTE PLANNER
========================================================= */

function clearRoutePlanner() {

    /*
     * Remove origin marker
     */

    if (
        originMarker &&
        routeMap &&
        routeMap.hasLayer(
            originMarker
        )
    ) {

        routeMap.removeLayer(
            originMarker
        );

    }


    /*
     * Remove destination markers
     */

    destinationMarkers.forEach(
        function (marker) {

            if (
                marker &&
                routeMap &&
                routeMap.hasLayer(
                    marker
                )
            ) {

                routeMap.removeLayer(
                    marker
                );

            }

        }
    );


    /*
     * Remove route lines
     */

    clearRouteLines();


    /*
     * Reset route variables
     */

    originMarker =
        null;

    originLatLng =
        null;

    destinationMarkers =
        [];

    destinationPoints =
        [];

    routeFinalized =
        false;


    updateDestinationList();


    /*
     * Reset distances
     */

    const routeDistance =
        document.getElementById(
            'routeTotalDistance'
        );


    if (routeDistance) {

        routeDistance.textContent =
            '0.00 km';

    }


    const returnDistance =
        document.getElementById(
            'routeReturnDistance'
        );


    if (returnDistance) {

        returnDistance.textContent =
            '0.00 km';

    }


    const finalDistance =
        document.getElementById(
            'routeFinalDistance'
        );


    if (finalDistance) {

        finalDistance.textContent =
            '0.00 km';

    }


    /*
     * Clear distance field
     */

    const distanceInput =
        document.getElementById(
            'distance_km'
        );


    if (distanceInput) {

        distanceInput.value =
            '';

    }


    /*
     * Clear route data
     */

    const routeData =
        document.getElementById(
            'route_data'
        );


    if (routeData) {

        routeData.value =
            '';

    }


    /*
     * Clear search
     */

    const routeSearchInput =
        document.getElementById(
            'routeSearchInput'
        );


    if (routeSearchInput) {

        routeSearchInput.value =
            '';

    }


    /*
     * Clear search results
     */

    const routeSearchResults =
        document.getElementById(
            'routeSearchResults'
        );


    if (routeSearchResults) {

        routeSearchResults.innerHTML =
            '';

    }


    /*
     * Reset Finalize button
     */

    const finalizeButton =
        document.getElementById(
            'finalizeRouteBtn'
        );


    if (finalizeButton) {

        finalizeButton.disabled =
            true;

        finalizeButton.textContent =
            'Finalize Route';

    }


    /*
     * Reset status
     */

    const status =
        document.getElementById(
            'routePlannerStatus'
        );


    if (status) {

        status.textContent =
            'Click the map to set a starting point.';

    }


    /*
     * Return map to default view
     */

    if (routeMap) {

        routeMap.setView(
            [9.7392, 118.7353],
            10
        );

    }

}


/* =========================================================
   INITIALIZE ROUTE PLANNER WHEN MODAL OPENS
========================================================= */

if (addRoutesModal) {

    addRoutesModal.addEventListener(
        'shown.bs.modal',
        function () {

            const finalizeButton =
                document.getElementById(
                    'finalizeRouteBtn'
                );


            if (finalizeButton) {

                finalizeButton.disabled =
                    !originLatLng ||
                    destinationPoints.length === 0 ||
                    routeFinalized;


                finalizeButton.textContent =
                    'Finalize Route';

            }


            /*
             * Re-apply Fixed Fuel state after
             * the modal and Leaflet map are ready.
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

            }

        }
    );

}


/* =========================================================
   CONFIRM BEFORE CLOSING ROUTE MODAL
========================================================= */

let allowRouteModalClose =
    false;


if (addRoutesModal) {

    addRoutesModal.addEventListener(
        'hide.bs.modal',
        function (event) {

            /*
             * Allow closing after confirmation.
             */

            if (
                allowRouteModalClose
            ) {

                return;

            }


            /*
             * Check current modal mode.
             */

            const formMode =
                document.getElementById(
                    'form_mode'
                )?.value ||
                'add';


            /*
             * Existing saved routes
             * can close normally.
             */

            if (
                formMode === 'edit'
            ) {

                return;

            }


            /*
             * Fixed Fuel has no map route,
             * so there is nothing to discard.
             */

            const fixedFuelCheckbox =
                document.getElementById(
                    'is_fixed_fuel'
                );


            if (
                fixedFuelCheckbox &&
                fixedFuelCheckbox.checked
            ) {

                return;

            }


            /*
             * Only new routes can have
             * an unsaved map route.
             */

            const hasRoute =
                originLatLng ||
                destinationPoints.length > 0;


            if (!hasRoute) {

                return;

            }


            /*
             * Stop modal from closing.
             */

            event.preventDefault();


            /*
             * Show confirmation.
             */

            Swal.fire({

                title:
                    'Discard Route?',

                text:
                    'The current starting point, destinations, and route will be cleared.',

                icon:
                    'warning',

                showCancelButton:
                    true,

                confirmButtonText:
                    'Discard Route',

                cancelButtonText:
                    'Keep Editing',

                confirmButtonColor:
                    '#dc3545',

                reverseButtons:
                    true

            })
            .then(
                function (result) {

                    if (
                        result.isConfirmed
                    ) {

                        allowRouteModalClose =
                            true;


                        const modalInstance =
                            bootstrap.Modal.getInstance(
                                addRoutesModal
                            );


                        if (
                            modalInstance
                        ) {

                            modalInstance.hide();

                        }

                    }

                }
            );

        }
    );


    /* =====================================================
       RESET PLANNER AFTER MODAL FULLY CLOSES
    ===================================================== */

    addRoutesModal.addEventListener(
        'hidden.bs.modal',
        function () {

            clearRoutePlanner();


            allowRouteModalClose =
                false;

        }
    );

}
