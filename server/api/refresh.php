<?php
require_once __DIR__.'/cors.php';
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/jwt.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/logger.php';

header('Content-Type: application/json');

$refresh = $_COOKIE['refresh_token'] ?? '';
if (!$refresh) {
    http_response_code(401);
    echo json_encode(['error' => 'Chybí obnovovací token']);
    exit;
}

$row = find_valid_refresh_token($refresh);
if (!$row) {
    http_response_code(401);
    echo json_encode(['error' => 'Neplatný obnovovací token']);
    exit;
}

$userId = (int)$row['user_id'];
$db = get_db_connection();
$stmt = $db->prepare('SELECT email, role FROM users WHERE id = :id');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Neplatný uživatel']);
    exit;
}

// Rotate refresh token
$refreshTtl = 14 * 24 * 3600;
$newRefresh = rotate_refresh_token($refresh, $userId, $refreshTtl);
if (!$newRefresh) {
    http_response_code(401);
    echo json_encode(['error' => 'Neplatný obnovovací token']);
    exit;
}

setcookie('refresh_token', $newRefresh, [
    'httponly' => true,
    'samesite' => 'Lax',
    'expires' => time() + $refreshTtl,
    'path' => (defined('BASE_PATH') && BASE_PATH !== '' ? BASE_PATH : '/'),
]);

// Issue new access token (10 minutes)
$access = generate_jwt(['sub' => $userId, 'email' => $user['email'], 'role' => $user['role'] ?? 'user'], JWT_SECRET, 600);
setcookie('token', $access, [
    'httponly' => true,
    'samesite' => 'Lax',
    'path' => (defined('BASE_PATH') && BASE_PATH !== '' ? BASE_PATH : '/'),
]);

echo json_encode(['token' => $access, 'expires_in' => 600]);
