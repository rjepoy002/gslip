(() => {
    const button = document.getElementById('pushNotificationButton');
    const status = document.getElementById('pushNotificationStatus');
    const script = document.currentScript;

    if (!button || !status || !script) {
        return;
    }

    const scriptUrl = new URL(script.src, window.location.href);
    const assetPathIndex = scriptUrl.pathname.lastIndexOf('/assets/js/');
    const basePath = assetPathIndex >= 0
        ? scriptUrl.pathname.slice(0, assetPathIndex)
        : '';

    const applicationUrl = (path) => `${basePath}${path}`;

    let registration = null;

    /*
    |--------------------------------------------------------------------------
    | UI Status
    |--------------------------------------------------------------------------
    */

    const setStatus = (text, enabled = false) => {
        status.textContent = text;
        button.textContent = enabled
            ? 'Disable Notifications'
            : 'Enable Notifications';

        button.disabled = false;
    };

    /*
    |--------------------------------------------------------------------------
    | Browser Support
    |--------------------------------------------------------------------------
    */

    const supported = () =>
        'serviceWorker' in navigator &&
        'PushManager' in window &&
        'Notification' in window;

    /*
    |--------------------------------------------------------------------------
    | JSON Request Helper
    |--------------------------------------------------------------------------
    */

    const requestJson = async (url, options = {}) => {

        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers: {
                'Content-Type': 'application/json',
                ...(options.headers || {}),
            },
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(
                data.message || 'Request failed.'
            );
        }

        return data;
    };

    /*
    |--------------------------------------------------------------------------
    | Convert VAPID Public Key
    |--------------------------------------------------------------------------
    */

    const base64UrlToUint8Array = (value) => {

        const padding =
            '='.repeat((4 - (value.length % 4)) % 4);

        const base64 =
            (value + padding)
                .replace(/-/g, '+')
                .replace(/_/g, '/');

        const raw = window.atob(base64);

        return Uint8Array.from(
            raw,
            (character) => character.charCodeAt(0)
        );
    };

    /*
    |--------------------------------------------------------------------------
    | Service Worker Registration
    |--------------------------------------------------------------------------
    */

    const loadRegistration = async () => {

        if (!registration) {

            registration =
                await navigator.serviceWorker.register(
                    applicationUrl('/sw.js'),
                    {
                        scope: `${basePath || ''}/`,
                    }
                );
        }

        return registration;
    };

    /*
    |--------------------------------------------------------------------------
    | Synchronize Existing Subscription
    |--------------------------------------------------------------------------
    |
    | Important:
    |
    | A browser subscription may have been created while another e-GSlip
    | account was logged in.
    |
    | Whenever an authenticated e-GSlip page loads, send the existing
    | subscription back to the server.
    |
    | save_push_subscription.php will associate the endpoint with the
    | CURRENT $_SESSION['user_id'].
    |--------------------------------------------------------------------------
    */

    const syncSubscription = async (subscription) => {

        if (!subscription) {
            return;
        }

        await requestJson(
            applicationUrl('/api/save_push_subscription.php'),
            {
                method: 'POST',
                body: JSON.stringify(
                    subscription.toJSON()
                ),
            }
        );
    };

    /*
    |--------------------------------------------------------------------------
    | Refresh Current Browser State
    |--------------------------------------------------------------------------
    */

    const refreshState = async () => {

        if (!supported()) {

            button.disabled = true;
            status.textContent =
                'Not supported by this browser';

            return;
        }

        if (Notification.permission === 'denied') {

            button.disabled = true;
            status.textContent =
                'Blocked in browser';

            return;
        }

        try {

            const worker =
                await loadRegistration();

            const subscription =
                await worker.pushManager.getSubscription();

            /*
             * Existing subscription:
             *
             * Synchronize it with the account that is
             * CURRENTLY logged into e-GSlip.
             */
            if (subscription) {

                await syncSubscription(subscription);

                setStatus(
                    'Enabled',
                    true
                );

                return;
            }

            setStatus(
                'Not enabled',
                false
            );

        } catch (error) {

            console.error(
                'Web Push initialization failed:',
                error
            );

            button.disabled = true;
            status.textContent = 'Unavailable';
        }
    };

    /*
    |--------------------------------------------------------------------------
    | Enable / Disable Notifications
    |--------------------------------------------------------------------------
    */

    button.addEventListener(
        'click',
        async () => {

            if (!supported()) {
                return;
            }

            button.disabled = true;

            try {

                const worker =
                    await loadRegistration();

                const existingSubscription =
                    await worker.pushManager.getSubscription();

                /*
                |--------------------------------------------------------------------------
                | Disable Existing Subscription
                |--------------------------------------------------------------------------
                */

                if (existingSubscription) {

                    const endpoint =
                        existingSubscription.endpoint;

                    /*
                     * Remove from browser first.
                     */
                    await existingSubscription.unsubscribe();

                    /*
                     * Remove from e-GSlip database.
                     */
                    await requestJson(
                        applicationUrl(
                            '/api/remove_push_subscription.php'
                        ),
                        {
                            method: 'POST',
                            body: JSON.stringify({
                                endpoint,
                            }),
                        }
                    );

                    setStatus(
                        'Not enabled',
                        false
                    );

                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | Request Notification Permission
                |--------------------------------------------------------------------------
                */

                const permission =
                    await Notification.requestPermission();

                if (permission !== 'granted') {

                    status.textContent =
                        permission === 'denied'
                            ? 'Blocked in browser'
                            : 'Permission not granted';

                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | Get VAPID Public Key
                |--------------------------------------------------------------------------
                */

                const keyResponse =
                    await requestJson(
                        applicationUrl(
                            '/api/push_public_key.php'
                        ),
                        {
                            method: 'GET',
                        }
                    );

                /*
                |--------------------------------------------------------------------------
                | Create Browser Subscription
                |--------------------------------------------------------------------------
                */

                const subscription =
                    await worker.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey:
                            base64UrlToUint8Array(
                                keyResponse.public_key
                            ),
                    });

                /*
                |--------------------------------------------------------------------------
                | Save Subscription
                |--------------------------------------------------------------------------
                */

                await syncSubscription(subscription);

                setStatus(
                    'Enabled',
                    true
                );

            } catch (error) {

                console.error(
                    'Web Push update failed:',
                    error
                );

                status.textContent =
                    'Unable to update notification setting';

            } finally {

                if (
                    !button.disabled ||
                    status.textContent !==
                        'Blocked in browser'
                ) {
                    button.disabled = false;
                }
            }
        }
    );

    /*
    |--------------------------------------------------------------------------
    | Initialize
    |--------------------------------------------------------------------------
    */

    refreshState();

})();