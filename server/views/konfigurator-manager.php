<?php

use Configuration\Repository;
use Configuration\WizardRepository;

// Normalize base path for view usage.
$baseCandidate = defined('BASE_PATH') ? (string) BASE_PATH : '';
$BASE = isset($BASE) && $BASE !== '' ? (string) $BASE : $baseCandidate;
$BASE = rtrim($BASE, '/');

if (!isset($role) || $role === 'guest') {
    echo '<h1>Přístup odepřen</h1>'
      . '<p>Prosím přihlaste se pro zobrazení vašich konfigurací.</p>';
    return;
}

if (isset($_SERVER['HTTP_HX_REQUEST'])) {
    $managerCssHref = vite_asset_href('src/styles/konfigurator/manager.css', $isDevEnv ?? false, $BASE);
    if ($managerCssHref !== null) {
        ?>
<link
  rel="stylesheet"
  id="konfigurator-manager"
  href="<?= htmlspecialchars($managerCssHref, ENT_QUOTES, 'UTF-8') ?>"
  hx-swap-oob="true"
>
        <?php
    }
}

$userId = isset($currentUser['id']) ? (int) $currentUser['id'] : 0;
if ($userId <= 0) {
    echo '<h1>Přístup odepřen</h1>'
      . '<p>Nelze získat informace o vašem účtu.</p>';
    return;
}

$pdo = get_db_connection();
$repository = new Repository($pdo);
$wizardRepository = new WizardRepository($pdo);
/** @var array<int, array<string, mixed>> $configurations */
$configurations = $repository->fetch(null, 0, $userId);
/** @var array<int, array<string, mixed>> $drafts */
$drafts = $wizardRepository->findDraftsByUser($userId);
$latestDraftId = $drafts !== [] ? (int) $drafts[0]['id'] : null;
?>

<h1>Konfigurace</h1>
<div class="konfigurator-manager-actions">
  <button
    hx-get="<?= htmlspecialchars($BASE) ?>/konfigurator?new=1"
    hx-push-url="true"
    hx-target="#content"
    hx-select="#content"
    hx-swap="outerHTML"
  >Vytvořit novou konfiguraci</button>

  <?php if ($latestDraftId !== null) : ?>
    <button
      hx-get="<?= htmlspecialchars($BASE) ?>/konfigurator?draft=<?= htmlspecialchars((string) $latestDraftId) ?>"
      hx-push-url="true"
      hx-target="#content"
      hx-select="#content"
      hx-swap="outerHTML"
    >Pokračovat v posledním návrhu (#<?= htmlspecialchars((string) $latestDraftId) ?>)</button>
  <?php endif; ?>
</div>

<h2>Rozpracované návrhy</h2>
<div id="draft-form-errors" class="form-feedback hidden" role="status" aria-live="polite"></div>
<div id="draft-list-wrapper">
  <?php include __DIR__ . '/konfigurator/partials/drafts-list.php'; ?>
</div>

<h2>Dokončené konfigurace</h2>
<div id="configurations-form-errors" class="form-feedback hidden" role="status" aria-live="polite"></div>
<div id="configurations-list-wrapper">
  <?php include __DIR__ . '/konfigurator/partials/configurations-list.php'; ?>
</div>
