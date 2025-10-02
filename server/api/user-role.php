<?php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/cors.php';

header('Content-Type: application/json');
$jwtSecret = config_jwt_secret();

$token = $_COOKIE['token'] ?? '';
$payload = verify_jwt($token, $jwtSecret);
if (!$payload) {
    http_response_code(401);
    echo json_encode(['error' => 'NeautorizovĂˇno']);
    exit;
}

$db = get_db_connection();
// Verify caller is superadmin
$stmt = $db->prepare('SELECT role, email FROM users WHERE id = :id');
$stmt->execute([':id' => (int)$payload['sub']]);
$caller = $stmt->fetch();
if (!$caller || ($caller['role'] ?? 'user') !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['error' => 'ZakĂˇzĂˇno']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$id = isset($input['id']) ? (int)$input['id'] : 0;
$role = isset($input['role']) ? (string)$input['role'] : '';

$allowed = ['user','admin','superadmin'];
if ($id <= 0 || !in_array($role, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'NeplatnĂ˝ vstup']);
    exit;
}

// Prevent self demotion: superadmin cannot change their own role to anything else
if ($id === (int)($payload['sub'] ?? 0) && $role !== 'superadmin') {
    http_response_code(400);
    echo json_encode(['error' => 'Nelze zmÄ›nit vlastnĂ­ roli']);
    exit;
}

try {
    $u = $db->prepare('UPDATE users SET role = :role WHERE id = :id');
    $u->execute([':role' => $role, ':id' => $id]);

    $s = $db->prepare('SELECT id, username, email, role, created_at FROM users WHERE id = :id');
    $s->execute([':id' => $id]);
    $user = $s->fetch();
    echo json_encode(['ok' => true, 'user' => $user]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Chyba databĂˇze']);
}
