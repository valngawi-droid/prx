/* ChiperX Service Worker — PWA ringan:
   - Aset statis /assets/*: cache-first (cepat & hemat kuota)
   - Navigasi HTML: network-first, fallback ke /offline.html saat terputus
   - POST & host eksternal: dibiarkan lewat apa adanya (aman untuk CSRF/auth) */
const CACHE = 'chiperx-static-v1';
const CORE  = ['/offline.html'];

self.addEventListener('install', (e) => {
    e.waitUntil(caches.open(CACHE).then((c) => c.addAll(CORE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
    e.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (e) => {
    const req = e.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== location.origin) return;

    // Halaman HTML → network-first
    if (req.mode === 'navigate') {
        e.respondWith(fetch(req).catch(() => caches.match('/offline.html')));
        return;
    }

    // Aset statis → cache-first
    if (url.pathname.startsWith('/assets/')) {
        e.respondWith(
            caches.match(req).then((hit) =>
                hit ||
                fetch(req).then((res) => {
                    if (res.ok) {
                        const copy = res.clone();
                        caches.open(CACHE).then((c) => c.put(req, copy));
                    }
                    return res;
                })
            )
        );
    }
});
