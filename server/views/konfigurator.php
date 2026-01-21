<?php

// Require login for Konfigurátor route (status already handled in index.php)
if (!isset($role) || $role === 'guest') {
    echo '<h1>Přístup odepřen</h1><p>Pro zobrazení konfigurátoru se prosím přihlaste.</p>';
    return;
}
?>

<?php
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
