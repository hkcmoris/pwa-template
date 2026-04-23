<?php

declare(strict_types=1);

use Configuration\ConfigurationWizard;

require_once __DIR__ . '/../../../bootstrap.php';

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
    header('Vary: HX-Request, HX-Boosted, X-Requested-With, Cookie');
}

$user = app_get_current_user();
$role = $user['role'] ?? 'guest';
if (!in_array($role, ['user', 'admin', 'superadmin'], true)) {
    http_response_code(403);
    echo '<div id="konfigurator-wizard"></div>';
    return;
}

$userId = isset($user['id']) ? (int) $user['id'] : 0;
if ($userId <= 0) {
    http_response_code(403);
    echo '<div id="konfigurator-wizard"></div>';
    return;
}

$draftId = isset($_POST['draft_id']) ? (int) $_POST['draft_id'] : null;
if ($draftId !== null && $draftId <= 0) {
    $draftId = null;
}

$rawIds = $_POST['component_ids'] ?? [];
if (!is_array($rawIds)) {
    $rawIds = [$rawIds];
}
$componentIds = [];
foreach ($rawIds as $rawId) {
    $id = (int) $rawId;
    if ($id > 0 && !in_array($id, $componentIds, true)) {
        $componentIds[] = $id;
    }
}

try {
    $wizard = ConfigurationWizard::loadOrCreateDraft($userId, $draftId);
    $wizard->selectMultipleComponents($componentIds);
    $wizard->autoSelectSingleOptions();
    $BASE = rtrim((string) (defined('BASE_PATH') ? BASE_PATH : ''), '/');
    require __DIR__ . '/../../../views/konfigurator/partials/wizard.php';
} catch (Throwable $e) {
    log_message('Wizard multi-select failed: ' . $e->getMessage(), 'ERROR');
    http_response_code(400);
    $wizardError = 'Výběr možností nebyl uložen. Zkuste to prosím znovu.';
    $wizard = ConfigurationWizard::loadOrCreateDraft($userId, $draftId);
    $wizard->autoSelectSingleOptions();
    $BASE = rtrim((string) (defined('BASE_PATH') ? BASE_PATH : ''), '/');
    require __DIR__ . '/../../../views/konfigurator/partials/wizard.php';
}
