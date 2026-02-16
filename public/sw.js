const CACHE_NAME = 'up-cache-v2'

self.addEventListener('install', () => {
    self.skipWaiting()
})

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then(names =>
            Promise.all(names.map(n => caches.delete(n)))
        ).then(() => self.clients.claim())
    )
})

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return

    const url = new URL(event.request.url)
    if (url.origin !== location.origin) return

    // Only cache static assets (cache-first)
    if (/\.(css|js|png|jpg|svg|woff2?|ttf)$/i.test(url.pathname) || url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.match(event.request).then(cached =>
                cached || fetch(event.request).then(response => {
                    if (response.ok) {
                        const clone = response.clone()
                        caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone))
                    }
                    return response
                })
            )
        )
    }
})

// Push notifications
self.addEventListener('push', (event) => {
    let data = { title: 'Up', body: 'New notification', data: {} }
    if (event.data) {
        try { data = { ...data, ...event.data.json() } } catch (e) { data.body = event.data.text() }
    }
    event.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body,
            icon: '/icons/icon-192.png',
            tag: 'up-notification',
            data: data.data,
            vibrate: [100, 50, 100],
        })
    )
})

self.addEventListener('notificationclick', (event) => {
    event.notification.close()
    const url = event.notification.data?.url || '/monitors'
    event.waitUntil(
        clients.matchAll({ type: 'window' }).then(wins => {
            for (const win of wins) {
                if (new URL(win.url).origin === location.origin) {
                    win.focus()
                    return win.navigate(url)
                }
            }
            return clients.openWindow(url)
        })
    )
})
