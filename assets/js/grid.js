/* =========================================================
   GRID MODULE
   - Default dates
   - Excel-like date picker
   - Enter-key navigation
========================================================= */

document.addEventListener('DOMContentLoaded', function () {
  /* ===== Default Validity Until = Tomorrow ===== */
  const dateInputs = document.querySelectorAll(
    'input[type="date"][name="validity_until[]"]'
  );

  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);

  const yyyy = tomorrow.getFullYear();
  const mm   = String(tomorrow.getMonth() + 1).padStart(2, '0');
  const dd   = String(tomorrow.getDate()).padStart(2, '0');
  const formatted = `${yyyy}-${mm}-${dd}`;

  dateInputs.forEach(input => {
    if (!input.value) input.value = formatted;
  });
});

/* =========================================================
   EXCEL-LIKE DATE PICKER
========================================================= */
document.addEventListener('mousedown', function (e) {
  const input = e.target.closest('input[type="date"]');
  if (!input) return;

  e.preventDefault();
  input.focus({ preventScroll: true });

  if (typeof input.showPicker === 'function') {
    input.showPicker();
  }
});

/* =========================================================
   ENTER KEY GRID NAVIGATION
========================================================= */
document.addEventListener('keydown', function (e) {
  const active = document.activeElement;

  /* 🚫 DO NOT INTERCEPT KEYS INSIDE MODALS */
  if (active && active.closest('.modal')) return;
  if (!active || !active.closest('td')) return;

  if (e.key === 'Enter') {
    e.preventDefault();
    const td = active.closest('td');
    const tr = td.parentElement;
    const cells = Array.from(
      tr.querySelectorAll('td input, td select, td textarea')
    );
    const index = cells.indexOf(active);
    if (cells[index + 1]) cells[index + 1].focus();
  }
});
