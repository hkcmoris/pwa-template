<?php

use Configuration\Repository;

// Normalize base path for view usage.
$baseCandidate = defined('BASE_PATH') ? (string) BASE_PATH : '';
$BASE = isset($BASE) && $BASE !== '' ? (string) $BASE : $baseCandidate;
$BASE = rtrim($BASE, '/');

if (!isset($role) || $role === 'guest') {
    echo '<h1>Access denied</h1><p>Please sign in to view your configurations.</p>';
    return;
}

$userId = isset($currentUser['id']) ? (int) $currentUser['id'] : 0;
if ($userId <= 0) {
    echo '<h1>Access denied</h1><p>Unable to resolve your account.</p>';
    return;
}

$pdo = get_db_connection();
$repository = new Repository($pdo);
/** @var array<int, array<string, mixed>> $configurations */
$configurations = $repository->fetch(null, 0, $userId);
?>

<h1>Konfigurace</h1>
<button
  hx-get="<?= htmlspecialchars($BASE) ?>/konfigurator"
  hx-push-url="true"
  hx-target="#content"
  hx-select="#content"
  hx-swap="outerHTML"
>Vytvořit novou konfiguraci</button>
<div id="configurations-form-errors" class="form-feedback hidden" role="status" aria-live="polite"></div>
<div id="configurations-list-wrapper">
  <?php include __DIR__ . '/konfigurator/partials/configurations-list.php'; ?>
</div>
