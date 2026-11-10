document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('input[type="password"]').forEach((input, index) => {
    if (!input.id) input.id = `auth-password-${index}`;
    let container = input.parentElement;
    if (!container.classList.contains('form-floating')) {
      container = document.createElement('div');
      input.before(container); container.append(input);
    }
    container.classList.add('password-field');
    const button = document.createElement('button');
    button.type = 'button'; button.className = 'password-toggle';
    button.setAttribute('aria-controls', input.id);
    const hidden = () => {
      input.type = 'password';
      button.setAttribute('aria-label', 'Show password');
      button.setAttribute('aria-pressed', 'false');
    };
    button.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="m3 3 18 18"/></svg>';
    hidden();
    button.addEventListener('mousedown', event => event.preventDefault());
    button.addEventListener('click', () => {
      const start = input.selectionStart, end = input.selectionEnd;
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      button.setAttribute('aria-pressed', String(show));
      input.focus({preventScroll:true});
      if (start !== null) input.setSelectionRange(start, end);
    });
    container.append(button);
    input.form?.addEventListener('reset', hidden);
    input.closest('.modal')?.addEventListener('hidden.bs.modal', hidden);
  });
});
