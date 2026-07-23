const CACHE_NAME = 'metalsp-static-v1';
const APP_SHELL = ['/offline.html', '/manifest.webmanifest', '/logo.jpg', '/favicon.ico'];

self.addEventListener('install', event => {
    event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(APP_SHELL)));
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => Promise.all(
            keys.filter(key => key.startsWith('metalsp-') && key !== CACHE_NAME).map(key => caches.delete(key))
        )).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) return;

    // Never cache account pages, price polling, or any dynamic Laravel response.
    if (url.pathname.startsWith('/api/') || request.mode === 'navigate') {
        if (request.mode === 'navigate') {
            event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));
        }
        return;
    }

    // Versioned Vite assets and site images are safe to serve cache-first.
    if (url.pathname.startsWith('/build/') || /\.(?:css|js|jpg|jpeg|png|svg|ico|woff2?)$/i.test(url.pathname)) {
        event.respondWith(
            caches.match(request).then(cached => cached || fetch(request).then(response => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(request, copy));
                }
                return response;
            }))
        );
    }
});
