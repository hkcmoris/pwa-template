<?php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/cors.php';

header('Content-Type: application/json');
$jwtSecret = config_jwt_secret();

csrf_require_valid(null, 'json');

$refresh = $_COOKIE['refresh_token'] ?? '';
if (!$refresh) {
    http_response_code(401);
    echo json_encode(['error' => 'ChybĂ­ obnovovacĂ­ token']);
    exit;
}

$row = find_valid_refresh_token($refresh);
if (!$row) {
    http_response_code(401);
    echo json_encode(['error' => 'NeplatnĂ˝ obnovovacĂ­ token']);
    exit;
}

$userId = (int)$row['user_id'];
$db = get_db_connection();
$stmt = $db->prepare('SELECT email, role FROM users WHERE id = :id');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'NeplatnĂ˝ uĹľivatel']);
    exit;
}

// Rotate refresh token
$refreshTtl = 14 * 24 * 3600;

$base = defined('BASE_PATH') ? (string) BASE_PATH : '';
$cookiePath = '/' . trim($base, '/');

$newRefresh = rotate_refresh_token($refresh, $userId, $refreshTtl);
if (!$newRefresh) {
    http_response_code(401);
    echo json_encode(['error' => 'NeplatnĂ˝ obnovovacĂ­ token']);
    exit;
}

setcookie('refresh_token', $newRefresh, [
    'httponly' => true,
    'samesite' => 'Lax',
    'expires' => time() + $refreshTtl,
    'path' => $cookiePath,
    'secure' => !app_is_dev(),
]);

// Issue new access token (10 minutes)
$access = generate_jwt(
    ['sub' => $userId, 'email' => $user['email'], 'role' => $user['role'] ?? 'user'],
    $jwtSecret,
    600
);
setcookie('token', $access, [
    'httponly' => true,
    'samesite' => 'Lax',
    'path' => $cookiePath,
    'secure' => !app_is_dev(),
]);

echo json_encode(['token' => $access, 'expires_in' => 600]);
