<?php
// Normalize base path for view usage.
$baseCandidate = defined('BASE_PATH') ? BASE_PATH : '';
$BASE = isset($BASE) && is_string($BASE) ? $BASE : (is_string($baseCandidate) ? $baseCandidate : '');
$BASE = rtrim($BASE, '/');
?>
<div>
    <h1>404 – Stránka nenalezena</h1>
    <p>Omlouváme se, požadovaná stránka nebyla nalezena.</p>
    <a href="<?= htmlspecialchars($BASE) ?>/" hx-get="<?= htmlspecialchars($BASE) ?>/" hx-push-url="true" hx-target="#content" hx-select="#content" hx-swap="outerHTML">Zpět na domovskou stránku</a>
</div>
