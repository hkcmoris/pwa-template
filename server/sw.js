const CACHE_NAME = 'runtime';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;
    const url = new URL(event.request.url);
    event.respondWith(
        caches.open(CACHE_NAME).then(async (cache) => {
            try {
                const response = await fetch(event.request);
                if (url.pathname.startsWith('/assets/')) {
                    cache.put(event.request, response.clone());
                }
                return response;
            } catch {
                const cached = await cache.match(event.request);
                if (cached) {
                    return cached;
                }
                throw new Error('Network error');
            }
        })
    );
});
