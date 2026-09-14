const notificationSound = new Audio('assets/sounds/eGSlip_Premium_Notification.mp3');
notificationSound.preload = 'auto';
notificationSound.volume = 0.7;

// Unlock audio after the user's first interaction
let audioUnlocked = false;

function unlockAudio() {

    if (audioUnlocked) return;

    notificationSound.play()
        .then(() => {
            notificationSound.pause();
            notificationSound.currentTime = 0;
            audioUnlocked = true;
            console.log("Audio unlocked");
        })
        .catch(err => console.log(err));

}

document.addEventListener('click', unlockAudio, { once: true });
document.addEventListener('touchstart', unlockAudio, { once: true });

// flash title
const originalTitle = document.title;
let titleInterval = null;

function startTitleFlash(message) {

    if (titleInterval) return;

    titleInterval = setInterval(() => {

        document.title =
            document.title === originalTitle
                ? "🔔 " + message
                : originalTitle;

    }, 1000);

}

function stopTitleFlash() {

    if (titleInterval) {
        clearInterval(titleInterval);
        titleInterval = null;
    }

    document.title = originalTitle;

}

document.addEventListener('visibilitychange', () => {

    if (!document.hidden) {
        stopTitleFlash();
    }

});

function checkNotifications() {

    fetch('ajax/get_notifications.php')
    .then(response => response.json())
    .then(data => {

        data.forEach(notification => {

            notificationSound.currentTime = 0;
            notificationSound.play().catch(() => {});

            if (document.hidden) {
                startTitleFlash(notification.title);
                return;
            }

            // Determine the SweetAlert icon
            let swalIcon = 'info';

            switch (notification.type) {

                case 'new_pending':
                    swalIcon = 'info';
                    break;

                case 'recommended':
                    swalIcon = 'warning';
                    break;

                case 'approved':
                    swalIcon = 'success';
                    break;

                case 'rejected':
                    swalIcon = 'error';
                    break;

                default:
                    swalIcon = 'info';
            }


            Swal.fire({

                toast: true,
                position: 'bottom-end',

                icon: swalIcon,

                title: notification.title,

                text: notification.message,

                showConfirmButton: true,
                confirmButtonText: 'View',

                showCloseButton: true

                // timer: 8000,
                // timerProgressBar: true

            }).then((result) => {

                fetch('ajax/mark_notification_displayed.php', {

                    method: 'POST',

                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },

                    body: 'id=' + notification.id

                });

                if (result.isConfirmed) {

                    stopTitleFlash();

                    switch (notification.type) {

                        case 'new_pending':
                        case 'recommended':
                        case 'rejected':
                            window.location = 'pending_gas_slips.php';
                            break;
                    
                        case 'approved':
                            window.location = 'approved_slips.php';
                            break;
                    
                        default:
                            window.location = 'dashboard.php';
                    }

                }

            });

        });

    });

}

checkNotifications();

setInterval(checkNotifications, 5000);