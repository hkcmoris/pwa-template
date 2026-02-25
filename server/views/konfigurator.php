<?php

use Configuration\ConfigurationWizard;

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

$componentStyleEntry = 'src/styles/konfigurator/configuration-wizard.css';
if (isset($_SERVER['HTTP_HX_REQUEST'])) {
    $componentCssHref = vite_asset_href($componentStyleEntry, $isDevEnv ?? false, $BASE);
    if ($componentCssHref !== null) {
        ?>
        <link
            rel="stylesheet"
            id="konfigurator-configuration-wizard"
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

$currentUser = isset($currentUser) && is_array($currentUser) ? $currentUser : app_get_current_user();
$userId = isset($currentUser['id']) ? (int) $currentUser['id'] : 0;
if ($userId <= 0) {
    echo '<p>Nelze získat informace o vašem účtu.</p>';
    return;
}

$requestedDraftId = isset($_GET['draft']) ? (int) $_GET['draft'] : null;
if ($requestedDraftId !== null && $requestedDraftId <= 0) {
    $requestedDraftId = null;
}
$forceCreateNewDraft = isset($_GET['new']) && $_GET['new'] === '1';

$wizard = ConfigurationWizard::loadOrCreateDraft($userId, $requestedDraftId, $forceCreateNewDraft);
$wizard->autoSelectSingleOptions();
$wizardPartial = __DIR__ . '/konfigurator/partials/wizard.php';
if (is_file($wizardPartial)) {
    require $wizardPartial;
} else {
    echo '<p>Průvodce konfigurací nebyl nalezen.</p>';
}
