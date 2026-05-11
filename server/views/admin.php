<?php

require_once __DIR__ . '/../lib/Administration/Repository.php';

use Administration\Repository as AdministrationRepository;

// Normalize base path for view usage.
$baseCandidate = defined('BASE_PATH') ? (string) BASE_PATH : '';
$BASE = isset($BASE) && $BASE !== '' ? (string) $BASE : $baseCandidate;
$BASE = rtrim($BASE, '/');

$subnavStyleEntry = 'src/styles/subnav.css';
$componentStyleEntry = 'src/styles/admin.css';
if (isset($_SERVER['HTTP_HX_REQUEST'])) {
    $subnavCssHref = vite_asset_href($subnavStyleEntry, $isDevEnv ?? false, $BASE);
    $componentCssHref = vite_asset_href($componentStyleEntry, $isDevEnv ?? false, $BASE);
    if ($subnavCssHref !== null) {
        ?>
        <link
            rel="stylesheet"
            id="subnav"
            href="<?= htmlspecialchars($subnavCssHref, ENT_QUOTES, 'UTF-8') ?>"
            hx-swap-oob="true">
        <?php
    }
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

$__editorUser = isset($currentUser) && is_array($currentUser) ? $currentUser : app_get_current_user();
$__editorRole = isset($__editorUser['role']) ? $__editorUser['role'] : (isset($role) ? $role : 'guest');
$canAccessUsersTab = in_array($__editorRole, ['admin', 'superadmin'], true);
$canAccessSuperadminTabs = $__editorRole === 'superadmin';

if (!$canAccessUsersTab) {
    if (!headers_sent()) {
        http_response_code(403);
    }
    echo '<h1>Administrace</h1>';
    echo '<div id="admin-root">';
    echo '<div role="alert">';
    echo '  <h2>Přístup odepřen</h2>';
    echo '  <p>Nemáte oprávnění pro zobrazení administrace.</p>';
    echo '</div>';
    echo '</div>';
    return;
}

$allowedTabs = $canAccessSuperadminTabs
    ? ['import-export', 'company', 'users']
    : ['users'];
$requestedTab = isset($_GET['tab']) ? trim((string) $_GET['tab']) : '';
$activeTab = in_array($requestedTab, $allowedTabs, true)
    ? $requestedTab
    : $allowedTabs[0];

$companyAddress = [];
$logoSettings = [];
$logoLightPreviewUrl = '';
$logoDarkPreviewUrl = '';
$logoPdfPreviewUrl = '';
$logoWatermarkPreviewUrl = '';
$hasDarkLogo = false;
$hasPdfLogo = false;
$hasCustomPdfWatermark = false;
if ($canAccessSuperadminTabs && $activeTab === 'company') {
    $adminRepository = new AdministrationRepository();
    $companyAddress = $adminRepository->readCompanyAddress();
    $logoSettings = $adminRepository->readLogoSettings();

    $assetUrl = static function (string $path, string $updatedAt) use ($BASE): string {
        $normalizedPath = ltrim($path, '/');
        $url = ($BASE !== '' ? $BASE : '') . '/' . $normalizedPath;
        if ($updatedAt !== '') {
            $url .= '?v=' . rawurlencode($updatedAt);
        }
        return $url;
    };

    $lightPath = (string) $logoSettings['path'];
    $lightUpdatedAt = (string) $logoSettings['updated_at'];
    $darkPath = trim((string) $logoSettings['dark_path']);
    $darkUpdatedAt = (string) $logoSettings['dark_updated_at'];
    $pdfPath = trim((string) $logoSettings['pdf_path']);
    $pdfUpdatedAt = (string) $logoSettings['pdf_updated_at'];
    $watermarkPath = trim((string) $logoSettings['pdf_watermark_path']);
    $watermarkUpdatedAt = (string) $logoSettings['pdf_watermark_updated_at'];

    $hasDarkLogo = $darkPath !== '';
    $hasPdfLogo = $pdfPath !== '';
    $hasCustomPdfWatermark = $watermarkPath !== '';

    $logoLightPreviewUrl = $assetUrl($lightPath, $lightUpdatedAt);
    $logoDarkPreviewUrl = $assetUrl(
        $hasDarkLogo ? $darkPath : $lightPath,
        $hasDarkLogo ? $darkUpdatedAt : $lightUpdatedAt
    );
    $logoPdfPreviewUrl = $assetUrl(
        $hasPdfLogo ? $pdfPath : $lightPath,
        $hasPdfLogo ? $pdfUpdatedAt : $lightUpdatedAt
    );
    $logoWatermarkPreviewUrl = $assetUrl(
        $hasCustomPdfWatermark ? $watermarkPath : 'public/watermark-tile.svg',
        $hasCustomPdfWatermark ? $watermarkUpdatedAt : ''
    );
}

$tabLabels = [
    'import-export' => 'Import / Export',
    'company' => 'Firemní údaje',
    'users' => 'Uživatelé',
];
$tabHref = static function (string $tabKey) use ($BASE): string {
    return $BASE . '/admin?tab=' . rawurlencode($tabKey);
};
?>

<h1>Administrace</h1>

<nav id="admin-nav-menu" class="sub-nav-menu shadow-bevel" aria-label="Administrace sekce">
  <?php foreach ($allowedTabs as $tabKey) : ?>
        <?php $isActive = $tabKey === $activeTab; ?>
        <?php $href = $tabHref($tabKey); ?>
    <a
      href="<?= htmlspecialchars($href) ?>"
      hx-get="<?= htmlspecialchars($href) ?>"
      hx-push-url="true"
      hx-target="#content"
      hx-swap="innerHTML"
      class="sub-nav-link<?= $isActive ? ' active' : '' ?>"
      aria-current="<?= $isActive ? 'page' : 'false' ?>"
    ><?= htmlspecialchars((string) $tabLabels[$tabKey]) ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($activeTab === 'import-export' && $canAccessSuperadminTabs) : ?>
  <section class="admin-tab-panel" aria-labelledby="admin-tab-import-export">
    <h2 id="admin-tab-import-export" class="admin-tab-title">Import / Export</h2>
    <p class="admin-tab-description">Import databáze, export databáze a SQL konzole.</p>

    <div data-island="admin">
      <div class="admin-panel admin-panel-row">
        <section class="admin-transfer shadow-bevel">
          <h3>Import a export databáze</h3>
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
                  <button type="button" class="admin-action admin-action--primary" data-admin-result-close>
                    Rozumím
                  </button>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>

      <section class="admin-panel admin-panel-col">
        <h3>SQL konzole</h3>
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
      </section>
    </div>
  </section>
<?php endif; ?>

<?php if ($activeTab === 'company' && $canAccessSuperadminTabs) : ?>
  <section class="admin-tab-panel" aria-labelledby="admin-tab-company">
    <h2 id="admin-tab-company" class="admin-tab-title">Firemní údaje</h2>
    <p class="admin-tab-description">Správa loga a firemní adresy.</p>

    <div class="admin-panel admin-panel-row" data-island="admin">
      <div class="admin-panel-col">
        <section class="admin-logo shadow-bevel">
          <h3>Loga a vodoznak</h3>
          <p>
            Nahrajte varianty loga ve formátu SVG. Pokud tmavé logo není nastavené, použije se invertované hlavní logo.
          </p>

          <form id="admin-logo-form" class="admin-logo-form" enctype="multipart/form-data" novalidate>
            <?= csrf_field(); ?>
            <div class="admin-logo-current-grid">
              <figure class="admin-logo-current">
                <figcaption id="admin-logo-light-label">Aktuální logo (světlý režim)</figcaption>
                <div class="admin-logo-preview">
                  <img
                    id="admin-logo-current-light"
                    src="<?= htmlspecialchars($logoLightPreviewUrl, ENT_QUOTES, 'UTF-8') ?>"
                    alt="Aktuální logo pro světlý režim"
                  >
                </div>
                <label class="admin-field">
                  <input
                    type="file"
                    name="logo_light_svg"
                    accept=".svg,image/svg+xml"
                    aria-labelledby="admin-logo-light-label">
                </label>
              </figure>

              <figure class="admin-logo-current">
                <figcaption id="admin-logo-dark-label">Aktuální logo (tmavý režim)</figcaption>
                <div class="admin-logo-preview admin-logo-preview--dark">
                  <img
                    id="admin-logo-current-dark"
                    class="<?= $hasDarkLogo ? '' : 'admin-logo-preview-image--fallback-invert' ?>"
                    src="<?= htmlspecialchars($logoDarkPreviewUrl, ENT_QUOTES, 'UTF-8') ?>"
                    alt="Aktuální logo pro tmavý režim"
                  >
                </div>
                <p id="admin-logo-dark-fallback-hint" class="admin-file-hint<?= $hasDarkLogo ? ' hidden' : '' ?>">
                  Není nastaveno tmavé logo, aplikace používá invertované hlavní logo.
                </p>
                <label class="admin-field">
                  <input
                    type="file"
                    name="logo_dark_svg"
                    accept=".svg,image/svg+xml"
                    aria-labelledby="admin-logo-dark-label">
                </label>
              </figure>

              <figure class="admin-logo-current">
                <figcaption id="admin-logo-pdf-label">Aktuální logo pro PDF</figcaption>
                <div class="admin-logo-preview">
                  <img
                    id="admin-logo-current-pdf"
                    src="<?= htmlspecialchars($logoPdfPreviewUrl, ENT_QUOTES, 'UTF-8') ?>"
                    alt="Aktuální logo pro PDF"
                  >
                </div>
                <p id="admin-logo-pdf-fallback-hint" class="admin-file-hint<?= $hasPdfLogo ? ' hidden' : '' ?>">
                  Není nastaveno PDF logo, PDF používá hlavní logo.
                </p>
                <label class="admin-field">
                  <input
                    type="file"
                    name="logo_pdf_svg"
                    accept=".svg,image/svg+xml"
                    aria-labelledby="admin-logo-pdf-label">
                </label>
              </figure>

              <figure class="admin-logo-current">
                <figcaption id="admin-logo-watermark-label">Aktuální vodoznak pro PDF</figcaption>
                <div class="admin-logo-preview admin-logo-preview--watermark">
                  <img
                    id="admin-logo-current-watermark"
                    src="<?= htmlspecialchars($logoWatermarkPreviewUrl, ENT_QUOTES, 'UTF-8') ?>"
                    alt="Aktuální vodoznak pro PDF"
                  >
                </div>
                <p
                  id="admin-logo-watermark-fallback-hint"
                  class="admin-file-hint<?= $hasCustomPdfWatermark ? ' hidden' : '' ?>"
                >
                  Není nastaven vlastní vodoznak, PDF používá výchozí.
                </p>
                <label class="admin-field">
                  <input
                    type="file"
                    name="watermark_tile_svg"
                    accept=".svg,image/svg+xml"
                    aria-labelledby="admin-logo-watermark-label">
                </label>
              </figure>
            </div>
            <p class="admin-file-hint">Můžete nahrát pouze vybrané soubory, ostatní zůstanou beze změny.</p>
            <div class="admin-modal-actions">
              <button type="submit" class="admin-action admin-action--primary" data-admin-logo-submit>
                Uložit soubory
              </button>
            </div>
            <p id="admin-logo-feedback" class="admin-address-feedback hidden" role="status" aria-live="polite"></p>
          </form>
        </section>
      </div>

      <section class="admin-address shadow-bevel">
        <h3>Firemní adresa</h3>
        <p>Nastavte adresu společnosti pro tisk výstupů a další administrativu.</p>
        <form id="admin-address-form" class="admin-address-form" novalidate>
          <?= csrf_field(); ?>
          <label class="admin-field">
            <span>Název firmy</span>
            <input
              type="text"
              name="company_name"
              required
              placeholder="Název firmy"
              value="<?= htmlspecialchars((string) ($companyAddress['company_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
              autocomplete="organization">
          </label>
          <label class="admin-field">
            <span>Kód země</span>
            <input
              type="text"
              name="country_code"
              maxlength="2"
              minlength="2"
              required
              placeholder="Kód země"
              value="<?= htmlspecialchars((string) ($companyAddress['country_code'] ?? 'CZ'), ENT_QUOTES, 'UTF-8') ?>"
              autocomplete="country">
          </label>
          <label class="admin-field">
            <span>Stát / kraj</span>
            <input
              type="text"
              name="state"
              placeholder="Stát / kraj"
              value="<?= htmlspecialchars((string) ($companyAddress['state'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
              autocomplete="address-level1">
          </label>
          <div class="admin-field-row">
            <label class="admin-field">
              <span>Město</span>
              <input
                type="text"
                name="city"
                required
                placeholder="Město"
                value="<?= htmlspecialchars((string) ($companyAddress['city'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                autocomplete="address-level2">
            </label>
            <label class="admin-field">
              <span>PSČ</span>
              <input
                type="text"
                name="post_code"
                required
                placeholder="PSČ"
                value="<?= htmlspecialchars((string) ($companyAddress['post_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                autocomplete="postal-code">
            </label>
          </div>
          <div class="admin-field-row">
            <label class="admin-field">
              <span>Ulice</span>
              <input
                type="text"
                name="street"
                required
                placeholder="Ulice"
                value="<?= htmlspecialchars((string) ($companyAddress['street'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                autocomplete="address-line1">
            </label>
            <label class="admin-field">
              <span>Číslo popisné/orientační</span>
              <input
                type="text"
                name="street_number"
                required
                placeholder="Číslo popisné/orientační"
                value="<?= htmlspecialchars((string) ($companyAddress['street_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                autocomplete="address-line2">
            </label>
          </div>
          <div class="admin-modal-actions">
            <button type="submit" class="admin-action admin-action--primary">Uložit adresu</button>
          </div>
          <p id="admin-address-feedback" class="admin-address-feedback hidden" role="status" aria-live="polite"></p>
        </form>
      </section>
    </div>
    <div id="admin-messages" class="admin-feedback hidden" role="status" aria-live="polite"></div>
  </section>
<?php endif; ?>

<?php if ($activeTab === 'users') : ?>
  <section class="admin-tab-panel admin-users" aria-labelledby="admin-tab-users">
    <h2 id="admin-tab-users" class="admin-tab-title">Uživatelé</h2>
    <p class="admin-tab-description">Přehled uživatelů a správa jejich rolí.</p>
    <div data-island="users">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Uživatelské jméno</th>
            <th>E‑mail</th>
            <th>Role</th>
            <th>Počet konfigurací</th>
            <th>Datum vytvoření</th>
          </tr>
        </thead>
        <tbody id="users-list"></tbody>
      </table>
      <div id="users-message"></div>
    </div>
  </section>
<?php endif; ?>
