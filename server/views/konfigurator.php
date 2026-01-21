<?php

// Require login for Konfigurátor route (status already handled in index.php)
if (!isset($role) || $role === 'guest') {
    echo '<h1>Přístup odepřen</h1><p>Pro zobrazení konfigurátoru se prosím přihlaste.</p>';
    return;
}

// Normalize base path for view usage.
$baseCandidate = defined('BASE_PATH') ? (string) BASE_PATH : '';
$BASE = isset($BASE) && $BASE !== '' ? (string) $BASE : $baseCandidate;
$BASE = rtrim($BASE, '/');

$componentStyleEntry = 'src/styles/konfigurator/breadcrumbs.css';
if (isset($_SERVER['HTTP_HX_REQUEST'])) {
    $componentCssHref = vite_asset_href($componentStyleEntry, $isDevEnv ?? false, $BASE);
    if ($componentCssHref !== null) {
        ?>
        <link
            rel="stylesheet"
            id="konfigurator-breadcrumbs"
            href="<?= htmlspecialchars($componentCssHref, ENT_QUOTES, 'UTF-8') ?>"
            hx-swap-oob="true">
        <?php
    }
}

$componentStyleEntry = 'src/styles/konfigurator/component-options.css';
if (isset($_SERVER['HTTP_HX_REQUEST'])) {
    $componentCssHref = vite_asset_href($componentStyleEntry, $isDevEnv ?? false, $BASE);
    if ($componentCssHref !== null) {
        ?>
        <link
            rel="stylesheet"
            id="konfigurator-component-options"
            href="<?= htmlspecialchars($componentCssHref, ENT_QUOTES, 'UTF-8') ?>"
            hx-swap-oob="true">
        <?php
    }
}

$breadcrumbs = __DIR__ . '/konfigurator/partials/breadcrumbs.php';
if (is_file($breadcrumbs)) {
    require $breadcrumbs;
} else {
    echo '<p>Navigační panel nebyl nalezen.</p>';
}

$options = __DIR__ . '/konfigurator/partials/component-options.php';
if (is_file($options)) {
    require $options;
} else {
    echo '<p>Panel s volbami nebyl nalezen.</p>';
}
