<?php

// Normalize base path for view usage.
$baseCandidate = defined('BASE_PATH') ? (string) BASE_PATH : '';
$BASE = isset($BASE) && $BASE !== '' ? (string) $BASE : $baseCandidate;
$BASE = rtrim($BASE, '/');
?>
<section class="error-page">
    <h1>Přístup zamítnut</h1>
    <p>Pro zobrazení této stránky nemáte dostatečná oprávnění.</p>
    <p>
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
    </p>
</section>
