<?php

require_once __DIR__ . '/config/config.php';

header('Content-Type: application/javascript; charset=UTF-8');

$requested = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/sw.js';
$base = defined('BASE_PATH') ? rtrim(BASE_PATH, '/') : '';
if ($base !== '' && strpos($requested, $base) === 0) {
    $requested = substr($requested, strlen($base));
}
$requested = ltrim($requested, '/');

if (!defined('SW_ENABLED') || !SW_ENABLED) {
    http_response_code(200);
    header('Cache-Control: no-store');
    echo <<<JS
    self.addEventListener('install', (event) => {
        event.waitUntil(self.skipWaiting());
    });
    self.addEventListener('activate', (event) => {
        event.waitUntil((async () => {
            const cacheNames = await caches.keys();
            await Promise.all(cacheNames.map((cacheName) => caches.delete(cacheName)));
            await self.clients.claim();
            await self.registration.unregister();
        })());
    });
    self.addEventListener('fetch', (event) => {
        event.respondWith(fetch(event.request));
    });
    JS;
    exit;
}

$public = __DIR__ . '/public';
$relative = $requested === '' ? 'sw.js' : $requested;
if ($relative === 'sw.js') {
    $file = $public . '/sw.js';
} elseif (preg_match('/^sw-([A-Za-z0-9_-]+)\.js$/', $relative)) {
    $file = $public . '/sw/' . $relative;
} elseif (preg_match('/^sw\/sw-([A-Za-z0-9_-]+)\.js$/', $relative)) {
    $file = $public . '/' . $relative;
} else {
    http_response_code(404);
    header('Cache-Control: no-store');
    exit;
}

if (!is_file($file)) {
    http_response_code(404);
    header('Cache-Control: no-store');
    exit;
}

$mtime = @filemtime($file);
header('Cache-Control: public, max-age=0, must-revalidate');
if ($mtime) {
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
}
readfile($file);
exit;
