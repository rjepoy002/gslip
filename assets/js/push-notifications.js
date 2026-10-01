(() => {
    const button = document.getElementById('pushNotificationButton');
    const status = document.getElementById('pushNotificationStatus');
    const script = document.currentScript;
    const promptDismissedKey = 'egslip_notification_prompt_dismissed';

    if (!script) return;

    const scriptUrl = new URL(script.src, window.location.href);
    const assetPathIndex = scriptUrl.pathname.lastIndexOf('/assets/js/');
    const basePath = assetPathIndex >= 0 ? scriptUrl.pathname.slice(0, assetPathIndex) : '';
    const applicationUrl = (path) => `${basePath}${path}`;
    let registration = null;
    let registrationPromise = null;

    const setStatus = (text, enabled = false) => {
        if (!button || !status) return;
        status.textContent = text;
        button.textContent = enabled ? 'Disable Notifications' : 'Enable Notifications';
        button.disabled = false;
    };

    const supported = () =>
        'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

    const requestJson = async (url, options = {}) => {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'Request failed.');
        return data;
    };

    const base64UrlToUint8Array = (value) => {
        const padding = '='.repeat((4 - (value.length % 4)) % 4);
        const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw = window.atob(base64);
        return Uint8Array.from(raw, (character) => character.charCodeAt(0));
    };

    const loadRegistration = async () => {
        if (!registration) {
            registrationPromise ??= navigator.serviceWorker.register(
                applicationUrl('/sw.js'),
                { scope: `${basePath || ''}/` }
            );
            registration = await registrationPromise;
        }
        return registration;
    };

    // Keeps a shared-browser subscription associated with the current account.
    const syncSubscription = async (subscription) => {
        if (!subscription) return;
        await requestJson(applicationUrl('/api/save_push_subscription.php'), {
            method: 'POST',
            body: JSON.stringify(subscription.toJSON()),
        });
    };

    const showEnabledMessage = () => {
        if (!window.Swal) return;
        Swal.fire({
            icon: 'success',
            title: 'Notifications Enabled',
            html: `
                <p class="mb-2">Browser notifications are now enabled on this device.</p>
                <p class="notification-prompt-help mb-0">
                    You can manage or disable them anytime from
                    <strong>Profile &rarr; Browser Notifications</strong>.
                </p>`,
            confirmButtonText: 'OK',
        });
    };

    // Shared by the Profile button and the dashboard login prompt.
    const enablePushNotifications = async () => {
        if (!supported()) throw new Error('Browser notifications are not supported.');
        if (button) button.disabled = true;

        try {
            const worker = await loadRegistration();
            const existingSubscription = await worker.pushManager.getSubscription();

            if (existingSubscription) {
                await syncSubscription(existingSubscription);
                setStatus('Enabled', true);
                return true;
            }

            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                if (status) {
                    status.textContent = permission === 'denied'
                        ? 'Blocked in browser'
                        : 'Permission not granted';
                }
                return false;
            }

            const keyResponse = await requestJson(applicationUrl('/api/push_public_key.php'), {
                method: 'GET',
            });
            const subscription = await worker.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: base64UrlToUint8Array(keyResponse.public_key),
            });

            await syncSubscription(subscription);
            setStatus('Enabled', true);
            showEnabledMessage();
            return true;
        } catch (error) {
            console.error('Web Push update failed:', error);
            if (status) status.textContent = 'Unable to update notification setting';
            throw error;
        } finally {
            if (button && Notification.permission !== 'denied') button.disabled = false;
        }
    };

    const disablePushNotifications = async (subscription) => {
        if (button) button.disabled = true;
        try {
            const endpoint = subscription.endpoint;
            await subscription.unsubscribe();
            await requestJson(applicationUrl('/api/remove_push_subscription.php'), {
                method: 'POST',
                body: JSON.stringify({ endpoint }),
            });
            setStatus('Not enabled', false);
        } catch (error) {
            console.error('Web Push update failed:', error);
            if (status) status.textContent = 'Unable to update notification setting';
        } finally {
            if (button) button.disabled = false;
        }
    };

    const refreshState = async () => {
        if (!button || !status) return;
        if (!supported()) {
            button.disabled = true;
            status.textContent = 'Not supported by this browser';
            return;
        }
        if (Notification.permission === 'denied') {
            button.disabled = true;
            status.textContent = 'Blocked in browser';
            return;
        }

        try {
            const worker = await loadRegistration();
            const subscription = await worker.pushManager.getSubscription();
            if (subscription) {
                await syncSubscription(subscription);
                setStatus('Enabled', true);
                return;
            }
            setStatus('Not enabled', false);
        } catch (error) {
            console.error('Web Push initialization failed:', error);
            button.disabled = true;
            status.textContent = 'Unavailable';
        }
    };

    const showBlockedNotificationPopup = async () => {
        sessionStorage.setItem(promptDismissedKey, '1');
        if (!window.Swal) return;
        await Swal.fire({
            icon: 'info',
            title: 'Notifications Are Blocked',
            html: `
                <p>Browser notifications are currently blocked for e-GSlip.</p>
                <p class="notification-prompt-help mb-0">
                    To enable them, open your browser's site permissions for e-GSlip and allow Notifications.
                    You can also manage notification settings from
                    <strong>Profile &rarr; Browser Notifications</strong>.
                </p>`,
            confirmButtonText: 'OK',
        });
    };

    const showEnableNotificationPopup = async () => {
        if (!window.Swal) return;
        const result = await Swal.fire({
            icon: 'info',
            title: 'Enable Notifications',
            html: `
                <p>Stay updated when your gas slips are pending, recommended, approved, or rejected.</p>
                <p class="notification-prompt-help mb-0">
                    You can turn notifications off anytime from
                    <strong>Profile &rarr; Browser Notifications</strong> in the sidebar.
                </p>`,
            showCancelButton: true,
            confirmButtonText: 'Enable Notifications',
            cancelButtonText: 'Not Now',
            reverseButtons: true,
        });

        if (!result.isConfirmed) {
            sessionStorage.setItem(promptDismissedKey, '1');
            return;
        }

        try {
            await enablePushNotifications();
        } catch (error) {
            // The shared enable function already updates the Profile status.
        }
    };

    const checkPushNotificationStatus = async () => {
        if (
            !window.egslipPushPromptOnLogin ||
            !supported() ||
            sessionStorage.getItem(promptDismissedKey) === '1'
        ) return;

        if (Notification.permission === 'denied') {
            await showBlockedNotificationPopup();
            return;
        }

        try {
            await loadRegistration();
            const worker = await navigator.serviceWorker.ready;
            const subscription = await worker.pushManager.getSubscription();
            if (Notification.permission === 'granted' && subscription) return;
            await showEnableNotificationPopup();
        } catch (error) {
            console.error('Unable to check push notification status:', error);
        }
    };

    if (button) {
        button.addEventListener('click', async () => {
            if (!supported()) return;
            try {
                const worker = await loadRegistration();
                const subscription = await worker.pushManager.getSubscription();
                if (subscription) await disablePushNotifications(subscription);
                else await enablePushNotifications();
            } catch (error) {
                // The shared helpers already report failures in the Profile UI.
            }
        });
    }

    window.enablePushNotifications = enablePushNotifications;
    refreshState();

    const initializeLoginPrompt = () => checkPushNotificationStatus();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeLoginPrompt, { once: true });
    } else {
        initializeLoginPrompt();
    }
})();
