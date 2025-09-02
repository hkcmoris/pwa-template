<?php
require_once __DIR__.'/cors.php';
require_once __DIR__.'/../lib/logger.php';
require_once __DIR__.'/../lib/auth.php';

header('Content-Type: application/json');

setcookie('token', '', [
    'expires' => time() - 3600,
    'path' => (defined('BASE_PATH') && BASE_PATH !== '' ? BASE_PATH : '/'),
]);

// Revoke and clear refresh token
$refresh = $_COOKIE['refresh_token'] ?? '';
if ($refresh) {
    revoke_refresh_token($refresh);
}
setcookie('refresh_token', '', [
    'expires' => time() - 3600,
    'path' => (defined('BASE_PATH') && BASE_PATH !== '' ? BASE_PATH : '/'),
]);

log_message('User logged out');

echo json_encode(['success' => true]);
