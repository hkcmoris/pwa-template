<?php

// Require login for Konfigurátor route (status already handled in index.php)
if (!isset($role) || $role === 'guest') {
    echo '<h1>Přístup odepřen</h1><p>Pro zobrazení konfigurátoru se prosím přihlaste.</p>';
    return;
}
?>

<h1>Konfigurátor</h1>
<p>Vítejte v konfigurátoru.</p>

<?php
    $breadcrumbs = __DIR__ . '/konfigurator/partials/breadcrumbs.php';
if (is_file($breadcrumbs)) {
    require $breadcrumbs;
} else {
    echo '<p>Navigační panel nebyl nalezen.</p>';
}

$options = __DIR__ . '/konfigurator/partials/options.php';
if (is_file($options)) {
    require $options;
} else {
    echo '<p>Panel s volbami nebyl nalezen.</p>';
}
