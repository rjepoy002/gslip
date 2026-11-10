(() => {
if (window.gslipLogoutInitialized) return;
window.gslipLogoutInitialized = true;
// Resolve from the script so nested routes and subdirectory deployments agree.
const gslipLogoutEndpoint = new URL('../../logout.php', document.currentScript.src).href;
let gslipLogoutPending = false;
window.gslipLogout = async function () {
  if (gslipLogoutPending) return;
  gslipLogoutPending = true;
  try {
    const response = await fetch(gslipLogoutEndpoint, {credentials:'same-origin', cache:'no-store'});
    if (!response.ok) throw new Error('Logout form unavailable');
    const documentCopy = new DOMParser().parseFromString(await response.text(), 'text/html');
    const csrf = documentCopy.querySelector('input[name="csrf"]')?.value;
    if (!csrf || !/^[a-f0-9]{64}$/.test(csrf)) throw new Error('Logout token unavailable');
    const form = document.createElement('form');
    form.method = 'POST'; form.action = gslipLogoutEndpoint;
    const input = document.createElement('input');
    input.type = 'hidden'; input.name = 'csrf'; input.value = csrf;
    form.append(input); document.body.append(form); form.submit();
  } catch {
    gslipLogoutPending = false;
    if (window.Swal) await Swal.fire({icon:'error', title:'Unable to log out',
      text:'Reload the page and try again. Your session has not been logged out.'});
  }
};

// Capture also protects pages still loading a cached legacy target listener.
// Only this button is intercepted; maintenance and other forms are untouched.
let gslipLogoutConfirming = false;
document.addEventListener('click', async event => {
  const button = event.target.closest?.('#logoutBtn');
  if (!button) return;
  event.preventDefault(); event.stopImmediatePropagation();
  if (gslipLogoutConfirming || gslipLogoutPending) return;
  if (!window.Swal) { window.location.assign(gslipLogoutEndpoint); return; }
  gslipLogoutConfirming = true;
  try {
    const result = await Swal.fire({title:'Logout Confirmation',
      text:'Are you sure you want to log out?', icon:'warning', showCancelButton:true,
      confirmButtonText:'Yes, logout', cancelButtonText:'Cancel', reverseButtons:true});
    if (result.isConfirmed) await window.gslipLogout();
  } finally { gslipLogoutConfirming = false; }
}, true);

})();
