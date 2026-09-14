/* =========================================================
   APP UI (GLOBAL)
========================================================= */

document.addEventListener('DOMContentLoaded', function () {
  initSidebarToggle();
  initLogoutSweetAlert();
  showFlashMessage();
  initPrintCheckCell(); // 👈 add this
});

/* ================= FLASH MESSAGE ================= */
function showFlashMessage() {
  const el = document.getElementById('flashMessage');
  if (!el) return;

  Swal.fire({
    icon: 'success',
    title: 'Success',
    text: el.dataset.message,
    timer: 2000,
    showConfirmButton: false
  });
}

/* ================= SIDEBAR ================= */
function initSidebarToggle() {
  const sidebar = document.querySelector('.app-sidebar');
  const content = document.querySelector('.app-content');
  const toggleBtn = document.getElementById('sidebarToggle');
  if (!sidebar || !content || !toggleBtn) return;

  if (localStorage.getItem('sidebarCollapsed') === 'true') {
    sidebar.classList.add('collapsed');
    content.classList.add('sidebar-collapsed');
  }

  toggleBtn.addEventListener('click', () => {
    sidebar.classList.toggle('collapsed');
    content.classList.toggle('sidebar-collapsed');
    localStorage.setItem(
      'sidebarCollapsed',
      sidebar.classList.contains('collapsed')
    );
  });
}

/* ================= LOGOUT ================= */
function initLogoutSweetAlert() {
  const logoutBtn = document.getElementById('logoutBtn');
  if (!logoutBtn) return;

  logoutBtn.addEventListener('click', e => {
    e.preventDefault();
    Swal.fire({
      title: 'Logout Confirmation',
      text: 'Are you sure you want to log out?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, logout',
      cancelButtonText: 'Cancel',
      reverseButtons: true
    }).then(r => {
      if (r.isConfirmed) window.location.href = 'logout.php';
    });
  });
}

/* ================= PRINT CHECKBOX CELLS ================= */
function initPrintCheckCell() {

  // BODY CELLS (single toggle)
  document.querySelectorAll('.check-cell').forEach(cell => {
    cell.addEventListener('click', function (e) {
      if (e.target.tagName.toLowerCase() === 'input') return;

      const checkbox = this.querySelector('.print-check');
      if (!checkbox) return;

      checkbox.checked = !checkbox.checked;
      syncCheckAllState();
    });
  });

  // HEADER CELL (check all)
  const checkAllCell = document.querySelector('.check-all-cell');
  const checkAllBox  = document.getElementById('checkAll');

  if (checkAllCell && checkAllBox) {
    checkAllCell.addEventListener('click', function (e) {
      if (e.target.tagName.toLowerCase() === 'input') return;

      checkAllBox.checked = !checkAllBox.checked;
      toggleAllCheckboxes(checkAllBox.checked);
    });

    // Direct click on checkbox still works
    checkAllBox.addEventListener('change', function () {
      toggleAllCheckboxes(this.checked);
    });
  }
}

/* ================= HELPERS ================= */
function toggleAllCheckboxes(state) {
  document.querySelectorAll('.print-check').forEach(cb => {
    cb.checked = state;
  });
}

function syncCheckAllState() {
  const all = document.querySelectorAll('.print-check');
  const checked = document.querySelectorAll('.print-check:checked');
  const checkAll = document.getElementById('checkAll');

  if (!checkAll) return;

  checkAll.checked = all.length > 0 && all.length === checked.length;
}

