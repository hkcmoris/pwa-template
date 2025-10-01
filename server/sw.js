const CACHE_NAME = 'runtime';

// Derive base path from SW registration scope ('' or '/subdir')
const SCOPE_PATH = new URL(self.registration.scope).pathname.replace(/\/$/, '');

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async() => {
            const names = await caches.keys();
            await Promise.all(
                names
                    .filter((n) => n !== CACHE_NAME)
                    .map((n) => caches.delete(n))
            );
            await self.clients.claim();
        })()
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }
    const url = new URL(event.request.url);
    event.respondWith(
        caches.open(CACHE_NAME).then(async(cache) => {
            try {
                const response = await fetch(event.request);
                // Cache built assets (respect subfolder deployments)
                if (
                    url.pathname.startsWith(
                        `${SCOPE_PATH} / public / assets / `
                    )
                ) {
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
