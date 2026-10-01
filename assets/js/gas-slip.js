/* =========================================================
   GAS SLIP INTERACTIONS
========================================================= */

document.addEventListener('DOMContentLoaded', function () {
  initGasSlipRowModal();
  initRecommendHandler();
  initApproveHandler();
  initWithdrawHandler();
});

/* ================= MODAL ================= */
function initGasSlipRowModal() {
  const modalEl = document.getElementById('gasSlipModal');
  if (!modalEl) return;

  document.querySelectorAll('.gas-slip-row').forEach(row => {
    const open = () => openGasSlipDetails(row.dataset.id);

    row.addEventListener('click', function (e) {

      // ❌ Do NOT open modal if click came from checkbox column
      if (e.target.closest('.no-modal')) {
        return;
      }

      open();
    });

    row.addEventListener('keydown', e => e.key === 'Enter' && open());
  });
}

/* Shared by Approved Slips and Dashboard Recent Gas Slips. */
function openGasSlipDetails(id) {
  const modalEl = document.getElementById('gasSlipModal');
  const modalBody = document.getElementById('gasSlipModalBody');
  const modalTitle = document.getElementById('gasSlipModalTitle');
  const gasSlipId = Number.parseInt(id, 10);

  if (!modalEl || !modalBody || !modalTitle || !Number.isInteger(gasSlipId) || gasSlipId < 1) {
    return;
  }

  const gasSlipModal = bootstrap.Modal.getOrCreateInstance(modalEl);
  modalTitle.textContent = 'Gas Slip Details';
  modalBody.innerHTML = `<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>`;
  gasSlipModal.show();

  fetch(`view_gas_slip_modal.php?id=${encodeURIComponent(gasSlipId)}&mode=json`)
    .then(async response => {
      const data = await response.json();
      if (!response.ok || data.error) {
        throw new Error(data.error || 'Unable to load gas slip details.');
      }
      return data;
    })
    .then(data => {
      modalTitle.replaceChildren(
        document.createTextNode('Gas Slip No. '),
        Object.assign(document.createElement('strong'), { textContent: data.gas_slip_id }),
        Object.assign(document.createElement('span'), {
          className: `badge ${data.statusClass} ms-2`,
          textContent: data.statusLabel
        })
      );
      return fetch(`view_gas_slip_modal.php?id=${encodeURIComponent(gasSlipId)}&mode=body`);
    })
    .then(async response => {
      const html = await response.text();
      if (!response.ok) {
        throw new Error('Unable to load gas slip details.');
      }
      modalBody.innerHTML = html;
    })
    .catch(error => {
      const alert = document.createElement('div');
      alert.className = 'alert alert-danger mb-0';
      alert.textContent = error.message;
      modalBody.replaceChildren(alert);
    });
}

/* ================= RECOMMEND ================= */
// function initRecommendHandler() {
//   handleGasSlipAction('#recommendBtn', 'recommend_gas_slip.php',
//     'Recommended', 'Gas slip successfully recommended.');
// }
function initRecommendHandler() {
  handleGasSlipAction(
    '#recommendBtn',
    'recommend_gas_slip.php',
    'Recommended',
    'Gas slip successfully recommended.',
    {
      title: 'Recommend Gas Slip?',
      text: 'Are you sure you want to recommend this gas slip?'
    }
  );
}

/* ================= APPROVE ================= */
// function initApproveHandler() {
//   handleGasSlipAction('#approveBtn', 'approve_gas_slip.php',
//     'Approved', 'Gas slip successfully approved.');
// }
function initApproveHandler() {
  handleGasSlipAction(
    '#approveBtn',
    'approve_gas_slip.php',
    'Approved',
    'Gas slip successfully approved.',
    {
      title: 'Approve Gas Slip?',
      text: 'Are you sure you want to approve this gas slip?'
    }
  );
}

/* ================= WITHDRAW ================= */
// function initWithdrawHandler() {
//   handleGasSlipAction(
//     '#btnWithdraw',
//     'withdraw_gas_slip.php',
//     'Withdrawn',
//     'Gas slip returned to draft.'
//   );
// }
function initWithdrawHandler() {

  document.addEventListener('click', e => {

    const btn = e.target.closest('#btnWithdraw');
    if (!btn) return;

    Swal.fire({
      title: 'Withdraw Gas Slip?',
      text: 'Are you sure you want to withdraw this gas slip?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, Withdraw',
      cancelButtonText: 'Cancel'
    }).then((result) => {

      if (!result.isConfirmed) return;

      fetch('withdraw_gas_slip.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'id=' + btn.dataset.gasSlipId
      })

      .then(r => r.json())

      .then(d => {

        if (!d.success) {
          Swal.fire({
            icon: 'error',
            title: 'Action Failed',
            text: d.message || 'Unable to process request.'
          });
          return;
        }

        Swal.fire({
          icon: 'success',
          title: 'Withdrawn',
          text: 'Gas slip returned to draft.',
          timer: 1500,
          showConfirmButton: false
        }).then(() => location.reload());

      });

    });

  });

}


/* ================= SHARED ================= */
// function handleGasSlipAction(btnId, url, title, text) {

//   document.addEventListener('click', e => {

//     const btn = e.target.closest(btnId);
//     if (!btn) return;

//     fetch(url, {
//       method: 'POST',
//       headers: {
//         'Content-Type': 'application/x-www-form-urlencoded'
//       },
//       body: 'id=' + btn.dataset.gasSlipId
//     })

//     .then(r => r.json())

//     .then(d => {

//       if (!d.success) {

//         Swal.fire({
//           icon: 'error',
//           title: 'Action Failed',
//           text: d.message || 'Unable to process request.'
//         });

//         return;
//       }

//       Swal.fire({
//         icon: 'success',
//         title,
//         text,
//         timer: 1500,
//         showConfirmButton: false
//       })

//       .then(() => location.reload());

//     });

//   });

// }
function handleGasSlipAction(btnId, url, title, text, confirm = null) {

  document.addEventListener('click', e => {

    const btn = e.target.closest(btnId);
    if (!btn) return;

    const proceed = () => {

      fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'id=' + btn.dataset.gasSlipId
      })

      .then(r => r.json())

        .then(d => {

            if (!d.success) {

                if (d.logout) {

                    Swal.fire({
                        icon: 'warning',
                        title: 'Session Updated',
                        text: d.message || 'Your recommender authority is no longer active.',
                        confirmButtonText: 'OK',
                        allowOutsideClick: false,
                        allowEscapeKey: false
                    }).then(() => {
                        window.location.href = 'logout.php';
                    });

                    return;
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Action Failed',
                    text: d.message || 'Unable to process request.'
                });

                return;
            }

        Swal.fire({
          icon: 'success',
          title,
          text,
          timer: 1500,
          showConfirmButton: false
        }).then(() => location.reload());

      });

    };

    if (confirm) {

      Swal.fire({
        icon: 'question',
        title: confirm.title,
        text: confirm.text,
        showCancelButton: true,
        confirmButtonText: 'Yes',
        cancelButtonText: 'Cancel'
      }).then(result => {

        if (result.isConfirmed) {
          proceed();
        }

      });

    } else {
      proceed();
    }

  });

}
