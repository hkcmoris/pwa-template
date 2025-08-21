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
$stmt = $db->prepare('SELECT id FROM users WHERE email = :email');
$stmt->execute([':email' => $email]);

if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Email already registered']);
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $db->prepare('INSERT INTO users (email, password) VALUES (:email, :password)');
$stmt->execute([':email' => $email, ':password' => $hash]);
$userId = $db->lastInsertId();

$token = generate_jwt(['sub' => $userId, 'email' => $email], JWT_SECRET);
setcookie('token', $token, [
    'httponly' => true,
    'samesite' => 'Lax',
    'path' => '/',
]);

echo json_encode(['token' => $token]);
