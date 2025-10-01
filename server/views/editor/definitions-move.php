<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/definitions.php';
require_once __DIR__ . '/definitions-response.php';
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
    header('Vary: HX-Request, HX-Boosted, X-Requested-With, Cookie');
}

$user = app_get_current_user();
$role = $user['role'] ?? 'guest';
if (!in_array($role, ['admin', 'superadmin'], true)) {
    http_response_code(403);
    echo '<div id="definitions-list"></div>';
    echo '<div id="definition-form-errors"'
        . ' hx-swap-oob="true"'
        . ' class="form-feedback form-feedback--error"'
        . '>'
        . 'Nemáte oprávnění spravovat definice.'
        . '</div>';
    return;
}

$pdo = get_db_connection();
$idParam = $_POST['id'] ?? '';
$parentParam = $_POST['parent_id'] ?? '';
$positionParam = $_POST['position'] ?? '';
if (!preg_match('/^\d+$/', (string) $idParam)) {
    http_response_code(422);
    definitions_render_fragments($pdo, [
        'message' => 'Neplatné ID definice.',
        'message_type' => 'error',
    ]);
    return;
}

$id = (int) $idParam;
$parentId = null;
if ($parentParam !== '' && $parentParam !== null) {
    if (!preg_match('/^\d+$/', (string) $parentParam)) {
        http_response_code(422);
        definitions_render_fragments($pdo, [
            'message' => 'Neplatný rodič.',
            'message_type' => 'error',
        ]);
        return;
    }
    $parentId = (int) $parentParam;
}

if (!preg_match('/^\d+$/', (string) $positionParam)) {
    http_response_code(422);
    definitions_render_fragments($pdo, [
        'message' => 'Neplatná pozice.',
        'message_type' => 'error',
    ]);
    return;
}

$position = (int) $positionParam;
try {
    definitions_move($pdo, $id, $parentId, $position);
    print($id . ': [' . $parentId . ', ' . $position . ']');
    definitions_render_fragments($pdo, [
        'message' => 'Definice byla přesunuta.',
        'message_type' => 'success',
    ]);
} catch (RuntimeException $e) {
    http_response_code(422);
    definitions_render_fragments($pdo, [
        'message' => $e->getMessage(),
        'message_type' => 'error',
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    definitions_render_fragments($pdo, [
        'message' => 'Přesun se nezdařil.',
        'message_type' => 'error',
    ]);
}
