<?php

declare(strict_types=1);

use Configuration\ConfigurationWizard;
use Configuration\WizardRepository;

require_once __DIR__ . '/../../../bootstrap.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Vary: HX-Request, HX-Boosted, X-Requested-With, Cookie');
}

$user = app_get_current_user();
$role = $user['role'] ?? 'guest';
if (!in_array($role, ['user', 'admin', 'superadmin'], true)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Nemáte oprávnění dokončit konfiguraci.',
    ]);
    return;
}

$userId = isset($user['id']) ? (int) $user['id'] : 0;
if ($userId <= 0) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Nelze určit uživatele pro dokončení konfigurace.',
    ]);
    return;
}

$draftId = isset($_POST['draft_id']) ? (int) $_POST['draft_id'] : 0;
if ($draftId <= 0) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Vyberte prosím platný návrh konfigurace.',
    ]);
    return;
}

try {
    $wizard = ConfigurationWizard::loadOrCreateDraft($userId, $draftId);
    if ($wizard->getConfigurationId() !== $draftId) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Požadovaný návrh konfigurace nebyl nalezen.',
        ]);
        return;
    }

    $summary = $wizard->buildSummary();
    $selectedPath = isset($summary['selected_path']) && is_array($summary['selected_path'])
        ? $summary['selected_path']
        : [];
    if ($selectedPath === [] || empty($summary['is_complete'])) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Konfigurace ještě není kompletní. Dokončete všechny kroky.',
        ]);
        return;
    }

    $repository = new WizardRepository();
    $completed = $repository->completeDraft($draftId, $userId);
    if (!$completed) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Konfiguraci se nepodařilo dokončit. Zkuste to prosím znovu.',
        ]);
        return;
    }

    $basePath = defined('BASE_PATH') ? rtrim((string) BASE_PATH, '/') : '';
    $redirectUrl = ($basePath !== '' ? $basePath : '') . '/konfigurator-manager'
        . '?completed_configuration_id=' . rawurlencode((string) $draftId);

    echo json_encode([
        'success' => true,
        'message' => 'Konfigurace byla úspěšně dokončena.',
        'configuration_id' => $draftId,
        'redirect_url' => $redirectUrl,
    ]);
} catch (Throwable $e) {
    log_message('Wizard finish failed: ' . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Dokončení konfigurace se nezdařilo.',
    ]);
}
