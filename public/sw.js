const VERSION = 'meetspace-v20';
const SHELL = ['/', '/app.css', '/common.js', '/app.js', '/manifest.webmanifest', '/icons/icon-192.png', '/offline.html'];

self.addEventListener('install', (e) => {
    e.waitUntil(caches.open(VERSION).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
    e.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (e) => {
    const req = e.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== location.origin) return;

    // never cache private data (admin check, sign-in, a user's own bookings)
    if (/^\/api\/(admin|auth|me)/.test(url.pathname)) return;

    // API: network first, fall back to last cached response (read-only offline)
    if (url.pathname.startsWith('/api/')) {
        e.respondWith(
            fetch(req)
                .then((res) => {
                    const copy = res.clone();
                    if (res.ok) caches.open(VERSION).then((c) => c.put(req, copy));
                    return res;
                })
                .catch(() => caches.match(req))
        );
        return;
    }

    // Pages: network first, fall back to cache, then offline page
    if (req.mode === 'navigate') {
        e.respondWith(fetch(req).catch(() => caches.match('/').then((r) => r || caches.match('/offline.html'))));
        return;
    }

    // Static assets: stale-while-revalidate
    e.respondWith(
        caches.match(req).then((cached) => {
            const net = fetch(req).then((res) => {
                if (res.ok) caches.open(VERSION).then((c) => c.put(req, res.clone()));
                return res;
            }).catch(() => cached);
            return cached || net;
        })
    );
});
