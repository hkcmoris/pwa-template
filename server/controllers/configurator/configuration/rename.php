<?php

use Configuration\Repository;

require_once __DIR__ . '/../../../bootstrap.php';

log_message('Configuration rename request received', 'INFO');
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
    header('Vary: HX-Request, HX-Boosted, X-Requested-With, Cookie');
}

$user = app_get_current_user();
$role = $user['role'] ?? 'guest';
if (!in_array($role, ['user', 'admin', 'superadmin'], true)) {
    http_response_code(403);
    echo '<div id="configurations-form-errors" hx-swap-oob="true" class="form-feedback form-feedback--error">' .
        'Nemáte oprávnění spravovat konfigurace.' .
        '</div>';
    return;
}

$userId = isset($user['id']) ? (int) $user['id'] : 0;
if ($userId <= 0) {
    http_response_code(403);
    echo '<div id="configurations-form-errors" hx-swap-oob="true" class="form-feedback form-feedback--error">' .
        'Nelze určit přihlášeného uživatele.' .
        '</div>';
    return;
}

$configurationParam = $_POST['configuration_id'] ?? '';
$title = trim((string) ($_POST['title'] ?? ''));
if ($configurationParam === '' || !preg_match('/^\d+$/', (string) $configurationParam)) {
    http_response_code(422);
    echo '<div id="configurations-form-errors" hx-swap-oob="true" class="form-feedback form-feedback--error">' .
        'Vyberte platnou konfiguraci.' .
        '</div>';
    return;
}

if ($title === '' || mb_strlen($title) > 191) {
    http_response_code(422);
    echo '<div id="configurations-form-errors" hx-swap-oob="true" class="form-feedback form-feedback--error">' .
        'Zadejte název konfigurace (1–191 znaků).' .
        '</div>';
    return;
}

$configurationId = (int) $configurationParam;
$pdo = get_db_connection();
$repository = new Repository($pdo);
$renderList = static function (Repository $repository, int $userId): string {
    $configurations = $repository->fetch(null, 0, $userId);
    ob_start();
    include __DIR__ . '/../../../views/konfigurator/partials/configurations-list.php';
    $listHtml = ob_get_clean();
    if ($listHtml === false) {
        return '';
    }
    return $listHtml;
};

$configurationStmt = $pdo->prepare(
    'SELECT id, user_id, status FROM configurations WHERE id = :id LIMIT 1'
);
$configurationStmt->bindValue(':id', $configurationId, PDO::PARAM_INT);
$configurationStmt->execute();
/** @var array{user_id: int|string|null, status: string|null}|false $configuration */
$configuration = $configurationStmt->fetch(PDO::FETCH_ASSOC);

if ($configuration === false) {
    http_response_code(404);
    $listHtml = $renderList($repository, $userId);
    echo '<div id="configurations-list-wrapper" hx-swap-oob="true">' . $listHtml . '</div>';
    echo '<div id="configurations-form-errors" hx-swap-oob="true" class="form-feedback form-feedback--error">' .
        'Konfiguraci se nepodařilo najít.' .
        '</div>';
    return;
}

if (!in_array($role, ['admin', 'superadmin'], true) && (int) $configuration['user_id'] !== $userId) {
    http_response_code(403);
    $listHtml = $renderList($repository, $userId);
    echo '<div id="configurations-list-wrapper" hx-swap-oob="true">' . $listHtml . '</div>';
    echo '<div id="configurations-form-errors" hx-swap-oob="true" class="form-feedback form-feedback--error">' .
        'Nemáte oprávnění upravit tuto konfiguraci.' .
        '</div>';
    return;
}

if (($configuration['status'] ?? 'draft') === 'draft') {
    http_response_code(422);
    $listHtml = $renderList($repository, $userId);
    echo '<div id="configurations-list-wrapper" hx-swap-oob="true">' . $listHtml . '</div>';
    echo '<div id="configurations-form-errors" hx-swap-oob="true" class="form-feedback form-feedback--error">' .
        'Rozpracovaný návrh přejmenujte v sekci návrhů.' .
        '</div>';
    return;
}

try {
    $renameStmt = $pdo->prepare(
        'UPDATE configurations
         SET title = :title, updated_at = CURRENT_TIMESTAMP
         WHERE id = :id'
    );
    $renameStmt->bindValue(':id', $configurationId, PDO::PARAM_INT);
    $renameStmt->bindValue(':title', $title);
    $renameStmt->execute();

    $listHtml = $renderList($repository, $userId);
    echo '<div id="configurations-list-wrapper" hx-swap-oob="true">' . $listHtml . '</div>';
    echo '<div id="configurations-form-errors" ' .
        'hx-swap-oob="true" ' .
        'class="form-feedback form-feedback--success" ' .
        'role="status" ' .
        'aria-live="polite">' .
        'Konfigurace byla přejmenována.</div>';
} catch (Throwable $e) {
    log_message('Configuration rename failed: ' . $e->getMessage(), 'ERROR');
    http_response_code(500);
    $listHtml = $renderList($repository, $userId);
    echo '<div id="configurations-list-wrapper" hx-swap-oob="true">' . $listHtml . '</div>';
    echo '<div id="configurations-form-errors" hx-swap-oob="true" class="form-feedback form-feedback--error">' .
        'Konfiguraci se nepodařilo přejmenovat.' .
        '</div>';
}
