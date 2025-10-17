/* eslint-disable no-undef */
const CACHE_NAME = 'runtime';

// Derive base path from SW registration scope ('' or '/subdir')
const SCOPE_PATH = new URL(self.registration.scope).pathname.replace(/\/$/, '');
// All hashed build artifacts (JS/CSS/image chunks) live under /public/assets/.
// Cache them with a cache-first strategy so islands remain available offline.
const ASSET_PREFIX = `${SCOPE_PATH}/public/assets/`;

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
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
    const isImmutableAsset = url.pathname.startsWith(ASSET_PREFIX);

    event.respondWith(
        caches.open(CACHE_NAME).then(async (cache) => {
            if (isImmutableAsset) {
                const cached = await cache.match(event.request);
                if (cached) {
                    return cached;
                }
                try {
                    const response = await fetch(event.request);
                    if (response.ok) {
                        cache.put(event.request, response.clone());
                    }
                    return response;
                } catch {
                    const fallback = await cache.match(event.request);
                    if (fallback) {
                        return fallback;
                    }
                    throw new Error('Network error');
                }
            }

            try {
                const response = await fetch(event.request);
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
