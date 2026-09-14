<script>
let idleTimer;
const IDLE_TIME = <?= $autoRefreshTime ?? 60000 ?>;

/* =========================================================
   SYNC USER PERMISSIONS
   ========================================================= */
async function syncPermissions() {
    try {
        const response = await fetch(
            'includes/sync_permissions.php',
            {
                method: 'GET',
                cache: 'no-store',
                credentials: 'same-origin'
            }
        );

        if (!response.ok) {
            return;
        }

        const data = await response.json();

        if (!data.success || !data.logged_in) {
            return;
        }

        /*
         * sync_permissions.php updates the session.
         * The dashboard reload below will then use the
         * updated session permissions.
         */
    } catch (error) {
        console.error('Permission sync failed:', error);
    }
}

/* =========================================================
   DASHBOARD REFRESH
   ========================================================= */
async function refreshDashboard() {

    const openModal = document.querySelector('.modal.show');

    if (openModal) {
        resetIdleTimer();
        return;
    }

    /*
     * First synchronize permissions from the database.
     * Then perform the normal dashboard refresh.
     */
    await syncPermissions();

    location.reload();
}

/* =========================================================
   IDLE TIMER
   ========================================================= */
function resetIdleTimer() {
    clearTimeout(idleTimer);

    idleTimer = setTimeout(() => {

        if (document.hidden) return;

        refreshDashboard();

    }, IDLE_TIME);
}

/* =========================================================
   USER ACTIVITY
   ========================================================= */
[
    'mousemove',
    'mousedown',
    'keydown',
    'scroll',
    'touchstart'
].forEach(event => {
    document.addEventListener(event, resetIdleTimer, true);
});

/* =========================================================
   TAB BECOMES VISIBLE
   ========================================================= */
document.addEventListener('visibilitychange', () => {

    if (!document.hidden) {

        const openModal = document.querySelector('.modal.show');

        if (!openModal) {
            refreshDashboard();
        }
    }
});

resetIdleTimer();
</script>