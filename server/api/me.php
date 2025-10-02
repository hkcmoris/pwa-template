<?php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/cors.php';

header('Content-Type: application/json');
$jwtSecret = config_jwt_secret();

$token = $_COOKIE['token'] ?? '';
$refresh = $_COOKIE['refresh_token'] ?? '';
$payload = $token ? verify_jwt($token, $jwtSecret) : false;
if (!$payload) {
    // If there is a refresh token cookie, signal 401 so the client can refresh.
    // If no refresh token exists, treat as an anonymous guest with 200 + null user.
    if ($refresh) {
        http_response_code(401);
        echo json_encode(['error' => 'Access token expired']);
        exit;
    }
    echo json_encode(['user' => null]);
    exit;
}

$db = get_db_connection();
$stmt = $db->prepare('SELECT id, username, email, role, created_at FROM users WHERE id = :id');
$stmt->execute([':id' => (int)$payload['sub']]);
$user = $stmt->fetch();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'UĹľivatel nenalezen']);
    exit;
}

echo json_encode(['user' => [
    'id' => (int)$user['id'],
    'username' => $user['username'],
    'email' => $user['email'],
    'role' => $user['role'] ?? 'user',
]]);
