(() => {
  const initialize = () => {
    const sidebar = document.querySelector('.app-sidebar');
    const nav = sidebar?.querySelector('.sidebar-nav');
    const desktopPointer = window.matchMedia(
      '(min-width: 992px) and (hover: hover) and (pointer: fine)'
    );

    if (!sidebar || !nav) return;

    const tooltip = document.createElement('div');
    tooltip.className = 'sidebar-tooltip';
    tooltip.setAttribute('role', 'tooltip');
    document.body.appendChild(tooltip);

    const labelFor = (link) => {
      const label = link.querySelector('span');
      if (!label) return '';

      const labelCopy = label.cloneNode(true);
      labelCopy.querySelectorAll('.badge').forEach((badge) => badge.remove());
      return labelCopy.textContent.replace(/\s+/g, ' ').trim();
    };

    nav.querySelectorAll('a.nav-link').forEach((link) => {
      const label = labelFor(link);
      if (!label) return;

      link.dataset.sidebarTitle = label;
      if (!link.hasAttribute('aria-label')) link.setAttribute('aria-label', label);
    });

    const hide = () => tooltip.classList.remove('is-visible');
    const show = (link) => {
      if (
        !desktopPointer.matches ||
        !sidebar.classList.contains('collapsed') ||
        !link.dataset.sidebarTitle
      ) {
        hide();
        return;
      }

      const bounds = link.getBoundingClientRect();
      tooltip.textContent = link.dataset.sidebarTitle;
      tooltip.style.left = `${Math.round(bounds.right + 10)}px`;
      tooltip.style.top = `${Math.round(bounds.top + (bounds.height / 2))}px`;
      tooltip.classList.add('is-visible');
    };

    nav.addEventListener('pointerover', (event) => {
      const link = event.target.closest('a.nav-link');
      if (link && nav.contains(link)) show(link);
    });
    nav.addEventListener('pointerout', (event) => {
      const link = event.target.closest('a.nav-link');
      if (link && !link.contains(event.relatedTarget)) hide();
    });
    nav.addEventListener('focusin', (event) => {
      const link = event.target.closest('a.nav-link');
      if (link && nav.contains(link)) show(link);
    });
    nav.addEventListener('focusout', hide);
    desktopPointer.addEventListener('change', hide);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize, { once: true });
  } else {
    initialize();
  }
})();
