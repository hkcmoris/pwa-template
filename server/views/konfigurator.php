<?php

// Require login for Konfigurátor route (status already handled in index.php)
if (!isset($role) || $role === 'guest') {
    echo '<h1>Přístup odepřen</h1><p>Pro zobrazení konfigurátoru se prosím přihlaste.</p>';
    return;
}
?>

<h1>Konfigurátor</h1>
<p>Vítejte v konfigurátoru.</p>
