<?php

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../lib/auth.php';
require_once __DIR__ . '/../../../lib/db.php';
require_once __DIR__ . '/../../../lib/definitions.php';
require_once __DIR__ . '/../../../views/editor/definitions-response.php';
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
if (!preg_match('/^\d+$/', (string) $idParam)) {
    http_response_code(422);
    definitions_render_fragments($pdo, [
        'message' => 'Neplatné ID definice.',
        'message_type' => 'error',
    ]);
    return;
}

$id = (int) $idParam;
try {
    definitions_delete($pdo, $id);
    definitions_render_fragments($pdo, [
        'message' => 'Definice byla odstraněna.',
        'message_type' => 'success',
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    definitions_render_fragments($pdo, [
        'message' => 'Odstranění se nezdařilo.',
        'message_type' => 'error',
    ]);
}
