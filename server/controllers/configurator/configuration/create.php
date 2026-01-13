<?php

use Configuration\Formatter;
use Configuration\Repository;
use Components\Repository as ComponentsRepository;

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

$pdo = get_db_connection();

$formatter = new Formatter();
$componentsRepository = new ComponentsRepository($pdo);
$repository = new Repository($pdo, $formatter, $componentsRepository);
$errors = [];

if (!empty($errors)) {
    http_response_code(422);
    $viewModel = $presenter->presentInitial([
        'message' => implode(' ', $errors),
        'message_type' => 'error',
    ]);
    // components_render_fragments($viewModel);
    return;
}

try {
    $repository->create((int) $user['id']);
    http_response_code(201);
    $viewModel = $presenter->presentInitial([
        'message' => 'Konfigurace byla uložena.',
        'message_type' => 'success',
    ]);
    // components_render_fragments($viewModel);
} catch (Throwable $e) {
    log_message('Configuration creation failed: ' . $e->getMessage(), 'ERROR');
    http_response_code(500);
    $viewModel = $presenter->presentInitial([
        'message' => 'Konfiguraci se nepodařilo uložit.',
        'message_type' => 'error',
    ]);
    // components_render_fragments($viewModel);
}