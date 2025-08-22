<?php
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../jwt.php';

header('Content-Type: application/json');

$token = $_COOKIE['token'] ?? '';
$payload = verify_jwt($token, JWT_SECRET);
if (!$payload) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = get_db_connection();
$stmt = $db->prepare('SELECT id, email, created_at FROM users');
$stmt->execute();
$users = $stmt->fetchAll();

echo json_encode(['users' => $users]);

