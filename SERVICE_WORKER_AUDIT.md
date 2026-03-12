# Service Worker Usage Audit

## Scope and method

This audit covers all service worker related files discoverable via repository search (`rg -n "service worker|service-worker|sw\.js|navigator\.serviceWorker|workbox|manifest\.webmanifest|offline"`) and direct SW routing/build references.

## Key findings

1. **Registration timing is correct**
   - The service worker is registered behind `requestIdleCallback` (with `setTimeout` fallback), so it runs after initial paint/critical work.

2. **Versioning pipeline is implemented and mostly coherent**
   - Build script stamps `CACHE_NAME` with the Vite hash, writes `server/public/sw/sw-<hash>.js`, and emits `server/public/sw.js` as a loader importing the hashed worker.
   - Runtime and rewrite rules route `/sw.js`, `/sw-<hash>.js`, and `/sw/sw-<hash>.js` through `sw.php`.

3. **Potentially incorrect auth bypass logic in SW fetch handling**
   - `server/sw.js` attempts to bypass cache if request has `cookie` header (`event.request.headers.has('cookie')`). In browser fetch APIs this is a forbidden header and is generally not script-visible, so this check is likely ineffective.
   - Practical impact: authenticated asset/API/document requests are still mostly protected by `isDocument` and `NO_CACHE_PREFIXES`, but cookie-based bypass appears unreliable and should not be relied on.

4. **Offline fallback route is not implemented**
   - SW currently does network-first for documents and has no `/offline` fallback response path.
   - This may be intentional for now, but it does not match the stated project guardrail requiring an offline fallback route.

5. **SW disable path is thoughtfully handled**
   - When `SW_ENABLED` is false, PHP serves a kill-switch worker that unregisters and clears caches.
   - Client-side layout code also attempts unregister + cache cleanup on idle when SW is disabled.

## Files related to service worker

- `server/sw.js`: Main service worker runtime (install/activate/fetch caching policy).
- `server/sw.php`: Service worker delivery endpoint and SW disable kill-switch script.
- `server/views/layout.php`: Manifest link + service worker register/unregister bootstrap script in SSR layout.
- `scripts/build.mjs`: Build-time SW versioning and loader generation (`sw-<hash>.js` + `/sw.js`).
- `server/router.php`: Dev router interception for SW paths to ensure `sw.php` handles them.
- `server/.htaccess`: Production rewrite rules mapping SW URLs to `sw.php`.
- `server/config/config.php`: Reads `SW` env flag and defines `SW_ENABLED` toggle.
- `server/env.example`: Documents the `SW=ON|OFF` environment variable.
- `server/public/manifest.webmanifest`: PWA manifest referenced by layout.
- `server/phpcs.xml`: Excludes `sw.js` from PHPCS checks (tooling relation only).
- `DEPLOYMENT.md`: Post-deployment check includes SW registration/manifest verification.

## Candidate unnecessary files (high-confidence only)

- **None at high confidence.**
  - The files all have a clear role in routing, build, runtime behavior, config, or operational verification.
