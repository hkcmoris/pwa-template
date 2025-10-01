<?php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/cors.php';

header('Content-Type: application/json');

$cookiePath = (defined('BASE_PATH') && BASE_PATH !== '' ? BASE_PATH : '/');
$cookieOptions = [
    'expires' => time() - 3600,
    'path' => $cookiePath,
    'httponly' => true,
    'samesite' => 'Lax',
];

setcookie('token', '', $cookieOptions);
unset($_COOKIE['token']);

// Revoke and clear refresh token
$refresh = $_COOKIE['refresh_token'] ?? '';
if ($refresh) {
    revoke_refresh_token($refresh);
}
setcookie('refresh_token', '', $cookieOptions);
unset($_COOKIE['refresh_token']);

log_message('User logged out');

echo json_encode(['success' => true]);
