document.addEventListener('DOMContentLoaded', () => {
  const group = document.querySelector('.remember-help');
  const tooltip = document.getElementById('rememberHelpText');
  const button = group?.querySelector('.remember-help-button');
  if (!group || !tooltip || !button) return;
  let pinned = false, hideTimer;
  const position = () => {
    const anchor = button.getBoundingClientRect();
    const width = Math.min(200, Math.max(110, innerWidth - anchor.right - 14));
    tooltip.style.width = `${Math.min(width, innerWidth - 16)}px`;
    const bounds = tooltip.getBoundingClientRect();
    const left = Math.max(8, Math.min(anchor.right + 6, innerWidth - bounds.width - 8));
    let top = Math.max(8, Math.min(anchor.top + anchor.height / 2 - bounds.height / 2, innerHeight - bounds.height - 8));
    // On very narrow screens use the space above the form instead of covering
    // the password field or Login button. Fixed placement causes no layout shift.
    const controls = [...button.form.querySelectorAll('input:not([type="hidden"]), button[type="submit"]')]
      .filter(control => control.id !== 'rememberMe').map(control => control.getBoundingClientRect());
    if (controls.some(rect => left < rect.right && left + bounds.width > rect.left && top < rect.bottom && top + bounds.height > rect.top)) {
      top = Math.max(8, controls[0].top - bounds.height - 8);
    }
    tooltip.style.left = `${left}px`; tooltip.style.top = `${top}px`;
  };
  const hide = () => { clearTimeout(hideTimer); pinned = false; tooltip.hidden = true; };
  const show = () => { clearTimeout(hideTimer); tooltip.hidden = false; position(); };
  const leave = () => {
    clearTimeout(hideTimer);
    if (!pinned && !group.contains(document.activeElement)) hideTimer = setTimeout(hide, 120);
  };
  group.addEventListener('pointerenter', event => { if (event.pointerType !== 'touch') show(); });
  group.addEventListener('pointerleave', leave);
  group.addEventListener('focusin', show);
  group.addEventListener('focusout', event => { if (!group.contains(event.relatedTarget)) hide(); });
  button.addEventListener('click', () => { if (pinned) hide(); else { pinned = true; show(); } });
  tooltip.addEventListener('pointerenter', () => clearTimeout(hideTimer));
  tooltip.addEventListener('pointerleave', leave);
  document.addEventListener('keydown', event => { if (event.key === 'Escape') hide(); });
  document.addEventListener('pointerdown', event => {
    if (!group.contains(event.target) && !tooltip.contains(event.target)) hide();
  });
  window.addEventListener('resize', hide);
  window.addEventListener('scroll', hide, {passive:true});
});
