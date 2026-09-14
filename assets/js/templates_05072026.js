document.addEventListener('DOMContentLoaded', function () {

  // ===============================
  // COLLECT DATA FROM GRID
  // ===============================
  function collectTemplateData() {
    const rows = document.querySelectorAll('#gasSlipGrid tbody tr');

    let data = [];

    rows.forEach(row => {
      data.push({
        validity_until: row.querySelector('[name="validity_until[]"]')?.value || '',
        purpose: row.querySelector('[name="purpose[]"]')?.value || '',
        requested_by: row.querySelector('[name="requested_by[]"]')?.value || '',
        vehicle_id: row.querySelector('[name="vehicle_id[]"]')?.value || '',
        destinations: JSON.parse(row.querySelector('.destinations-data')?.value || '[]'),
        fuel_requests: JSON.parse(row.querySelector('.fuel-data')?.value || '[]')
      });
    });

    return data;
  }

  // ===============================
  // SAVE TEMPLATE BUTTON (PREVIEW ONLY)
  // ===============================
  const btn = document.getElementById('saveTemplateBtn');
  if (!btn) return;

  btn.addEventListener('click', function () {

    const templateName = document.getElementById('template_name').value.trim();

    if (!templateName) {
      Swal.fire({
        icon: 'warning',
        title: 'Template name required'
      });
      return;
    }

    const payload = {
      name: templateName,
      created_by: window.APP?.USER_ID || 0, // ✅ added here
      data: collectTemplateData()
    };

    // ===============================
    // DISPLAY OUTPUT (DEBUG)
    // ===============================
    const output = document.getElementById('templateOutput');
    if (output) {
      output.textContent = JSON.stringify(payload, null, 2);
    }

    // Optional: also show popup preview
    Swal.fire({
      title: 'Template Preview',
      html: `<pre style="text-align:left; font-size:12px; max-height:300px; overflow:auto;">${JSON.stringify(payload, null, 2)}</pre>`,
      width: 700
    });

    // close modal
    const modalEl = document.getElementById('templateModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();

  });

});