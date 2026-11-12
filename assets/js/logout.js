// Called only after the existing logout confirmation. Fetching the same-origin
// form supplies its session-bound CSRF token without adding a second prompt.
window.gslipLogout = async function () {
  try {
    const response = await fetch('logout.php', {credentials:'same-origin', cache:'no-store'});
    if (!response.ok) throw new Error('Logout form unavailable');
    const documentCopy = new DOMParser().parseFromString(await response.text(), 'text/html');
    const csrf = documentCopy.querySelector('input[name="csrf"]')?.value;
    if (!csrf || !/^[a-f0-9]{64}$/.test(csrf)) throw new Error('Logout token unavailable');
    const form = document.createElement('form');
    form.method = 'POST'; form.action = 'logout.php';
    const input = document.createElement('input');
    input.type = 'hidden'; input.name = 'csrf'; input.value = csrf;
    form.append(input); document.body.append(form); form.submit();
  } catch {
    window.location.assign('logout.php');
  }
};
