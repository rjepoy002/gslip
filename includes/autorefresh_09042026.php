<script>
let idleTimer;
const IDLE_TIME = <?= $autoRefreshTime ?? 60000 ?>;

function resetIdleTimer() {
    clearTimeout(idleTimer);

    idleTimer = setTimeout(() => {

        if (document.hidden) return;

        const openModal = document.querySelector('.modal.show');

        if (!openModal) {
            location.reload();
        } else {
            resetIdleTimer();
        }

    }, IDLE_TIME);
}

['mousemove','mousedown','keydown','scroll','touchstart']
.forEach(event => {
    document.addEventListener(event, resetIdleTimer, true);
});

document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {

        const openModal = document.querySelector('.modal.show');

        if (!openModal) {
            location.reload();
        }
    }
});

resetIdleTimer();
</script>