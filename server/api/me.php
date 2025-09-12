<?php
require_once __DIR__.'/cors.php';
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/jwt.php';
require_once __DIR__.'/../lib/logger.php';

header('Content-Type: application/json');

$token = $_COOKIE['token'] ?? '';
$payload = $token ? verify_jwt($token, JWT_SECRET) : false;
if (!$payload) {
    // Guest: return null user with 200 to avoid noisy 401s for anonymous visits
    echo json_encode(['user' => null]);
    exit;
}

$db = get_db_connection();
$stmt = $db->prepare('SELECT id, username, email, role, created_at FROM users WHERE id = :id');
$stmt->execute([':id' => (int)$payload['sub']]);
$user = $stmt->fetch();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Uživatel nenalezen']);
    exit;
}

echo json_encode(['user' => [
    'id' => (int)$user['id'],
    'username' => $user['username'],
    'email' => $user['email'],
    'role' => $user['role'] ?? 'user',
]]);
