<?php
require_once __DIR__.'/cors.php';
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/jwt.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/logger.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';
$password = $input['password'] ?? '';

log_message("Login attempt for {$email}");

if (!$email || !$password) {
    log_message('Login failed: missing email or password', 'ERROR');
    http_response_code(400);
    echo json_encode(['error' => 'Email and password required']);
    exit;
}

$db = get_db_connection();
$stmt = $db->prepare('SELECT id, password FROM users WHERE email = :email');
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    log_message("Login failed: invalid credentials for {$email}", 'ERROR');
    http_response_code(401);
    echo json_encode(['error' => 'Invalid credentials']);
    exit;
}

$accessTtl = 600;
$token = generate_jwt(['sub' => $user['id'], 'email' => $email], JWT_SECRET, $accessTtl);
setcookie('token', $token, [
    'httponly' => true,
    'samesite' => 'Lax',
    'expires' => time() + $accessTtl,
    'path' => (defined('BASE_PATH') && BASE_PATH !== '' ? BASE_PATH : '/'),
]);

// Issue refresh token (14 days) and set cookie
$refreshTtl = 14 * 24 * 3600;
$refresh = create_refresh_token((int)$user['id'], $refreshTtl);
setcookie('refresh_token', $refresh, [
    'httponly' => true,
    'samesite' => 'Lax',
    'expires' => time() + $refreshTtl,
    'path' => (defined('BASE_PATH') && BASE_PATH !== '' ? BASE_PATH : '/'),
]);

log_message("User logged in: {$email}");

echo json_encode(['token' => $token]);
