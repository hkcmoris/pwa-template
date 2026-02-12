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

$selectionId = isset($_POST['selection_id']) ? (int) $_POST['selection_id'] : 0;
$draftId = isset($_POST['draft_id']) ? (int) $_POST['draft_id'] : null;
if ($draftId !== null && $draftId <= 0) {
    $draftId = null;
}

if ($selectionId <= 0) {
    http_response_code(422);
    $wizardError = 'Požadovaný krok není platný.';
    $wizard = ConfigurationWizard::loadOrCreateDraft($userId, $draftId);
    $BASE = rtrim((string) (defined('BASE_PATH') ? BASE_PATH : ''), '/');
    require __DIR__ . '/../../../views/konfigurator/partials/wizard.php';
    return;
}

try {
    $wizard = ConfigurationWizard::loadOrCreateDraft($userId, $draftId);
    $wizard->goToStep($selectionId);
    $BASE = rtrim((string) (defined('BASE_PATH') ? BASE_PATH : ''), '/');
    require __DIR__ . '/../../../views/konfigurator/partials/wizard.php';
} catch (Throwable $e) {
    log_message('Wizard goto-step failed: ' . $e->getMessage(), 'ERROR');
    http_response_code(400);
    $wizardError = 'Přechod na vybraný krok se nezdařil.';
    $wizard = ConfigurationWizard::loadOrCreateDraft($userId, $draftId);
    $BASE = rtrim((string) (defined('BASE_PATH') ? BASE_PATH : ''), '/');
    require __DIR__ . '/../../../views/konfigurator/partials/wizard.php';
}
