<?php

use Configuration\WizardRepository;

require_once __DIR__ . '/../../../bootstrap.php';

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
    header('Vary: HX-Request, HX-Boosted, X-Requested-With, Cookie');
}

$user = app_get_current_user();
$role = $user['role'] ?? 'guest';
if (!in_array($role, ['user', 'admin', 'superadmin'], true)) {
    http_response_code(403);
    echo '<div id="draft-form-errors" hx-swap-oob="true" class="form-feedback form-feedback--error">' .
        'Nemáte oprávnění spravovat návrhy.</div>';
    return;
}

$userId = isset($user['id']) ? (int) $user['id'] : 0;
if ($userId <= 0) {
    http_response_code(403);
    echo '<div id="draft-form-errors" hx-swap-oob="true" class="form-feedback form-feedback--error">' .
        'Nelze určit přihlášeného uživatele.</div>';
    return;
}

$draftParam = $_POST['draft_id'] ?? '';
$title = trim((string) ($_POST['title'] ?? ''));
if ($draftParam === '' || !preg_match('/^\d+$/', (string) $draftParam)) {
    http_response_code(422);
    echo '<div id="draft-form-errors" hx-swap-oob="true" class="form-feedback form-feedback--error">' .
        'Vyberte platný návrh.</div>';
    return;
}
if ($title === '' || mb_strlen($title) > 191) {
    http_response_code(422);
    echo '<div id="draft-form-errors" hx-swap-oob="true" class="form-feedback form-feedback--error">' .
        'Zadejte název návrhu (1–191 znaků).</div>';
    return;
}

$draftId = (int) $draftParam;
$pdo = get_db_connection();
$wizardRepository = new WizardRepository($pdo);

try {
    if (!$wizardRepository->renameDraft($draftId, $userId, $title)) {
        http_response_code(404);
        echo '<div id="draft-form-errors" hx-swap-oob="true" class="form-feedback form-feedback--error">' .
        'Návrh se nepodařilo najít.</div>';
        return;
    }

    $drafts = $wizardRepository->findDraftsByUser($userId);
    ob_start();
    include __DIR__ . '/../../../views/konfigurator/partials/drafts-list.php';
    $draftListHtml = ob_get_clean();

    echo '<div id="draft-list-wrapper" hx-swap-oob="true">' .
        ($draftListHtml === false ? '' : $draftListHtml) .
        '</div>';
    echo '<div id="draft-form-errors" ' .
        'hx-swap-oob="true" ' .
        'class="form-feedback form-feedback--success" ' .
        'role="status" ' .
        'aria-live="polite">' .
        'Návrh byl přejmenován.</div>';
} catch (Throwable $e) {
    log_message('Draft rename failed: ' . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo '<div id="draft-form-errors" hx-swap-oob="true" class="form-feedback form-feedback--error">' .
        'Návrh se nepodařilo přejmenovat.</div>';
}
