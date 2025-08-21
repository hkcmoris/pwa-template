<?php
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../jwt.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';
$password = $input['password'] ?? '';

if (!$email || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'Email and password required']);
    exit;
}

$db = get_db_connection();
$stmt = $db->prepare('SELECT id, password FROM users WHERE email = :email');
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid credentials']);
    exit;
}

$token = generate_jwt(['sub' => $user['id'], 'email' => $email], JWT_SECRET);
setcookie('token', $token, [
    'httponly' => true,
    'samesite' => 'Lax',
    'path' => '/',
]);

echo json_encode(['token' => $token]);
