<?php

use Configuration\Repository;
use Configuration\WizardRepository;

// Normalize base path for view usage.
$baseCandidate = defined('BASE_PATH') ? (string) BASE_PATH : '';
$BASE = isset($BASE) && $BASE !== '' ? (string) $BASE : $baseCandidate;
$BASE = rtrim($BASE, '/');

if (!isset($role) || $role === 'guest') {
    echo '<h1>Přístup odepřen</h1>'
      . '<p>Prosím přihlaste se pro zobrazení vašich konfigurací.</p>';
    return;
}

if (isset($_SERVER['HTTP_HX_REQUEST'])) {
    $managerCssHref = vite_asset_href('src/styles/konfigurator/manager.css', $isDevEnv ?? false, $BASE);
    if ($managerCssHref !== null) {
        ?>
<link
  rel="stylesheet"
  id="konfigurator-manager"
  href="<?= htmlspecialchars($managerCssHref, ENT_QUOTES, 'UTF-8') ?>"
  hx-swap-oob="true"
>
        <?php
    }
}

$userId = isset($currentUser['id']) ? (int) $currentUser['id'] : 0;
if ($userId <= 0) {
    echo '<h1>Přístup odepřen</h1>'
      . '<p>Nelze získat informace o vašem účtu.</p>';
    return;
}

$pdo = get_db_connection();
$repository = new Repository($pdo);
$wizardRepository = new WizardRepository($pdo);
/** @var array<int, array<string, mixed>> $configurations */
$configurations = $repository->fetch(null, 0, $userId);
/** @var array<int, array<string, mixed>> $drafts */
$drafts = $wizardRepository->findDraftsByUser($userId);
$latestDraftId = $drafts !== [] ? (int) $drafts[0]['id'] : null;
$latestDraftTitle = $drafts !== [] ? trim((string) ($drafts[0]['title'] ?? '')) : '';
$latestDraftNumber = $drafts !== [] && isset($drafts[0]['draft_number'])
    ? (int) $drafts[0]['draft_number']
    : $latestDraftId;
$latestDraftLabel = $latestDraftId !== null
    ? ($latestDraftTitle !== '' ? $latestDraftTitle : ('Návrh #' . $latestDraftNumber))
    : '';
?>

<section data-island="konfigurator-manager">
  <svg
    width="0px"
    height="0px"
    display="none"
    style="display: none;"
    aria-hidden="true"
  >
    <symbol id="icon-trash" viewBox="0 0 512 512">
      <path
        d="M64,160,93.74,442.51A24,24,0,0,0,117.61,464H394.39a24,24,0,0,0,23.87-21.49L448,160
          M312,377.46l-56-56-56,56L174.54,352l56-56-56-56L200,214.54l56,56,56-56L337.46,240l-56,56,56,56Z"
      />
      <rect x="32" y="48" width="448" height="80" rx="12" ry="12"/>
    </symbol>
    <symbol id="icon-rename" viewBox="0 0 24 24">
      <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
        <g fill="currentColor" fill-rule="nonzero">
          <path
            d="M20.0624471,8.44531708 C21.3187966,9.70246962 21.3181456,11.7400656 20.060993,12.9964151 L12.9470184,
            20.0984035 C12.6752626,20.3697014 12.338595,20.5669341 11.9690608,20.6713285 L7.35604585,
            21.9745173 C6.78489045,22.1358702 6.26149869,21.6013269 6.43485528,21.0336996 L7.82237944,
            16.4904828 C7.93021937,16.1373789 8.12329466,15.8162379 8.38457552,15.5553852 L15.5089127,
            8.44272265 C16.767237,7.18646041 18.8055552,7.18762176 20.0624471,8.44531708 Z M8.1508398,
            2.36975012 L8.20132289,2.47486675 L11.4537996,10.724 L10.2967996,11.879 L9.5557996,10 L5.4427996,
            10 L4.44747776,12.5208817 C4.30788849,12.8739875 3.9301318,13.0620782 3.57143476,12.9736808 L3.47427411,
            12.9426336 C3.1211683,12.8030443 2.93307758,12.4252876 3.02147501,12.0665906 L3.05252224,
            11.9694299 L6.80613337,2.47427411 C7.0415216,1.87883471 7.84863764,1.84414583 8.1508398,
            2.36975012 Z M7.50274363,4.79226402 L6.0357996,8.5 L8.9637996,8.5 L7.50274363,4.79226402 Z"
          />
        </g>
      </g>
    </symbol>
  </svg>
  <h1>Konfigurace</h1>
  <div class="konfigurator-manager-actions">
    <button
      hx-get="<?= htmlspecialchars($BASE) ?>/konfigurator?new=1"
      hx-push-url="true"
      hx-target="#content"
      hx-select="#content"
      hx-swap="outerHTML"
    >Vytvořit novou konfiguraci</button>

    <?php if ($latestDraftId !== null) : ?>
      <button
        hx-get="<?= htmlspecialchars($BASE) ?>/konfigurator?draft=<?= htmlspecialchars((string) $latestDraftId) ?>"
        hx-push-url="true"
        hx-target="#content"
        hx-select="#content"
        hx-swap="outerHTML"
      >Pokračovat v posledním návrhu (<?= htmlspecialchars($latestDraftLabel) ?>)</button>
    <?php endif; ?>
  </div>

  <h2>Rozpracované návrhy</h2>
  <div id="draft-form-errors" class="form-feedback hidden" role="status" aria-live="polite"></div>
  <div id="draft-list-wrapper">
    <?php include __DIR__ . '/konfigurator/partials/drafts-list.php'; ?>
  </div>

  <h2>Dokončené konfigurace</h2>
  <div id="configurations-form-errors" class="form-feedback hidden" role="status" aria-live="polite"></div>
  <div id="configurations-list-wrapper">
    <?php include __DIR__ . '/konfigurator/partials/configurations-list.php'; ?>
  </div>

  <div id="konfigurator-manager-modal" class="konfigurator-manager-modal hidden" aria-hidden="true"></div>
</section>
</div>
