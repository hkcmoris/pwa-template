<?php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/cors.php';

header('Content-Type: application/json');
$jwtSecret = config_jwt_secret();

$token = $_COOKIE['token'] ?? '';
$payload = verify_jwt($token, $jwtSecret);
if (!$payload) {
    log_message('Users request unauthorized', 'ERROR');
    http_response_code(401);
    echo json_encode(['error' => 'Neautorizováno']);
    exit;
}

$db = get_db_connection();
// Verify caller role from DB to prevent stale token claims
$stmt = $db->prepare('SELECT role, email FROM users WHERE id = :id');
$stmt->execute([':id' => (int)$payload['sub']]);
$caller = $stmt->fetch();
if (!$caller || !in_array(($caller['role'] ?? 'user'), ['admin','superadmin'], true)) {
    log_message('Users request forbidden for ' . ($caller['email'] ?? 'unknown'), 'ERROR');
    http_response_code(403);
    echo json_encode(['error' => 'Zakázáno']);
    exit;
}

log_message("Users list requested by {$caller['email']}");
$stmt = $db->prepare('SELECT id, username, email, role, created_at FROM users');
$stmt->execute();
$users = $stmt->fetchAll();
echo json_encode(['users' => $users]);
