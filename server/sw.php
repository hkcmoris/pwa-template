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
    echo "self.addEventListener('install', (event) => {\n";
    echo "  event.waitUntil(self.skipWaiting());\n";
    echo "});\n";
    echo "self.addEventListener('activate', (event) => {\n";
    echo "  event.waitUntil((async () => {\n";
    echo "    const cacheNames = await caches.keys();\n";
    echo "    await Promise.all(cacheNames.map((cacheName) => caches.delete(cacheName)));\n";
    echo "    await self.clients.claim();\n";
    echo "    await self.registration.unregister();\n";
    echo "  })());\n";
    echo "});\n";
    echo "self.addEventListener('fetch', (event) => {\n";
    echo "  event.respondWith(fetch(event.request));\n";
    echo "});\n";
    exit;
}

$relative = $requested === '' ? 'sw.js' : $requested;
if ($relative === 'sw.js') {
    $file = __DIR__ . '/sw.js';
} elseif (preg_match('/^sw-([A-Za-z0-9]+)\\.js$/', $relative)) {
    $file = __DIR__ . '/' . $relative;
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
