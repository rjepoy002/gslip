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

/* Global Create Gas Slip propagation. Field/row identity is kept outside the
 * DOM so cloned rows cannot inherit another row's edit or confirmation state.
 */
(() => {
  const selector = '#gasSlipGrid tbody input[name="validity_until[]"], #gasSlipGrid tbody input[name="purpose[]"]';
  const fields = new WeakMap();
  const rowIds = new WeakMap();
  let pending = false;
  const stateFor = input => {
    if (!fields.has(input)) fields.set(input, {committed: input.value, edited: false, armed: false, version: 0});
    return fields.get(input);
  };
  const rowId = row => {
    if (!rowIds.has(row)) rowIds.set(row, Symbol('gas-slip-row'));
    return rowIds.get(row);
  };
  const rowsIn = body => Array.from(body.children).filter(row => row.tagName === 'TR');
  function initialize() {
    const grid = document.getElementById('gasSlipGrid');
    if (!grid) return;
    const seed = () => grid.querySelectorAll(selector).forEach(input => {
      stateFor(input); rowId(input.closest('tr'));
    });
    seed();
    // Add Row, template replacement and any supported insert path share this.
    new MutationObserver(seed).observe(grid, {childList: true, subtree: true});
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, {once: true});
  else initialize();

  function prepare(event) {
    const input = event.target;
    if (!input.matches(selector)) return;
    const state = stateFor(input);
    // A native date picker may return focus after its input event, before
    // change. Never replace the old committed value during that edit.
    if (!state.armed && !state.edited) state.committed = input.value;
    state.armed = true;
  }
  document.addEventListener('pointerdown', prepare, true);
  document.addEventListener('focusin', prepare);
  document.addEventListener('beforeinput', prepare);
  document.addEventListener('input', event => {
    const input = event.target;
    if (!input.matches(selector)) return;
    const state = stateFor(input);
    ++state.version;
    if (event.isTrusted || state.armed) state.edited = true;
    else if (!state.edited) state.committed = input.value;
  });

  async function commit(event) {
    const input = event.target;
    if (!input.matches(selector)) return;
    if (event.type === 'focusout' && input.name !== 'purpose[]') {
      const state = stateFor(input);
      if (!state.edited) state.armed = false;
      return;
    }
    const state = stateFor(input);
    const oldValue = state.committed;
    const newValue = input.value;
    const edited = state.edited || event.isTrusted;
    state.committed = newValue;
    state.edited = false;
    state.armed = false;
    if (!edited || oldValue === newValue || !newValue.trim() || !input.checkValidity()) return;
    ++state.version;
    const version = state.version;
    const row = input.closest('tr');
    const body = row.parentElement;
    const rows = rowsIn(body);
    const index = rows.indexOf(row);
    if (index < 0 || index === rows.length - 1 || pending || typeof Swal === 'undefined') return;
    const sourceId = rowId(row);
    const targets = rows.slice(index + 1).map(next => {
      const field = Array.from(next.querySelectorAll('input')).find(candidate => candidate.name === input.name);
      return field ? {row: next, id: rowId(next), field, value: field.value, version: stateFor(field).version} : null;
    }).filter(Boolean);
    pending = true;
    try {
      const result = await Swal.fire({
        icon: 'question',
        title: 'Apply to All Below?',
        text: `Apply this ${input.name === 'purpose[]' ? 'Purpose' : 'Validity Until'} to all rows below this row?`,
        showCancelButton: true,
        cancelButtonText: 'No, Keep Individual',
        confirmButtonText: 'Apply to All Below',
        focusCancel: true
      });
      const currentRows = rowsIn(body);
      if (!result.isConfirmed || !row.isConnected || row.parentElement !== body || rowId(row) !== sourceId ||
          state.version !== version || input.value !== newValue || currentRows.length !== rows.length ||
          rows.some((original, position) => currentRows[position] !== original) ||
          targets.some(target => !target.field.isConnected || rowId(target.row) !== target.id ||
            target.field.value !== target.value || stateFor(target.field).version !== target.version)) return;
      for (const target of targets) {
        target.field.value = newValue;
        const targetState = stateFor(target.field);
        targetState.committed = newValue;
        targetState.edited = false;
        targetState.armed = false;
        // Notify existing dirty-state listeners without recursive change prompts.
        target.field.dispatchEvent(new Event('input', {bubbles: true}));
      }
    } finally {
      pending = false;
    }
  }
  document.addEventListener('change', commit);
  document.addEventListener('focusout', commit);
})();
