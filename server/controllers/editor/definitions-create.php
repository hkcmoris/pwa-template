<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/logger.php';
require_once __DIR__ . '/../../lib/definitions.php';
require_once __DIR__ . '/../../views/editor/definitions-response.php';
log_message('Definitions create request received', 'INFO');
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
    header('Vary: HX-Request, HX-Boosted, X-Requested-With, Cookie');
}

$user = app_get_current_user();
$role = $user['role'] ?? 'guest';
if (!in_array($role, ['admin', 'superadmin'], true)) {
    http_response_code(403);
    echo '<div id="definitions-list"></div>';
    echo '<div id="definition-form-errors" hx-swap-oob="true" class="form-feedback form-feedback--error">' .
        'Nemáte oprávnění spravovat definice.' .
        '</div>';
    return;
}

$pdo = get_db_connection();
$title = isset($_POST['title']) ? trim((string) $_POST['title']) : '';
$parentParam = null;
if (isset($_POST['parent_id'])) {
    $rawParentValue = $_POST['parent_id'];
    if (is_scalar($rawParentValue)) {
        $trimmedParent = trim((string) $rawParentValue);
        if ($trimmedParent !== '') {
            $parentParam = $trimmedParent;
        }
    }
}
$positionParam = isset($_POST['position']) ? trim((string) $_POST['position']) : '';
$errors = [];
$parentId = null;
if ($title === '') {
    log_message('Title is empty', 'ERROR');
    $errors[] = 'Vyplňte prosím název definice.';
} elseif (mb_strlen($title, 'UTF-8') > 191) {
    log_message('Title exceeds maximum length of 191 characters', 'ERROR');
    $errors[] = 'Název může mít maximálně 191 znaků.';
}

if ($parentParam !== null) {
    if (!preg_match('/^\d+$/', (string) $parentParam)) {
        log_message('Parent ID is not a valid integer: ' . var_export($parentParam, true), 'ERROR');
        $errors[] = 'Vybraný rodič není platný.';
    } else {
        $parentId = (int) $parentParam;
        if ($parentId <= 0 || !definitions_parent_exists($pdo, $parentId)) {
            log_message('Parent ID does not exist: ' . $parentId, 'ERROR');
            $errors[] = 'Vybraná rodičovská definice neexistuje.';
        }
    }
}

$position = null;
if ($positionParam !== '') {
    if (!preg_match('/^\d+$/', $positionParam)) {
        log_message('Position is not a valid non-negative integer: ' . var_export($positionParam, true), 'ERROR');
        $errors[] = 'Pozice musí být nezáporné číslo.';
    } else {
        $position = (int) $positionParam;
    }
}

if (empty($errors)) {
    if ($position === null) {
        $position = definitions_next_position($pdo, $parentId);
    }
    definitions_create($pdo, $title, $parentId, $position);
    http_response_code(201);
    log_message(
        'Definition created successfully with title=' . $title .
        ', parentId=' . var_export($parentId, true) .
        ', position=' . $position,
        'INFO'
    );
    definitions_render_fragments($pdo, [
        'message' => 'Definice byla uložena.',
        'message_type' => 'success',
    ]);
} else {
    http_response_code(422);
    $escapedErrors = array_map(function ($msg) {

        return htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
    }, $errors);
    definitions_render_fragments($pdo, [
        'message' => implode(' ', $escapedErrors),
        'message_type' => 'error',
    ]);
}
