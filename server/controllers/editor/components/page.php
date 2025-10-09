<?php

use Components\Formatter;
use Components\Repository;

require_once __DIR__ . '/../../../bootstrap.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Vary: HX-Request, HX-Boosted, X-Requested-With, Cookie');
}

$user = app_get_current_user();
$role = $user['role'] ?? 'guest';
if (!in_array($role, ['admin', 'superadmin'], true)) {
    http_response_code(403);
    echo json_encode([
        'html' => '',
        'nextOffset' => 0,
        'hasMore' => false,
    ]);
    return;
}

$offsetParam = isset($_GET['offset']) ? (string) $_GET['offset'] : '0';
$offset = 0;
if ($offsetParam !== '' && preg_match('/^\d+$/', $offsetParam)) {
    $offset = (int) $offsetParam;
}

$componentPageSize = 50;
$pdo = get_db_connection();
$formatter = new Formatter();
$repository = new Repository($pdo, $formatter);
$componentsTree = $repository->fetchTree();
$componentsFlat = $formatter->flattenTree($componentsTree);
$total = count($componentsFlat);
$maxOffset = $total > 0 ? max(0, $total - 1) : 0;
$offset = max(0, min($offset, $maxOffset));
$componentsPage = array_slice($componentsFlat, $offset, $componentPageSize);

ob_start();
include __DIR__ . '/../../../views/editor/partials/components-chunk.php';
$chunk = ob_get_clean();

if ($chunk === false) {
    $chunk = '';
}

$nextOffset = $offset + count($componentsPage);
$hasMore = $nextOffset < $total;

echo json_encode([
    'html' => $chunk,
    'nextOffset' => $nextOffset,
    'hasMore' => $hasMore,
]);
