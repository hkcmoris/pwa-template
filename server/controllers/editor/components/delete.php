<?php

use Components\Formatter;
use Components\Repository;

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../../../views/editor/components-response.php';

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
    header('Vary: HX-Request, HX-Boosted, X-Requested-With, Cookie');
}

$user = app_get_current_user();
$role = $user['role'] ?? 'guest';
if (!in_array($role, ['admin', 'superadmin'], true)) {
    http_response_code(403);
    echo '<div id="components-list-wrapper"></div>';
    echo '<div id="component-form-errors" hx-swap-oob="true" class="form-feedback form-feedback--error">' .
        'Nemate opravneni spravovat komponenty.' .
        '</div>';
    return;
}

$componentParam = $_POST['component_id'] ?? '';
if (!preg_match('/^\d+$/', (string) $componentParam)) {
    http_response_code(422);
    $pdo = get_db_connection();
    components_render_fragments($pdo, [
        'message' => 'Neplatne ID komponenty.',
        'message_type' => 'error',
    ]);
    return;
}

$componentId = (int) $componentParam;
$pdo = get_db_connection();
$formatter = new Formatter();
$repository = new Repository($pdo, $formatter);

try {
    $repository->delete($componentId);
    components_render_fragments($pdo, [
        'message' => 'Komponenta byla odstranena.',
        'message_type' => 'success',
    ]);
} catch (RuntimeException $e) {
    http_response_code(404);
    components_render_fragments($pdo, [
        'message' => 'Komponentu se nepodarilo najit.',
        'message_type' => 'error',
    ]);
} catch (Throwable $e) {
    log_message('Component delete failed: ' . $e->getMessage(), 'ERROR');
    http_response_code(500);
    components_render_fragments($pdo, [
        'message' => 'Odstraneni komponenty se nepodarilo.',
        'message_type' => 'error',
    ]);
}
