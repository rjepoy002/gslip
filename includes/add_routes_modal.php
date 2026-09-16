<!-- =========================================================
     ADD / EDIT ROUTES MODAL
========================================================= -->

<div class="modal fade"
     id="addRoutesModal"
     tabindex="-1"
     aria-labelledby="formTitle"
     aria-hidden="true">

    <div class="modal-dialog modal-route modal-dialog-scrollable">

        <div class="modal-content">

            <form id="RoutesForm" method="POST">

                <!-- Hidden Fields -->
                <input type="hidden" name="routes_id" id="routes_id">
                <input type="hidden" name="form_mode" id="form_mode" value="add">

                <!-- Future route data -->
                <input type="hidden" name="route_points" id="route_points">
                <input type="hidden" name="route_data" id="route_data">


                <!-- =================================================
                     HEADER
                ================================================== -->

                <div class="modal-header">

                    <h5 class="modal-title" id="formTitle">
                        Add New Routes
                    </h5>

                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                            aria-label="Close">
                    </button>

                </div>


                <!-- =================================================
                     BODY
                ================================================== -->

                <div class="modal-body" style="max-height: calc(100vh - 180px); overflow-y: auto;">

                    <div class="row g-3">


                        <!-- =========================================
                             LEFT PANEL - ROUTE DETAILS
                        ========================================== -->

                        <div class="col-lg-2">

                            <div class="card h-100">

                                <div class="card-header">
                                    <strong>Route Details</strong>
                                </div>

                                <div class="card-body">


                                    <!-- AREA -->

                                    <div class="form-floating mb-2">

                                        <select name="area"
                                                id="area"
                                                class="form-select"
                                                required
                                                onchange="copyAreaToOrigin()">

                                            <option value="" disabled selected>
                                                -- Select Area --
                                            </option>

                                            <?php while ($row = $areaResult->fetch_assoc()): ?>
                                                <option value="<?= htmlspecialchars($row['area_name']) ?>">
                                                    <?= htmlspecialchars($row['area_name']) ?>
                                                </option>
                                            <?php endwhile; ?>

                                        </select>

                                        <label for="area">
                                            Area
                                        </label>

                                    </div>


                                    <!-- 2-WHEELS ROUTE -->

                                    <div class="form-check form-switch mb-3">

                                        <input class="form-check-input"
                                            type="checkbox"
                                            id="twoWheelsRoute">

                                        <label class="form-check-label"
                                            for="twoWheelsRoute">
                                            2-Wheels Route
                                        </label>

                                    </div>

                                    <!-- FIXED FUEL -->

                                    <div id="fixed_fuel_row"
                                        class="form-check form-switch mb-3"
                                        style="display:none;">

                                        <input class="form-check-input"
                                            type="checkbox"
                                            name="is_fixed_fuel"
                                            id="is_fixed_fuel"
                                            value="1">

                                        <label class="form-check-label"
                                            for="is_fixed_fuel">
                                            Fixed Fuel
                                        </label>

                                    </div>

                                    <!-- ROUTE -->

                                    <div id="route_row"
                                        style="display:none;">

                                        <div class="form-floating mb-2">

                                            <input type="text"
                                                name="route"
                                                id="route_input"
                                                class="form-control"
                                                maxlength="6"
                                                placeholder="e.g., PX0453">

                                            <label for="route_input">
                                                Route
                                                <small style="font-size:0.70em;">
                                                    [e.g., PX0453]
                                                </small>
                                            </label>

                                        </div>

                                    </div>


                                    <!-- ORIGIN -->

                                    <div class="form-floating mb-2">

                                        <input type="text"
                                               name="origin"
                                               id="origin"
                                               class="form-control"
                                               placeholder="Origin"
                                               required>

                                        <label for="origin">
                                            Origin
                                        </label>

                                    </div>


                                    <!-- DESTINATION -->

                                    <div class="form-floating mb-2">

                                        <input type="text"
                                               name="destination"
                                               id="destination"
                                               class="form-control"
                                               placeholder="Destination"
                                               required>

                                        <label for="destination">
                                            Destination
                                        </label>

                                    </div>


                                    <!-- DISTANCE -->

                                    <div class="form-floating mb-2">

                                        <input type="number"
                                               name="distance_km"
                                               id="distance_km"
                                               class="form-control"
                                               step="0.01"
                                               min="0"
                                               placeholder="Distance (km)"
                                               required
                                               readonly>

                                        <label for="distance_km">
                                            Distance (km)
                                        </label>

                                    </div>


                                    <!-- =========================================
                                        FUEL ALLOCATION
                                    ========================================== -->

                                    <div id="fuel_allocation_row"
                                        style="display:none;">

                                        <div class="form-floating mb-2">

                                            <input type="number"
                                                name="fuel_allocation"
                                                id="fuel_allocation"
                                                class="form-control"
                                                step="0.01"
                                                min="0"
                                                placeholder="Fuel Allocation (L)">

                                            <label for="fuel_allocation">
                                                Fuel Allocation (L)
                                            </label>

                                        </div>

                                    </div>


                                    <!-- STATUS -->

                                    <div class="form-floating mb-2">

                                        <select name="status"
                                                id="status"
                                                class="form-select"
                                                required>

                                            <option value="" disabled selected>
                                                -- Select Status --
                                            </option>

                                            <option value="active">
                                                Active
                                            </option>

                                            <option value="inactive">
                                                Inactive
                                            </option>

                                        </select>

                                        <label for="status">
                                            Status
                                        </label>

                                    </div>


                                    <!-- REMARKS -->

                                    <div class="form-floating mb-2">

                                        <input type="text"
                                               name="remarks"
                                               id="remarks"
                                               class="form-control"
                                               placeholder="Remarks">

                                        <label for="remarks">
                                            Remarks
                                        </label>

                                    </div>

                                    <!-- ACTION BUTTONS -->

                                    <div class="d-flex justify-content-end gap-2 mt-3">

                                        <button type="button"
                                                id="cancelEditBtn"
                                                class="btn btn-secondary"
                                                data-bs-dismiss="modal">
                                            Cancel
                                        </button>

                                        <button type="submit"
                                                id="addBtn"
                                                class="btn btn-success btn-sm">
                                            Save Routes
                                        </button>

                                        <button type="submit"
                                                id="updateBtn"
                                                class="btn btn-primary btn-sm d-none">
                                            Update Routes
                                        </button>
                                        
                                    </div>


                                </div>

                            </div>

                        </div>


                        <!-- =========================================
                             RIGHT PANEL - MAP
                        ========================================== -->

                        <div class="col-lg-8">

                            <div class="card h-100">

                                <div class="card-header d-flex
                                            justify-content-between
                                            align-items-center">

                                    <strong>
                                        Multi-Destination Route Planner
                                    </strong>

                                </div>


                                <div class="card-body">


                                    <!-- SEARCH + CURRENT LOCATION -->

                                    <div class="input-group mb-2">

                                        <input
                                            type="text"
                                            id="routeSearchInput"
                                            class="form-control"
                                            placeholder="Search location..."
                                            autocomplete="off"
                                        >

                                        <button
                                            type="button"
                                            class="btn btn-primary"
                                            id="routeSearchBtn"
                                            title="Search Location"
                                        >
                                            <i class="bi bi-search"></i>
                                        </button>

                                        <button
                                            type="button"
                                            class="btn btn-outline-primary"
                                            id="useCurrentLocationBtn"
                                            title="Use Current Location"
                                            aria-label="Use Current Location"
                                        >
                                            <i class="bi bi-crosshair"></i>
                                        </button>

                                    </div>


                                    <!-- SEARCH RESULTS -->

                                    <div
                                        id="routeSearchResults"
                                        class="list-group mb-2"
                                        style="max-height:250px; overflow-y:auto;">
                                    </div>

                                    <!-- MAP -->

                                    <div id="routeMap"
                                        class="border rounded">
                                    </div>


                                    <!-- DISTANCE SUMMARY -->

                                    <div class="row mt-3">

                                        <div class="col-md-4">

                                            <div class="border rounded p-2">

                                                <div class="small text-muted">
                                                    Route
                                                </div>

                                                <strong id="routeTotalDistance">
                                                    0.00 km
                                                </strong>

                                            </div>

                                        </div>


                                        <div class="col-md-4">

                                            <div class="border rounded p-2">

                                                <div class="small text-muted">
                                                    Return
                                                </div>

                                                <strong id="routeReturnDistance">
                                                    0.00 km
                                                </strong>

                                            </div>

                                        </div>


                                        <div class="col-md-4">

                                            <div class="border rounded p-2">

                                                <div class="small text-muted">
                                                    Final Distance
                                                </div>

                                                <strong id="routeFinalDistance">
                                                    0.00 km
                                                </strong>

                                            </div>

                                        </div>

                                    </div>


                                    <div id="routePlannerStatus"
                                         class="small text-muted mt-2">

                                        Add a starting point and destinations.

                                    </div>

                                </div>

                            </div>

                        </div>

                        <!-- =========================================
                            RIGHT PANEL - DESTINATION SEQUENCE
                        ========================================= -->

                        <div class="col-lg-2">

                            <div class="card h-100">

                                <div class="card-header d-flex
                                            justify-content-between
                                            align-items-center">

                                    <strong>Destination Sequence</strong>

                                    <span class="badge bg-secondary"
                                        id="routeSequenceCount">
                                        0 Locations
                                    </span>

                                </div>

                                <div class="card-body">

                                    <div id="routeDestinationList"
                                        class="list-group"
                                        style="max-height:550px;
                                                overflow-y:auto;">

                                        <div class="text-muted small p-2">
                                            No destinations added.
                                        </div>

                                    </div>

                                </div>

                                <!-- FINALIZE ROUTE -->

                                <div class="mt-3 pt-2 border-top">

                                    <button type="button"
                                            id="finalizeRouteBtn"
                                            class="btn btn-success w-100"
                                            disabled>
                                        Finalize Route
                                    </button>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </form>


            <!-- =========================================
                 ROUTE LOADING OVERLAY
            ========================================== -->

            <div id="routeLoadingOverlay"
                 class="route-loading-overlay d-none">

                <div class="route-loading-content">

                    <div class="spinner-border text-primary"
                         role="status">
                        <span class="visually-hidden">
                            Loading...
                        </span>
                    </div>

                    <div id="routeLoadingMessage"
                         class="mt-3 fw-semibold">
                        Loading Route...
                    </div>

                    <div class="small text-muted mt-1">
                        Please wait while the route is being loaded.
                    </div>

                </div>

            </div>


        </div>

    </div>

</div>