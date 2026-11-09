(() => {
    // A page may include the shared sidebar script more than once.
    if (window.__gslipMaintenanceStarted) return;
    window.__gslipMaintenanceStarted = true;
    let busy = false;
    let current;
    let submitting = false;
    let countdownClock;
    let countdownTimer;
    let deadlineCheckAt = -Infinity;
    const monotonicNow = () => performance.now();
    function remainingMilliseconds() {
        return countdownClock ? Math.max(0, countdownClock.deadline - (monotonicNow() + countdownClock.offset)) : 0;
    }
    function syncCountdown(s, serverTimeMs, sentAt, receivedAt) {
        if (!s.enabled) {
            if (countdownTimer !== undefined) clearInterval(countdownTimer);
            countdownTimer = undefined;
            countdownClock = undefined;
            return;
        }
        if (!countdownClock || countdownClock.eventId !== s.id) {
            // Midpoint compensates for transport latency. Use a monotonic clock so
            // a wrong device clock or a later clock adjustment cannot move the timer.
            countdownClock = {eventId:s.id, deadline:s.effective_at * 1000,
                offset:serverTimeMs - (sentAt + receivedAt) / 2};
            deadlineCheckAt = -Infinity;
        }
        // Same-event polls never re-anchor the display or restart its timer.
        if (countdownTimer === undefined) countdownTimer = setInterval(tick, 200);
    }
    function tick() {
        countdown();
        if (countdownClock && current?.state.phase === 'warning' && remainingMilliseconds() === 0 &&
            monotonicNow() - deadlineCheckAt >= 1000) {
            deadlineCheckAt = monotonicNow();
            refresh(); // Verify current state before any automatic logout.
        }
    }
    const endpoint = new URL('maintenance.php', document.baseURI);
    let noticeEventId;
    let logoutRequest;
    let swalLoading;
    function ownNotice() {
        return window.Swal?.getPopup()?.classList.contains('maintenance-notice-modal');
    }
    function closeNotice() {
        noticeEventId = undefined;
        if (ownNotice()) window.Swal.close();
    }
    async function ensureSwal() {
        if (window.Swal) return window.Swal;
        if (!swalLoading) swalLoading = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = new URL('assets/js/sweetalert2.all.min.js', endpoint);
            script.onload = () => resolve(window.Swal);
            script.onerror = () => { swalLoading = undefined; reject(new Error('Unable to load maintenance notice.')); };
            document.head.append(script);
        });
        return swalLoading;
    }
    async function requestLogout(eventId) {
        if (!logoutRequest) logoutRequest = post('logout', '', eventId).finally(() => { logoutRequest = undefined; });
        return logoutRequest;
    }
    async function showNotice(s) {
        const swal = await ensureSwal();
        // Loading the library must not resurrect an event canceled by a later poll.
        if (!current?.state.enabled || current.state.id !== s.id || current.admin || !current.authenticated) return;
        if (noticeEventId !== s.id || !ownNotice()) {
            closeNotice();
            noticeEventId = s.id;
            swal.fire({
                title:'System Maintenance Notice', icon:'warning', position:'center',
                iconHtml:'<svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.5 4.5a5 5 0 0 0-6.2 6.2L3 16a2.1 2.1 0 0 0 3 3l5.3-5.3a5 5 0 0 0 6.2-6.2l-3 3-3-3 3-3Z"/></svg>',
                backdrop:'rgba(0, 0, 0, 0.65)',
                customClass:{popup:'maintenance-notice-modal',container:'maintenance-notice-container',icon:'maintenance-notice-icon'},
                html:'<p>System maintenance will begin shortly. You will be automatically logged out when the countdown ends.</p><div class="maintenance-notice-countdown" role="timer" aria-label="Seconds until maintenance"><strong id="maintenance-notice-seconds">60</strong><span>seconds remaining</span></div><p id="maintenance-notice-reason" class="maintenance-notice-reason"></p>',
                confirmButtonText:'Acknowledge and Log Out', confirmButtonColor:'#009879',
                allowOutsideClick:false, allowEscapeKey:false, showCloseButton:false,
                showCancelButton:false, showLoaderOnConfirm:true,
                preConfirm:async () => {
                    try {
                        const result = await requestLogout(s.id);
                        if (!result?.logout) await refresh();
                    } catch (error) { swal.showValidationMessage(error.message); }
                    return false;
                }
            });
        }
        const reason = document.getElementById('maintenance-notice-reason');
        if (reason) { reason.textContent = s.reason || ''; reason.hidden = !s.reason; }
        countdown();
    }
    function countdown() {
        const output = document.getElementById('maintenance-countdown');
        if (!countdownClock) return;
        // Presentation only; logout always waits for the existing authoritative poll.
        const seconds = Math.ceil(remainingMilliseconds() / 1000);
        if (output && output.dataset.seconds !== String(seconds)) {
            output.dataset.seconds = String(seconds);
            output.replaceChildren(document.createTextNode(String(seconds)), Object.assign(document.createElement('span'), {textContent:'s'}));
        }
        const modalSeconds = document.getElementById('maintenance-notice-seconds');
        if (modalSeconds && modalSeconds.textContent !== String(seconds)) modalSeconds.textContent = String(seconds);
        document.getElementById('maintenance-progress')?.setAttribute('aria-valuenow', String(seconds));
        const bar = document.getElementById('maintenance-progress-bar');
        if (bar) bar.style.width = `${Math.min(100, seconds / 60 * 100)}%`;
    }
    function render(s) {
        const badge = document.getElementById('maintenance-badge');
        if (badge) {
            badge.textContent = s.enabled ? 'Active' : 'Operational';
            badge.className = `badge rounded-pill maintenance-badge ${s.enabled ? 'is-active' : 'is-inactive'}`;
        }
        const summary = document.getElementById('maintenance-summary');
        if (summary) summary.classList.toggle('is-completed', !s.enabled);
        const heading = document.getElementById('maintenance-completion-heading');
        if (heading) heading.hidden = s.enabled;
        const icon = document.getElementById('maintenance-summary-icon');
        if (icon) icon.className = `fa-solid ${s.enabled ? 'fa-shield-halved' : 'fa-circle-check'}`;
        const reason = document.getElementById('maintenance-reason');
        if (reason) reason.textContent = s.reason || 'No reason provided.';
        const activated = document.getElementById('maintenance-activated');
        // Existing events persist the deadline, exactly 60 seconds after activation.
        if (activated) activated.textContent = s.effective_at > 0 ? new Date((s.effective_at - 60) * 1000).toLocaleString('en-PH', {timeZone:'Asia/Manila',dateStyle:'medium',timeStyle:'short'}) : 'Not available';
        const warning = document.getElementById('maintenance-warning');
        if (warning) warning.hidden = s.phase !== 'warning';
        countdown();
        const controls = document.getElementById('maintenance-controls');
        controls?.querySelectorAll('button[type="submit"]').forEach(button => {
            button.disabled = submitting || !current.admin || (button.value === 'enable' ? s.enabled : !s.enabled);
        });
        const acknowledge = document.getElementById('maintenance-acknowledge');
        if (acknowledge) acknowledge.hidden = !s.enabled || !current.authenticated || current.admin;
    }
    async function post(action, reason = '', eventId = current?.state.id) {
        if (!current) return;
        const response = await fetch(endpoint, {method:'POST', cache:'no-store', body:new URLSearchParams({
            action, reason, csrf:current.csrf || '', event_id:eventId
        })});
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Unable to update maintenance.');
        if (result.logout) location.assign(new URL('maintenance.php', endpoint));
        return result;
    }
    async function refresh() {
        if (busy) return;
        busy = true;
        try {
            const sentAt = monotonicNow();
            const response = await fetch(new URL('?state=1', endpoint), {cache:'no-store'});
            if (!response.ok) throw new Error('Unable to check maintenance');
            current = await response.json();
            const receivedAt = monotonicNow();
            const s = current.state;
            const serverTimeMs = Number.isFinite(current.server_time_ms) ? current.server_time_ms : s.server_time * 1000;
            syncCountdown(s, serverTimeMs, sentAt, receivedAt);
            const seconds = Math.max(0, s.effective_at - s.server_time);
            const text = s.phase === 'ended' ? 'GSLIP is now available. You may log in to continue using the system.' :
                `System maintenance ${s.phase === 'warning' ? `starts in ${seconds} seconds` : 'is in progress'}.${s.reason ? ' ' + s.reason : ''}`;
            const status = document.getElementById('maintenance-status');
            if (status) status.textContent = text;
            render(s);
            if (s.phase === 'ended' || current.admin || !current.authenticated) {
                closeNotice();
            } else {
                // Modal loading failures never bypass server-confirmed deadline logout.
                try { await showNotice(s); } catch (error) { if (status) status.textContent = error.message; }
                if (s.phase === 'active') await requestLogout(s.id);
            }
            if ('serviceWorker' in navigator) {
                const registration = await navigator.serviceWorker.getRegistration();
                if (registration) {
                    const notifications = await registration.getNotifications();
                    notifications.filter(n => n.data?.type === 'maintenance' &&
                        (!s.enabled || n.data.event_id !== s.id)).forEach(n => n.close());
                }
            }
        } catch (e) {
            // Never use a cached deadline to log out when authoritative state is unavailable.
            const status = document.getElementById('maintenance-status');
            if (status) status.textContent = 'Unable to check maintenance. Retrying…';
        } finally { busy = false; }
    }
    document.getElementById('maintenance-controls')?.addEventListener('submit', async e => {
        e.preventDefault();
        if (!current?.admin || submitting || !e.submitter) return;
        const action = e.submitter.value;
        if (!['enable','disable'].includes(action)) return;
        const reason = new FormData(e.target).get('reason');
        const feedback = document.getElementById('maintenance-action-feedback');
        submitting = true;
        render(current.state);
        try {
            const confirmation = await Swal.fire({
                title: action === 'enable' ? 'Enable maintenance?' : 'Disable maintenance?',
                text: action === 'enable' ? 'Users will receive a 60-second warning before access is restricted. Administrators will retain access.' : 'The current warning will be canceled and normal user access will be restored.',
                icon: 'warning', showCancelButton:true, reverseButtons:true,
                confirmButtonColor:'#009879', cancelButtonText:'Cancel',
                confirmButtonText:action === 'enable' ? 'Enable maintenance' : 'Disable maintenance'
            });
            if (!confirmation.isConfirmed) return;
            await post(action, reason);
            if (feedback) feedback.textContent = action === 'enable' ? 'Maintenance enabled. The 60-second warning is now running.' : 'Maintenance disabled. Normal access is restored.';
            await refresh();
        } catch (error) {
            if (feedback) feedback.textContent = error.message;
        } finally { submitting = false; if (current) render(current.state); }
    });
    document.getElementById('maintenance-acknowledge')?.addEventListener('click', () => {
        requestLogout(current?.state.id).catch(error => { document.getElementById('maintenance-status').textContent = error.message; });
    });
    refresh(); setInterval(refresh, 3000);
    const resume = () => { tick(); refresh(); };
    window.addEventListener('focus', resume);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') resume();
    });
})();
