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

});
