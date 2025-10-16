<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/http.php';

disable_response_cache();
// Basic CORS headers (dev-only). In production same-origin is used.
if ((defined('APP_ENV') ? APP_ENV : 'dev') === 'dev') {
    $allowedOrigin = 'http://localhost:5173';
    if (!empty($_SERVER['HTTP_ORIGIN'])) {
        // Only echo the dev origin to avoid mismatches
        header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    } else {
        header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    }
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
