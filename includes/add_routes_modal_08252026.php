
<!-- /* ========================================================= -->
  <!-- ADD Routes MODAL -->
<!-- ========================================================= */ -->

<div class="modal fade" id="addRoutesModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">

      <form id="RoutesForm" method="POST">

        <input type="hidden" name="routes_id" id="routes_id">
        <input type="hidden" name="form_mode" id="form_mode" value="add">

        <div class="modal-header">
          <h5 class="modal-title" id="formTitle">Add New Routes</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
      
          <div class="form-floating mb-2">
            <select name="area" id="area" class="form-select" required onchange="copyAreaToOrigin()">
              <option value="" disabled selected>-- Select Area --</option>

              <?php while ($row = $areaResult->fetch_assoc()): ?>
                <option value="<?= htmlspecialchars($row['area_name']) ?>">
                  <?= htmlspecialchars($row['area_name']) ?>
                </option>
              <?php endwhile; ?>

            </select>
            <label for="area">Area</label>
          </div>

          <div class="form-floating mb-2">
            <input
              type="text"
              name="route"
              id="route_input"
              class="form-control"
              maxlength="6"
              placeholder="e.g., PX0453"
            />
            <label for="route">Route <small style="font-size:0.70em;">[e.g. PX0453] </small><small class="text-danger" style="font-size:0.70em;">Leave blank if not applicable</small> </label>
          </div>

          <div class="form-floating mb-2">
            <input
              type="text"
              name="origin"
              id="origin"
              class="form-control"
              placeholder="Origin"
              required
            />
            <label for="origin">Origin</label>
          </div>

          <div class="form-floating mb-2">
            <input
              type="text"
              name="destination"
              id="destination"
              class="form-control"
              placeholder="Destination"
              required
            />
            <label for="destination">Destination</label>
          </div>

          <div class="form-floating mb-2">
            <input
              type="number"
              name="distance_km"
              id="distance_km"
              class="form-control"
              step="0.1"
              placeholder="Distance (km)"
              required
            />
            <label for="distance_km">Distance (km)</label>
          </div>

          <div class="form-floating mb-2" id="fuel_allocation_row">
            <input
              type="number"
              name="fuel_allocation"
              id="fuel_allocation"
              class="form-control"
              step="0.1"
              placeholder="Fuel Allocation (L)"
              required
            />
            <label for="fuel_allocation">Fuel Allocation</label>
          </div>

          <!-- Status -->
          <div class="form-floating mb-2">
            <select name="status" id="status" class="form-select" required>
              <option value="" disabled selected>-- Select Status --</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
            <label for="status">Status</label>
          </div>

          <!-- Remarks -->
          <div class="form-floating mb-2">
            <input type="text"
                  name="remarks"
                  id="remarks"
                  class="form-control"
                  placeholder="Remarks">
            <label for="remarks">Remarks</label>
          </div>

        </div>  

        <div class="modal-footer">
          <button type="button" 
            id="cancelEditBtn"
            class="btn btn-secondary" 
            data-bs-dismiss="modal">
            Cancel
          </button>
          <button type="submit" id="addBtn" class="btn btn-success btn-sm">
            Save Routes
          </button>

          <button type="submit" id="updateBtn" class="btn btn-primary btn-sm d-none">
              Update Routes
          </button>
        </div>

      </form>

    </div>
  </div>
</div>
