<?php

// Normalize base path for view usage.
$baseCandidate = defined('BASE_PATH') ? (string) BASE_PATH : '';
$BASE = isset($BASE) && $BASE !== '' ? (string) $BASE : $baseCandidate;
$BASE = rtrim($BASE, '/');
// Access control: only admin or superadmin may access the editor
$__editorUser = isset($currentUser) && is_array($currentUser) ? $currentUser : app_get_current_user();
$__editorRole = isset($__editorUser['role']) ? $__editorUser['role'] : (isset($role) ? $role : 'guest');
if ($__editorRole != 'superadmin') {
    if (!headers_sent()) {
        http_response_code(403);
    }
    // Keep #admin-root present so htmx subnav swaps don't remove the container
    echo '<h1>Administrace</h1>';
    echo '<div id="admin-root">';
    echo '<div role="alert">';
    echo '  <h2>Přístup odepřen</h2>';
    echo '  <p>Nemáte oprávnění pro zobrazení administrace.</p>';
    echo '</div>';
    echo '</div>';
    return;
}
?>
<h1>Administrace</h1>
<textarea
  id="admin-sql-query-input"
  style="width: 100%; height: 400px; font-family: monospace;"
  placeholder="SQL Query"
  ></textarea>
<div>
  <button
    id="admin-sql-query-run-button"
    type="button"
    hx-post="<?= htmlspecialchars($BASE) ?>/admin/sql"
    hx-include="#admin-sql-query-input"
    hx-target="#admin-messages"
    hx-swap="innerHTML"
  >Spustit SQL dotaz</button>
</div>