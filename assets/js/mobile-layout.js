document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.querySelector('.app-sidebar');
  const sidebarToggle = document.getElementById('sidebarToggle');
  if (!sidebar) return;

  const mobileMedia = window.matchMedia('(max-width: 991.98px)');
  const menuButton = document.createElement('button');
  menuButton.type = 'button';
  menuButton.className = 'mobile-menu-toggle';
  menuButton.setAttribute('aria-label', 'Open navigation menu');
  menuButton.setAttribute('aria-expanded', 'false');
  menuButton.innerHTML = '<i class="fa-solid fa-bars" aria-hidden="true"></i>';

  const backdrop = document.createElement('div');
  backdrop.className = 'mobile-sidebar-backdrop';
  backdrop.setAttribute('aria-hidden', 'true');
  document.body.append(menuButton, backdrop);

  const setOpen = (open) => {
    if (!mobileMedia.matches) return;
    sidebar.classList.toggle('mobile-open', open);
    backdrop.classList.toggle('is-visible', open);
    document.body.classList.toggle('mobile-nav-open', open);
    menuButton.setAttribute('aria-expanded', String(open));
    menuButton.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
  };

  menuButton.addEventListener('click', () => setOpen(!sidebar.classList.contains('mobile-open')));
  backdrop.addEventListener('click', () => setOpen(false));

  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', (event) => {
      if (!mobileMedia.matches) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      setOpen(!sidebar.classList.contains('mobile-open'));
    }, true);
  }

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') setOpen(false);
  });

  const handleViewportChange = (event) => {
    if (!event.matches) {
      sidebar.classList.remove('mobile-open');
      backdrop.classList.remove('is-visible');
      document.body.classList.remove('mobile-nav-open');
    }
  };

  if (mobileMedia.addEventListener) {
    mobileMedia.addEventListener('change', handleViewportChange);
  } else {
    mobileMedia.addListener(handleViewportChange);
  }
});
