<?php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/cors.php';
header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';
$password = $input['password'] ?? '';
$base = defined('BASE_PATH') ? (string) BASE_PATH : '';
$cookiePath = '/' . trim($base, '/');
log_message("Login attempt for {$email}");
if (!$email || !$password) {
    log_message('Login failed: missing email or password', 'ERROR');
    http_response_code(400);
    echo json_encode(['error' => 'E‑mail a heslo jsou povinné']);
    exit;
}

$db = get_db_connection();
$stmt = $db->prepare('SELECT id, password, role FROM users WHERE email = :email');
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();
if (!$user || !password_verify($password, $user['password'])) {
    log_message("Login failed: invalid credentials for {$email}", 'ERROR');
    http_response_code(401);
    echo json_encode(['error' => 'Neplatné přihlašovací údaje']);
    exit;
}

$accessTtl = 600;
$token = generate_jwt(
    ['sub' => $user['id'], 'email' => $email, 'role' => $user['role'] ?? 'user'],
    JWT_SECRET,
    $accessTtl
);
setcookie('token', $token, [
    'httponly' => true,
    'samesite' => 'Lax',
    'expires' => time() + $accessTtl,
    'path' => $cookiePath,
]);
// Issue refresh token (14 days) and set cookie
$refreshTtl = 14 * 24 * 3600;
$refresh = create_refresh_token((int)$user['id'], $refreshTtl);
setcookie('refresh_token', $refresh, [
    'httponly' => true,
    'samesite' => 'Lax',
    'expires' => time() + $refreshTtl,
    'path' => $cookiePath,
]);
log_message("User logged in: {$email}");
$role = $user['role'] ?? 'user';
echo json_encode(['token' => $token, 'user' => [
    'email' => $email,
    'role' => $role,
]]);
