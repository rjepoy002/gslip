/* =========================================================
   GAS SLIP INTERACTIONS
========================================================= */
document.addEventListener('DOMContentLoaded', function () {
  console.log('DOM loaded');

  try { initGasSlipRowModal(); } catch(e) { console.error('Modal error:', e); }
  try { initRecommendHandler(); } catch(e) { console.error('Recommend error:', e); }
  try { initApproveHandler(); } catch(e) { console.error('Approve error:', e); }
  try { initWithdrawHandler(); } catch(e) { console.error('Withdraw error:', e); }
});

document.addEventListener('DOMContentLoaded', function () {
  initGasSlipRowModal();
  initRecommendHandler();
  initApproveHandler();
  initWithdrawHandler();
});

/* ================= MODAL ================= */
function initGasSlipRowModal() {
  const modalEl = document.getElementById('gasSlipModal');
  const modalBody = document.getElementById('gasSlipModalBody');
  const modalTitle = document.getElementById('gasSlipModalTitle');
  if (!modalEl || !modalBody || !modalTitle) return;

  const gasSlipModal = new bootstrap.Modal(modalEl);

  document.querySelectorAll('.gas-slip-row').forEach(row => {
    const open = () => {
      const id = row.dataset.id;
      if (!id) return;

      modalTitle.innerHTML = 'Gas Slip Details';
      modalBody.innerHTML = `<div class="text-center py-5">
        <div class="spinner-border text-primary"></div>
      </div>`;
      gasSlipModal.show();

      fetch(`view_gas_slip_modal.php?id=${id}&mode=json`)
        .then(r => r.json())
        .then(d => {
          modalTitle.innerHTML =
            `Gas Slip No. <strong>${d.gas_slip_id}</strong>
            <span class="badge ${d.statusClass} ms-2">${d.statusLabel}</span>`;
          return fetch(`view_gas_slip_modal.php?id=${id}&mode=body`);
        })
        .then(r => r.text())
        .then(html => modalBody.innerHTML = html);
    };

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

/* ================= RECOMMEND ================= */
function initRecommendHandler() {
  handleGasSlipAction('#recommendBtn', 'recommend_gas_slip.php',
    'Recommended', 'Gas slip successfully recommended.');
}

/* ================= APPROVE ================= */
function initApproveHandler() {
  handleGasSlipAction('#approveBtn', 'approve_gas_slip.php',
    'Approved', 'Gas slip successfully approved.');
}

/* ================= WITHDRAW ================= */
function initWithdrawHandler() {
  handleGasSlipAction(
    '#btnWithdraw',
    'withdraw_gas_slip.php',
    'Withdrawn',
    'Gas slip returned to draft.'
  );
}


/* ================= SHARED ================= */
function handleGasSlipAction(btnId, url, title, text) {
  document.addEventListener('click', e => {
    const btn = e.target.closest(btnId);
    if (!btn) return;

    fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'id=' + btn.dataset.gasSlipId
    })
    .then(r => r.json())
    .then(d => {
      if (!d.success) {
        new bootstrap.Modal(document.getElementById('esignModal')).show();
        return;
      }
      Swal.fire({ icon: 'success', title, text, timer: 1500, showConfirmButton: false })
        .then(() => location.reload());
    });
  });
}
