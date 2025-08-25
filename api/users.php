<?php
require_once __DIR__.'/cors.php';
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../jwt.php';
require_once __DIR__.'/../logger.php';

header('Content-Type: application/json');

$token = $_COOKIE['token'] ?? '';
$payload = verify_jwt($token, JWT_SECRET);
if (!$payload) {
    log_message('Users request unauthorized', 'ERROR');
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

log_message("Users list requested by {$payload['email']}");

$db = get_db_connection();
$stmt = $db->prepare('SELECT id, email, created_at FROM users');
$stmt->execute();
$users = $stmt->fetchAll();

echo json_encode(['users' => $users]);

