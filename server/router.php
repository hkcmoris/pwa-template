<?php

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Decode percent-encoded path so files with spaces/diacritics resolve correctly
$decoded = rawurldecode($path ?? '/');
$file = __DIR__ . $decoded;
if (preg_match('/^\/sw(?:-[A-Za-z0-9]+)?\.js$/', $decoded)) {
    require __DIR__ . '/sw.php';
}

// Normalize and ensure the requested file stays under the server docroot
$rootReal = realpath(__DIR__);
$root = $rootReal === false ? '' : str_replace('\\', '/', $rootReal);
$candReal = realpath($file);
$candidatePath = $candReal === false ? $file : $candReal;
$cand = str_replace('\\', '/', $candidatePath);
$inDocroot = false;
if ($root !== '') {
    $inDocroot = strncmp($cand, $root, strlen($root)) === 0;
}
if ($decoded !== '/' && $inDocroot && is_file($cand)) {
    // Let PHP's built-in server serve the static file
    return false;
}

require __DIR__ . '/index.php';
