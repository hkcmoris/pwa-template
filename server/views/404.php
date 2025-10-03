<?php

// Normalize base path for view usage.
$baseCandidate = defined('BASE_PATH') ? (string) BASE_PATH : '';
$BASE = isset($BASE) && $BASE !== '' ? (string) $BASE : $baseCandidate;
$BASE = rtrim($BASE, '/');
?>
<div>
    <h1>404 – Stránka nenalezena</h1>
    <p>Omlouváme se, požadovaná stránka nebyla nalezena.</p>
    <a 
        href="<?= htmlspecialchars($BASE) ?>/" 
        hx-get="<?= htmlspecialchars($BASE) ?>/" 
        hx-push-url="true" 
        hx-target="#content" 
        hx-select="#content" 
        hx-swap="outerHTML"
    >
        Zpět na domovskou stránku
    </a>
</div>
