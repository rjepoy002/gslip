document.addEventListener('DOMContentLoaded', function () {

  // ===============================
  // QUICK TEMPLATE DROPDOWN
  // ===============================
    document.getElementById('quickTemplateLoad')
    ?.addEventListener('change', function () {

    const templateId = this.value;
    const currentTemplateId = String(window.TEMPLATE_ID || '');

    // ==========================
    // NOTHING SELECTED
    // ==========================
    if (!templateId) {

        window.isDirty = false;

        window.location.href = 'create_gas_slip.php';

        return;
    }

    // ==========================
    // SAME TEMPLATE SELECTED
    // CLEAR PAGE
    // ==========================
    if (templateId === currentTemplateId) {

        Swal.fire({
        title: 'Clear Current Template?',
        text: 'This will reset the gas slip form.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Clear'
        }).then(result => {

        if (!result.isConfirmed) return;

        window.isDirty = false;

        window.location.href = 'create_gas_slip.php';

        });

        return;
    }

    // ==========================
    // LOAD DIFFERENT TEMPLATE
    // ==========================
    window.isDirty = false;

    window.location.href =
        'create_gas_slip.php?template_id=' +
        encodeURIComponent(templateId);

    });

  // ===============================
  // CHECK TEMPLATE ID
  // ===============================
  if (!window.TEMPLATE_ID || window.TEMPLATE_ID <= 0) {
    return;
  }

  // ===============================
  // FETCH TEMPLATE
  // ===============================
  fetch('load_template.php?id=' + encodeURIComponent(window.TEMPLATE_ID))
    .then(res => res.json())
    .then(res => {

      if (!res.success) {

        Swal.fire({
          icon: 'error',
          title: 'Template Load Failed',
          text: res.message || 'Unable to load template'
        });

        return;
      }

      const rows = res.rows || [];

      if (rows.length === 0) {
        return;
      }

      const tbody = document.querySelector('#gasSlipGrid tbody');
      
      if (!tbody) return;
        const originalRowTemplate =
        document.querySelector('#gasSlipGrid tbody tr')?.cloneNode(true);

      // ===============================
      // REMOVE EXISTING ROWS
      // ===============================
      tbody.innerHTML = '';

      // ===============================
      // BUILD ROWS
      // ===============================
      rows.forEach((rowData, index) => {

        const row = createTemplateRow(
            rowData,
            index,
            originalRowTemplate
            );

        tbody.appendChild(row);

      });

      // ===============================
      // RENUMBER
      // ===============================
      if (typeof renumberRows === 'function') {
        renumberRows();
      }

      Swal.fire({
        icon: 'success',
        title: 'Template Loaded',
        timer: 1200,
        showConfirmButton: false
      });

    })
    .catch(err => {

      console.error(err);

      Swal.fire({
        icon: 'error',
        title: 'Server Error',
        text: err.message
      });

    });

});


// =====================================
// CREATE TEMPLATE ROW
// =====================================
function createTemplateRow(data, index, originalRowTemplate) {

  // clone first row template
  const baseRow = document.querySelector('#gasSlipGrid tbody tr');

  const row = originalRowTemplate.cloneNode(true);

  // =====================================
  // RESET INPUTS
  // =====================================
  row.querySelectorAll('input').forEach(input => {
    input.value = '';
  });

  // =====================================
  // VALIDITY
  // =====================================
    if (typeof setValidityPlusOne === 'function') {
    setValidityPlusOne(row);
    }

  // =====================================
  // PURPOSE
  // =====================================
  const purpose = row.querySelector('[name="purpose[]"]');

  if (purpose) {
    purpose.value = data.purpose || '';
  }

  // =====================================
  // REQUESTED BY
  // =====================================
  const requestedBy = row.querySelector('[name="requested_by[]"]');

  if (requestedBy) {
    requestedBy.value = data.requested_by || '';
  }

    // =====================================
    // VEHICLE
    // =====================================
    const vehicleId = row.querySelector('[name="vehicle_id[]"]');

    if (vehicleId) {
    vehicleId.value = data.vehicle_id || '';
    }

    // display text
    const vehicleDisplay = row.querySelector('.vehicle-display');

    if (vehicleDisplay) {

    const plate = data.plate_no || '';
    const brand = data.brand || '';
    const model = data.model || '';

    vehicleDisplay.value =
        `${plate} - ${brand} ${model}`.trim();

    }

// category
const categoryInput = row.querySelector('.vehicle-category');

if (categoryInput) {
  categoryInput.value = data.category || '';
}

// efficiency
const efficiencyInput = row.querySelector('.vehicle-efficiency');

if (efficiencyInput) {
  efficiencyInput.value = data.km_per_liter || '';
}

  // =====================================
  // DESTINATIONS
  // =====================================
  const destInput = row.querySelector('.destinations-data');

  if (destInput) {

    destInput.value = JSON.stringify(
      data.destinations || []
    );

    const count = row.querySelector('.dest-count');

    if (count) {
      count.textContent =
        (data.destinations || []).length;
    }
  }

  // =====================================
  // FUEL REQUESTS
  // =====================================
  const fuelInput = row.querySelector('.fuel-data');

  if (fuelInput) {

    fuelInput.value = JSON.stringify(
      data.fuel_requests || []
    );

    const count = row.querySelector('.fuel-count');

    if (count) {
      count.textContent =
        (data.fuel_requests || []).length;
    }
  }

  // =====================================
  // ROW NUMBER
  // =====================================
  const rowNo = row.querySelector('.row-number');

  if (rowNo) {
    rowNo.textContent = index + 1;
  }

  return row;
}
