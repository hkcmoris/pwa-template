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
<section class="admin-transfer" data-island="admin">
  <h2>Import a export databáze</h2>
  <p>Vyberte, která data chcete importovat nebo exportovat.</p>
  <div class="admin-transfer-actions">
    <button type="button" class="admin-action" data-admin-modal="export">
      Export databáze
    </button>
    <button type="button" class="admin-action admin-action--danger" data-admin-modal="import">
      Import databáze
    </button>
  </div>
  <div id="admin-transfer-modal" class="admin-modal hidden" aria-hidden="true">
    <div class="admin-modal-overlay" data-admin-modal-close></div>
    <div class="admin-modal-panel" role="dialog" aria-modal="true" aria-labelledby="admin-transfer-title">
      <header class="admin-modal-header">
        <h3 id="admin-transfer-title">Export databáze</h3>
        <button type="button" class="admin-modal-close" data-admin-modal-close aria-label="Zavřít">×</button>
      </header>
      <form id="admin-transfer-form" class="admin-modal-body" enctype="multipart/form-data">
        <?= csrf_field(); ?>
        <fieldset data-admin-data>
          <legend>Vyberte data</legend>
          <label class="admin-checkbox">
            <input type="checkbox" name="definitions" checked>
            <span>Definice</span>
          </label>
          <label class="admin-checkbox">
            <input type="checkbox" name="components" checked>
            <span>Komponenty</span>
          </label>
          <label class="admin-checkbox">
            <input type="checkbox" name="prices" checked>
            <span>Ceníky</span>
          </label>
          <label class="admin-checkbox">
            <input type="checkbox" name="users" checked>
            <span>Uživatelé</span>
          </label>
        </fieldset>
        <fieldset class="admin-modal-file hidden" data-admin-file>
          <legend>SQL soubor</legend>
          <label class="admin-file">
            <input type="file" name="sql_file" accept=".sql">
            <span class="admin-file-label">Vyberte SQL soubor k importu</span>
          </label>
          <p class="admin-file-hint">
            Import podporuje pouze export z této aplikace.
          </p>
        </fieldset>
        <fieldset class="admin-modal-confirm hidden" data-admin-confirm>
          <legend>Potvrzení importu</legend>
          <label class="admin-checkbox">
            <input type="checkbox" name="confirm_overwrite">
            <span>Chci přepsat aktuální data importem.</span>
          </label>
        </fieldset>
        <div class="admin-modal-actions">
          <button type="button" class="admin-action" data-admin-modal-close>Storno</button>
          <button type="submit" class="admin-action admin-action--primary" data-admin-submit>
            Exportovat
          </button>
        </div>
      </form>
    </div>
  </div>
</section>
<textarea
  id="admin-sql-query-input"
  name="sql_query"
  placeholder="SQL Query"
  ></textarea>
<div>
  <button
    id="admin-sql-query-run-button"
    type="button"
    hx-post="<?= htmlspecialchars($BASE) ?>/admin/sql"
    hx-include="#admin-sql-query-input"
    hx-target="#admin-results"
    hx-swap="innerHTML"
  >Spustit SQL dotaz</button>
</div>
<div id="admin-messages" class="admin-feedback hidden" role="status" aria-live="polite"></div>
<div id="admin-results"></div>
