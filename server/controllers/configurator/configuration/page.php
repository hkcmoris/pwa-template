<?php

use Configuration\Repository;

require_once __DIR__ . '/../../../bootstrap.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Vary: HX-Request, HX-Boosted, X-Requested-With, Cookie');
}

$user = app_get_current_user();
$role = $user['role'] ?? 'guest';
if (!in_array($role, ['user', 'admin', 'superadmin'], true)) {
    http_response_code(403);
    echo json_encode([
        'configurations' => [],
        'nextOffset' => 0,
        'hasMore' => false,
    ]);
    return;
}

$userId = isset($user['id']) ? (int) $user['id'] : 0;
$limitParam = isset($_GET['limit']) ? (string) $_GET['limit'] : '20';
$offsetParam = isset($_GET['offset']) ? (string) $_GET['offset'] : '0';

$limit = 20;
$offset = 0;
if ($limitParam !== '' && preg_match('/^\d+$/', $limitParam)) {
    $limit = max(1, (int) $limitParam);
}
if ($offsetParam !== '' && preg_match('/^\d+$/', $offsetParam)) {
    $offset = max(0, (int) $offsetParam);
}

$pdo = get_db_connection();
$repository = new Repository($pdo);
$configurations = $repository->fetch($limit, $offset, $userId);
$total = $repository->countByUser($userId);
$nextOffset = $offset + count($configurations);
$hasMore = $nextOffset < $total;

echo json_encode([
    'configurations' => $configurations,
    'nextOffset' => $nextOffset,
    'hasMore' => $hasMore,
]);
