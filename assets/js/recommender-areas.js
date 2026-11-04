/* Area eligibility editor. Authorization and validation remain server-side. */
(() => {
    const modal = document.getElementById('recommenderAreasModal');
    if (!modal) return;
    const form = document.getElementById('recommenderAreasForm');
    const search = document.getElementById('recommenderAreasSearch');
    const options = Array.from(form.querySelectorAll('.area-checkbox-option'));
    const checkboxes = options.map(option => option.querySelector('input'));
    const count = document.getElementById('recommenderAreasCount');
    const error = document.getElementById('recommenderAreasError');
    const save = document.getElementById('recommenderAreasSave');
    let trigger = null;
    let busy = false;

    function updateCount() {
        count.textContent = `${checkboxes.filter(input => input.checked).length} of ${checkboxes.length} selected`;
    }
    function filterAreas() {
        const term = search.value.trim().toLocaleLowerCase();
        let visible = 0;
        options.forEach(option => {
            const matches = option.textContent.toLocaleLowerCase().includes(term);
            // Bootstrap's d-flex uses !important, so the hidden attribute alone is insufficient.
            option.classList.toggle('d-none', !matches);
            option.classList.toggle('d-flex', matches);
            if (matches) visible++;
        });
        document.getElementById('recommenderAreasNoResults').hidden = visible > 0;
    }
    function setBusy(value) {
        busy = value;
        form.setAttribute('aria-busy', String(value));
        form.querySelectorAll('button, input').forEach(control => { control.disabled = value; });
        save.innerHTML = value
            ? '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Saving…'
            : 'Save Changes';
    }
    modal.addEventListener('show.bs.modal', event => {
        trigger = event.relatedTarget;
        if (!trigger || !trigger.matches('.edit-recommender-areas')) { event.preventDefault(); return; }
        const selected = new Set(JSON.parse(trigger.dataset.areaIds).map(String));
        checkboxes.forEach(input => { input.checked = selected.has(input.value); });
        document.getElementById('recommenderAreasUser').value = trigger.dataset.userId;
        document.getElementById('recommenderAreasName').textContent = trigger.dataset.userName;
        search.value = '';
        error.hidden = true;
        error.textContent = '';
        setBusy(false);
        filterAreas();
        updateCount();
        document.getElementById('recommenderAreasList').scrollTop = 0;
    });
    modal.addEventListener('shown.bs.modal', () => search.focus());
    modal.addEventListener('hide.bs.modal', event => { if (busy) event.preventDefault(); });
    search.addEventListener('input', filterAreas);
    checkboxes.forEach(input => input.addEventListener('change', updateCount));
    document.getElementById('recommenderAreasSelectAll').addEventListener('click', () => {
        checkboxes.forEach(input => { input.checked = true; });
        updateCount();
    });
    document.getElementById('recommenderAreasClearAll').addEventListener('click', () => {
        checkboxes.forEach(input => { input.checked = false; });
        updateCount();
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (busy || !trigger) return;
        const body = new FormData(form); // Capture before disabling controls.
        setBusy(true);
        error.hidden = true;
        try {
            if (!checkboxes.some(input => input.checked)) {
                const primaryArea = trigger.dataset.primaryAreaName;
                if (!primaryArea) throw new Error('The recommender has no primary area. Select an authorized area or update their primary area before saving.');
                const confirmation = await Swal.fire({
                    target: modal,
                    icon: 'info',
                    title: 'Reset to Default Area?',
                    text: `No areas are selected. This will reset the recommender's assigned areas to their primary area: ${primaryArea}. Do you want to continue?`,
                    showCancelButton: true,
                    confirmButtonText: 'Reset to Default',
                    cancelButtonText: 'Keep Editing'
                });
                if (!confirmation.isConfirmed) return;
            }
            const response = await fetch('save_recommender_areas.php', {method: 'POST', body});
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'Unable to save assigned areas.');

            const savedIds = new Set(result.areas.map(area => String(area.id)));
            checkboxes.forEach(input => { input.checked = savedIds.has(input.value); });
            updateCount();
            const badges = trigger.closest('tr').querySelector('.recommender-area-badges');
            badges.replaceChildren();
            result.areas.forEach(area => {
                const badge = document.createElement('span');
                badge.className = 'badge rounded-pill bg-primary text-wrap';
                badge.textContent = area.area_name;
                badges.append(badge);
            });
            trigger.dataset.areaIds = JSON.stringify(result.areas.map(area => Number(area.id)));
            setBusy(false);
            bootstrap.Modal.getOrCreateInstance(modal).hide();
            Swal.fire({icon: 'success', title: 'Assigned areas updated.', text: result.message, timer: 2500, showConfirmButton: false});
        } catch (failure) {
            error.textContent = failure.message || 'Unable to save assigned areas. Please try again.';
            error.hidden = false;
        } finally {
            setBusy(false);
        }
    });
})();
