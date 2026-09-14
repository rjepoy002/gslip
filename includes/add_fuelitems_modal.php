
<!-- /* ========================================================= -->
  <!-- ADD Fuel Items MODAL -->
<!-- ========================================================= */ -->

<div class="modal fade" id="addFuelItemsModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">

      <form id="fuelitemsform" method="POST">

        <input type="hidden" name="fuel_item_id" id="fuel_item_id">
        <input type="hidden" name="form_mode" id="form_mode" value="add">

        <div class="modal-header">
          <h5 class="modal-title" id="formTitle">Add New Fuel Items</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          
          <!-- Fuel Name -->
          <div class="form-floating mb-2">
            <input type="text"
                  name="fuel_name"
                  required
                  id="fuel_name"
                  class="form-control"
                  placeholder="Fuel Name">
            <label for="fuel_name">Fuel Name</label>
          </div>

          <!-- Unit -->
          <div class="form-floating mb-2">
            <input type="text"
                  name="unit"
                  required
                  id="unit"
                  class="form-control"
                  placeholder="Unit">
            <label for="unit">Unit</label>
          </div>

          <!-- Container -->
          <div class="form-floating mb-2">
            <select name="container" id="container" class="form-select" required>
              <option value="" disabled selected>-- Select Container --</option>
              <option value="yes">Yes</option>
              <option value="no">No</option>
            </select>
            <label for="container">Container</label>
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
            Save Fuel Item
          </button>

          <button type="submit" id="updateBtn" class="btn btn-primary btn-sm d-none">
              Update Fuel Item
          </button>
        </div>

      </form>

    </div>
  </div>
</div>
