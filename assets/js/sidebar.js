/* =========================================================
   SIDEBAR MODULE
   - Collapse state
   - Logout confirmation
========================================================= */

document.addEventListener('DOMContentLoaded', function () {
  /* ===== Sidebar Toggle ===== */
  const sidebar   = document.querySelector('.app-sidebar');
  const content   = document.querySelector('.app-content');
  const toggleBtn = document.getElementById('sidebarToggle');

  if (sidebar && content && toggleBtn) {
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

  /* ===== Logout SweetAlert ===== */
  const logoutBtn = document.getElementById('logoutBtn');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', function (e) {
      e.preventDefault();
      Swal.fire({
        title: 'Logout Confirmation',
        text: 'Are you sure you want to log out?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, logout',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        reverseButtons: true
      }).then(result => {
        if (result.isConfirmed) {
          window.location.href = 'logout.php';
        }
      });
    });
  }
});
