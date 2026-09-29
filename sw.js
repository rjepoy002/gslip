self.addEventListener('push', (event) => {
    let payload = {};

    try {
        payload = event.data ? event.data.json() : {};
    } catch (error) {
        payload = {};
    }

    const title = typeof payload.title === 'string' && payload.title.trim()
        ? payload.title.trim()
        : 'e-GSlip Notification';
    const body = typeof payload.body === 'string' ? payload.body : '';
    const url = typeof payload.url === 'string' ? payload.url : '';

    event.waitUntil(
        self.registration.showNotification(title, {
            body,
            data: {
                url,
                gas_slip_id: Number(payload.gas_slip_id) || 0,
                type: typeof payload.type === 'string' ? payload.type : '',
            },
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const notificationData = event.notification.data || {};
    const targetUrl = new URL(
        notificationData.url || 'dashboard.php',
        self.registration.scope
    ).href;
    const appScope = new URL(self.registration.scope);

    event.waitUntil((async () => {
        const windows = await clients.matchAll({ type: 'window', includeUncontrolled: true });
        const appWindow = windows.find((client) => {
            const clientUrl = new URL(client.url);
            return clientUrl.origin === appScope.origin
                && clientUrl.pathname.startsWith(appScope.pathname);
        });

        if (appWindow) {
            await appWindow.focus();
            return appWindow.navigate(targetUrl);
        }

        return clients.openWindow(targetUrl);
    })());
});
