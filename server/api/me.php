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

$userId = isset($payload['sub']) ? (int)$payload['sub'] : 0;
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Neplatný token']);
    exit;
}

$email = isset($payload['email']) ? (string)$payload['email'] : '';
$username = isset($payload['username']) ? (string)$payload['username'] : $email;
$role = isset($payload['role']) && $payload['role'] !== '' ? (string)$payload['role'] : 'user';

echo json_encode(['user' => [
    'id' => $userId,
    'username' => $username,
    'email' => $email,
    'role' => $role,
]]);
