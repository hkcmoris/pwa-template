<?php
require_once __DIR__.'/cors.php';
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/jwt.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/logger.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$username = $input['username'] ?? '';
$email = $input['email'] ?? '';
$password = $input['password'] ?? '';

log_message("Registration attempt for {$email}");

if (!$username || !$email || !$password) {
    log_message('Registration failed: missing username, email or password', 'ERROR');
    http_response_code(400);
    echo json_encode(['error' => 'Uživatelské jméno, e‑mail a heslo jsou povinné']);
    exit;
}

$db = get_db_connection();

try {
    $stmt = $db->prepare('SELECT id FROM users WHERE email = :email');
    $stmt->execute([':email' => $email]);

    if ($stmt->fetch()) {
        log_message("Registration failed: email already registered ({$email})", 'ERROR');
        http_response_code(409);
        echo json_encode(['error' => 'E‑mail je již zaregistrován']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare(
        'INSERT INTO users (username, email, password) VALUES (:username, :email, :password)'
    );
    $stmt->execute([':username' => $username, ':email' => $email, ':password' => $hash]);
    $userId = $db->lastInsertId();
    log_message("User registered: {$email}");
} catch (PDOException $e) {
    log_message('Registration DB error: ' . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode(['error' => 'Chyba databáze']);
    exit;
}

// Issue access token (10 minutes)
$accessTtl = 600;
$token = generate_jwt(['sub' => $userId, 'email' => $email], JWT_SECRET, $accessTtl);
setcookie('token', $token, [
    'httponly' => true,
    'samesite' => 'Lax',
    'expires' => time() + $accessTtl,
    'path' => (defined('BASE_PATH') && BASE_PATH !== '' ? BASE_PATH : '/'),
]);

// Issue refresh token (14 days)
$refreshTtl = 14 * 24 * 3600;
$refresh = create_refresh_token((int)$userId, $refreshTtl);
setcookie('refresh_token', $refresh, [
    'httponly' => true,
    'samesite' => 'Lax',
    'expires' => time() + $refreshTtl,
    'path' => (defined('BASE_PATH') && BASE_PATH !== '' ? BASE_PATH : '/'),
]);

http_response_code(201);
echo json_encode(['token' => $token]);
exit;
