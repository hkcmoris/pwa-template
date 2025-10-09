<?php

use Components\Formatter;
use Components\Repository;
use Definitions\Formatter as DefinitionsFormatter;
use Definitions\Repository as DefinitionsRepository;
use Editor\ComponentPresenter;

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

$pdo = get_db_connection();
$formatter = new Formatter();
$definitionsFormatter = new DefinitionsFormatter();
$definitionsRepository = new DefinitionsRepository($pdo);
$repository = new Repository($pdo, $formatter, $definitionsRepository);
$presenter = new ComponentPresenter($repository, $formatter, $definitionsRepository, $definitionsFormatter);

$listData = $presenter->presentPage($offset);
$componentsPage = $listData['componentsPage'];

ob_start();
include __DIR__ . '/../../../views/editor/partials/components-chunk.php';
$chunk = ob_get_clean();

if ($chunk === false) {
    $chunk = '';
}

echo json_encode([
    'html' => $chunk,
    'nextOffset' => $listData['nextOffset'],
    'hasMore' => $listData['hasMore'],
]);
