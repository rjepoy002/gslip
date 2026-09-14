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
  // SAVE TEMPLATE
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
      created_by: window.APP?.USER_ID || 0,
      data: collectTemplateData()
    };

    // ===============================
    // DEBUG PREVIEW - START
    // ===============================
    // console.log(payload);

    // Swal.fire({
    //   title: 'Template Preview',
    //   html: `
    //     <pre style="
    //       text-align:left;
    //       font-size:12px;
    //       max-height:400px;
    //       overflow:auto;
    //       background:#f8f9fa;
    //       padding:10px;
    //       border-radius:6px;
    //     ">${JSON.stringify(payload, null, 2)}</pre>
    //   `,
    //   width: 800
    // });

    // // stop saving temporarily
    // return;

    // ===============================
    // DEBUG PREVIEW - END
    // ===============================


    // disable button while saving
    btn.disabled = true;

    fetch('save_template.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(payload)
    })
    .then(async res => {
      const text = await res.text();
      console.log(text);

      return JSON.parse(text);
    })
    .then(res => {

      btn.disabled = false;

      if (res.success) {

        Swal.fire({
          icon: 'success',
          title: 'Template Saved',
          timer: 1500,
          showConfirmButton: false
        });

        // clear template name
        document.getElementById('template_name').value = '';

        // close modal
        const modalEl = document.getElementById('templateModal');
        const modal = bootstrap.Modal.getInstance(modalEl);

        if (modal) modal.hide();

      } else {

        Swal.fire({
          icon: 'error',
          title: 'Save Failed',
          text: res.message || 'Unable to save template'
        });

      }

    })
    .catch(err => {

      btn.disabled = false;

      Swal.fire({
        icon: 'error',
        title: 'Server Error',
        text: err.message
      });

      console.error(err);

    });

  });

});