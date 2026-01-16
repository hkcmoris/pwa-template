<?php

use Configuration\Repository;

require_once __DIR__ . '/../../../bootstrap.php';

log_message('Configuration create request received', 'INFO');
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
    header('Vary: HX-Request, HX-Boosted, X-Requested-With, Cookie');
}

$user = app_get_current_user();
$role = $user['role'] ?? 'guest';
if (!in_array($role, ['user', 'admin', 'superadmin'], true)) {
    http_response_code(403);
    echo '<div id="configurations-list-wrapper"></div>';
    echo '<div id="configurations-form-errors" hx-swap-oob="true" class="form-feedback form-feedback--error">' .
        'Nemáte oprávnění spravovat konfigurace.' .
        '</div>';
    return;
}

$userId = isset($user['id']) ? (int) $user['id'] : 0;
if ($userId <= 0) {
    http_response_code(403);
    echo '<div id="configurations-list-wrapper"></div>';
    echo '<div id="configurations-form-errors" hx-swap-oob="true" class="form-feedback form-feedback--error">' .
        'Nelze určit uživatele pro uložení konfigurace.' .
        '</div>';
    return;
}

$pdo = get_db_connection();
$repository = new Repository($pdo);

$errors = [];

if (!empty($errors)) {
    http_response_code(422);
    echo '<div id="configurations-list-wrapper"></div>';
    echo '<div id="configurations-form-errors" hx-swap-oob="true" class="form-feedback form-feedback--error">' .
        htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8') .
        '</div>';
    return;
}

try {
    $repository->create($userId);
    http_response_code(201);
    $configurations = $repository->fetch(null, 0, $userId);
    ob_start();
    include __DIR__ . '/../../../views/konfigurator/partials/configurations-list.php';
    $listHtml = ob_get_clean();
    if ($listHtml === false) {
        $listHtml = '';
    }
    echo '<div id="configurations-list-wrapper">' . $listHtml . '</div>';
    echo '<div id="configurations-form-errors" hx-swap-oob="true" class="form-feedback form-feedback--success" role="status" aria-live="polite">' .
        'Konfigurace byla uložena.' .
        '</div>';
} catch (Throwable $e) {
    log_message('Configuration creation failed: ' . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo '<div id="configurations-list-wrapper"></div>';
    echo '<div id="configurations-form-errors" hx-swap-oob="true" class="form-feedback form-feedback--error" role="status" aria-live="polite">' .
        'Konfiguraci se nepodařilo uložit.' .
        '</div>';
}
