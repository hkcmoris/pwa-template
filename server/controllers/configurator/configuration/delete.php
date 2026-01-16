<?php

use Configuration\Repository;
use Configuration\ValidationException;

require_once __DIR__ . '/../../../bootstrap.php';

log_message('Configuration delete request received', 'INFO');
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
$configurationParam = $_POST['configuration_id'] ?? '';

if ($configurationParam === '' || !preg_match('/^\d+$/', (string) $configurationParam)) {
    http_response_code(422);
    echo '<div id="configurations-list-wrapper"></div>';
    echo '<div id="configurations-form-errors" hx-swap-oob="true" class="form-feedback form-feedback--error">' .
        'Vyberte prosím platnou konfiguraci.' .
        '</div>';
    return;
}

$configurationId = (int) $configurationParam;

$pdo = get_db_connection();
$repository = new Repository($pdo);
$configuration = $repository->find($configurationId);

if ($configuration === null) {
    http_response_code(404);
    echo '<div id="configurations-list-wrapper"></div>';
    echo '<div id="configurations-form-errors" hx-swap-oob="true" class="form-feedback form-feedback--error">' .
        'Konfiguraci se nepodařilo najít.' .
        '</div>';
    return;
}

if (!in_array($role, ['admin', 'superadmin'], true) && (int) $configuration['user_id'] !== $userId) {
    http_response_code(403);
    echo '<div id="configurations-list-wrapper"></div>';
    echo '<div id="configurations-form-errors" hx-swap-oob="true" class="form-feedback form-feedback--error">' .
        'Nemáte oprávnění smazat tuto konfiguraci.' .
        '</div>';
    return;
}

try {
    $repository->delete($configurationId);
    $configurations = $repository->fetch(null, 0, $userId);
    ob_start();
    include __DIR__ . '/../../../views/konfigurator/partials/configurations-list.php';
    $listHtml = ob_get_clean();
    if ($listHtml === false) {
        $listHtml = '';
    }
    echo '<div id="configurations-list-wrapper">' . $listHtml . '</div>';
    echo '<div id="configurations-form-errors" hx-swap-oob="true" class="form-feedback form-feedback--success" role="status" aria-live="polite">' .
        'Konfigurace byla smazána.' .
        '</div>';
} catch (ValidationException $e) {
    log_message('Configuration delete validation failed: ' . $e->getMessage(), 'WARN');
    http_response_code(422);
    echo '<div id="configurations-list-wrapper"></div>';
    echo '<div id="configurations-form-errors" hx-swap-oob="true" class="form-feedback form-feedback--error" role="status" aria-live="polite">' .
        htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') .
        '</div>';
} catch (Throwable $e) {
    log_message('Configuration delete failed: ' . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo '<div id="configurations-list-wrapper"></div>';
    echo '<div id="configurations-form-errors" hx-swap-oob="true" class="form-feedback form-feedback--error" role="status" aria-live="polite">' .
        'Konfiguraci se nepodařilo smazat.' .
        '</div>';
}
