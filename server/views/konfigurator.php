<?php
// Require login for Konfigurátor route
if (!isset($role) || $role === 'guest') {
  http_response_code(403);
  echo '<h1>Přístup odepřen</h1><p>Pro zobrazení konfigurátoru se prosím přihlaste.</p>';
  return;
}
?>

<h1>Konfigurátor</h1>
<p>Vítejte v konfigurátoru.</p>
