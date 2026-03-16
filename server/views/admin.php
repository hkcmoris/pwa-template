<?php

require_once __DIR__ . '/../lib/Administration/Repository.php';

use Administration\Repository as AdministrationRepository;

// Normalize base path for view usage.
$baseCandidate = defined('BASE_PATH') ? (string) BASE_PATH : '';
$BASE = isset($BASE) && $BASE !== '' ? (string) $BASE : $baseCandidate;
$BASE = rtrim($BASE, '/');

$componentStyleEntry = 'src/styles/admin.css';
if (isset($_SERVER['HTTP_HX_REQUEST'])) {
    $componentCssHref = vite_asset_href($componentStyleEntry, $isDevEnv ?? false, $BASE);
    if ($componentCssHref !== null) {
        ?>
        <link
            rel="stylesheet"
            id="admin"
            href="<?= htmlspecialchars($componentCssHref, ENT_QUOTES, 'UTF-8') ?>"
            hx-swap-oob="true">
        <?php
    }
}
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

$adminRepository = new AdministrationRepository();
$companyAddress = $adminRepository->readCompanyAddress();
?>
<h1>Administrace</h1>
<div class="admin-panel">
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
    <div id="admin-import-result-modal" class="admin-modal hidden" aria-hidden="true">
      <div class="admin-modal-overlay" data-admin-result-close></div>
      <div class="admin-modal-panel" role="dialog" aria-modal="true" aria-labelledby="admin-import-result-title">
        <header class="admin-modal-header">
          <h3 id="admin-import-result-title">Výsledek importu</h3>
          <button type="button" class="admin-modal-close" data-admin-result-close aria-label="Zavřít">×</button>
        </header>
        <div class="admin-modal-body">
          <p id="admin-import-result-message" class="admin-import-result-message"></p>
          <div class="admin-modal-actions">
            <button type="button" class="admin-action admin-action--primary" data-admin-result-close>Rozumím</button>
          </div>
        </div>
      </div>
    </div>
  </section>
  <section class="admin-logo" data-island="admin">
    <h2>Nahrát logo</h2>
    <p>Nahrajte logo ve formátu svg</p>
    <div class="admin-logo-actions">
      <button type="button" class="admin-action" data-admin-modal-logo>
        Vybrat soubor
      </button>
    </div>
    <div id="admin-logo-modal" class="admin-modal hidden" aria-hidden="true">
      <div class="admin-modal-overlay" data-admin-modal-close></div>
      <div class="admin-modal-panel" role="dialog" aria-modal="true" aria-labelledby="admin-logo-title">
        <header class="admin-modal-header">
          <h3 id="admin-logo-title">Nahrát logo</h3>
          <button type="button" class="admin-modal-close" data-admin-modal-close aria-label="Zavřít">×</button>
        </header>
        <form id="admin-logo-form" class="admin-modal-body" enctype="multipart/form-data">
          <?= csrf_field(); ?>
          <fieldset class="admin-modal-file hidden" data-admin-file>
            <legend>SVG soubor</legend>
            <label class="admin-file">
              <input type="file" name="svg_file" accept=".svg,image/svg+xml">
              <span class="admin-file-label">Vyberte SVG soubor k importu</span>
            </label>
            <p class="admin-file-hint">
              Import podporuje pouze SVG soubory.
            </p>
          </fieldset>
          <div class="admin-logo-preview hidden">
            <img id="admin-logo-preview-img" alt="Logo preview">
          </div>
          <div class="admin-modal-actions">
            <button type="button" class="admin-action" data-admin-modal-close>Storno</button>
            <button type="submit" class="admin-action admin-action--primary" data-admin-submit>
              Nahrát
            </button>
          </div>
        </form>
      </div>
    </div>
    <div id="admin-logo-result-modal" class="admin-modal hidden" aria-hidden="true">
      <div class="admin-modal-overlay" data-admin-result-close></div>
      <div class="admin-modal-panel" role="dialog" aria-modal="true" aria-labelledby="admin-logo-result-title">
        <header class="admin-modal-header">
          <h3 id="admin-logo-result-title">Výsledek nahrání</h3>
          <button type="button" class="admin-modal-close" data-admin-result-close aria-label="Zavřít">×</button>
        </header>
        <div class="admin-modal-body">
          <p id="admin-logo-result-message" class="admin-logo-result-message"></p>
          <div class="admin-modal-actions">
            <button type="button" class="admin-action admin-action--primary" data-admin-result-close>Rozumím</button>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="admin-address" data-island="admin">
    <h2>Firemní adresa</h2>
    <p>Nastavte adresu společnosti pro tisk výstupů a další administrativu.</p>
    <form id="admin-address-form" class="admin-address-form" novalidate>
      <?= csrf_field(); ?>
      <label class="admin-field">
        <span>Kód země</span>
        <input
          type="text"
          name="country_code"
          maxlength="2"
          minlength="2"
          required
          value="<?= htmlspecialchars((string)($companyAddress['country_code'] ?? 'CZ'), ENT_QUOTES, 'UTF-8') ?>"
          autocomplete="country">
      </label>
      <label class="admin-field">
        <span>Stát / kraj</span>
        <input
          type="text"
          name="state"
          required
          value="<?= htmlspecialchars((string)($companyAddress['state'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
          autocomplete="address-level1">
      </label>
      <label class="admin-field">
        <span>Město</span>
        <input
          type="text"
          name="city"
          required
          value="<?= htmlspecialchars((string)($companyAddress['city'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
          autocomplete="address-level2">
      </label>
      <div class="admin-field-row">
        <label class="admin-field">
          <span>Ulice</span>
          <input
            type="text"
            name="street"
            required
            value="<?= htmlspecialchars((string)($companyAddress['street'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
            autocomplete="address-line1">
        </label>
        <label class="admin-field">
          <span>Číslo popisné/orientační</span>
          <input
            type="text"
            name="street_number"
            required
            value="<?= htmlspecialchars((string)($companyAddress['street_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
            autocomplete="address-line2">
        </label>
      </div>
      <label class="admin-field">
        <span>PSČ</span>
        <input
          type="text"
          name="post_code"
          required
          value="<?= htmlspecialchars((string)($companyAddress['post_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
          autocomplete="postal-code">
      </label>
      <div class="admin-modal-actions">
        <button type="submit" class="admin-action admin-action--primary">Uložit adresu</button>
      </div>
      <p id="admin-address-feedback" class="admin-address-feedback hidden" role="status" aria-live="polite"></p>
    </form>
  </section>
</div>
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
