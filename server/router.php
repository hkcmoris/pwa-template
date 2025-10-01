<?php

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Decode percent-encoded path so files with spaces/diacritics resolve correctly
$decoded = rawurldecode($path ?? '/');
$file = __DIR__ . $decoded;
if (preg_match('/^\/sw(?:-[A-Za-z0-9]+)?\.js$/', $decoded)) {
    require __DIR__ . '/sw.php';
}

// Normalize and ensure the requested file stays under the server docroot
$root = str_replace('\\', '/', realpath(__DIR__));
$cand = str_replace('\\', '/', realpath($file) ?: $file);
if ($decoded !== '/' && $cand && strpos($cand, $root) === 0 && is_file($cand)) {
// Let PHP's built-in server serve the static file
    return false;
}

require __DIR__ . '/index.php';
