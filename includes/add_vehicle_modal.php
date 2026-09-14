
<!-- /* ========================================================= -->
  <!-- ADD VEHICLE MODAL -->
<!-- ========================================================= */ -->

<div class="modal fade" id="addVehicleModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">

      <form id="vehicleForm" method="POST">

        <input type="hidden" name="vehicle_id" id="vehicle_id">
        <input type="hidden" name="form_mode" id="form_mode" value="add">

        <div class="modal-header">
          <h5 class="modal-title" id="formTitle">Add New Vehicle</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">

          <div class="form-floating mb-2">
            <input type="text" name="plate_no" class="form-control"
                  id="plate_no" placeholder="Plate No" required>
            <label for="plate_no">Plate No</label>
          </div>

          <div class="form-floating mb-2">
            <input type="text" name="brand" class="form-control"
                  id="brand" placeholder="Brand" required>
            <label for="brand">Brand</label>
          </div>

          <div class="form-floating mb-2">
            <input type="text" name="model" class="form-control"
                  id="model" placeholder="Model" required>
            <label for="model">Model</label>
          </div>

          <div class="form-floating mb-2">
            <select name="category" id="category" class="form-select" required>
              <option value="" disabled selected>-- Select --</option>
              <option value="4-wheels">4-Wheels</option>
              <option value="2-wheels">2-Wheels</option>
              <option value="trucks">Trucks</option>
            </select>
            <label for="category">Category</label>
          </div>

          <div class="form-floating mb-3 position-relative">
            <input type="number"
                  class="form-control pe-5"
                  step="0.10"
                  id="km_per_liter"
                  name="km_per_liter"
                  placeholder="e.g. 12.50" required>

            <label for="km_per_liter">KM per Liter</label>

            <!-- IMPORTANT: Use data-bs-dismiss -->
            <button type="button"
                    class="btn btn-sm position-absolute top-50 end-0 translate-middle-y me-2 border-0 bg-transparent"
                    data-bs-dismiss="modal"
                    data-bs-toggle="modal"
                    data-bs-target="#kmModal">
                <i class="fa fa-car"></i>
            </button>
          </div>

          <div id="idlingRateRow" class="form-floating mb-2" style="display: none;">
            <input type="number"
                  step="0.01"
                  name="idling_rate"
                  id="idling_rate"
                  class="form-control"
                  placeholder="e.g. 1.5">
            <label for="idling_rate">Idle Rate (L/hr)</label>
          </div>

          <!-- Ownership -->
          <div class="form-floating mb-2">
            <select name="ownership" id="ownership" class="form-select" required>
              <option value="" disabled selected>Select Ownership</option>
              <option value="coop-owned">Coop-Owned</option>
              <option value="private">Private</option>
            </select>
            <label for="ownership">Ownership</label>
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
            Save Vehicle
          </button>

          <button type="submit" id="updateBtn" class="btn btn-primary btn-sm d-none">
              Update Vehicle
          </button>
        </div>

      </form>

    </div>
  </div>
</div>