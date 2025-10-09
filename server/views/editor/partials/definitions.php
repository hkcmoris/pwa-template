<?php
require_once __DIR__ . '/../../../lib/db.php';

use Definitions\Formatter;
use Definitions\Repository;

$BASE = rtrim((defined('BASE_PATH') ? BASE_PATH : ''), '/');
$pdo = get_db_connection();
$definitionsRepository = new Repository($pdo);
$definitionsFormatter = new Formatter();
$definitionsTree = $definitionsRepository->fetchTree($definitionsFormatter);
$definitionsFlat = $definitionsFormatter->flattenTree($definitionsTree);
?>

<?php
$definitionsStyleEntry = 'src/styles/editor/definitions.css';
if (isset($_SERVER['HTTP_HX_REQUEST'])) {
    $definitionsCssHref = vite_asset_href($definitionsStyleEntry, $isDevEnv ?? false, $BASE);
    if ($definitionsCssHref !== null) {
        ?>
<link
  rel="stylesheet"
  id="editor-partial-style"
  href="<?= htmlspecialchars($definitionsCssHref, ENT_QUOTES, 'UTF-8') ?>"
  hx-swap-oob="true"
>
        <?php
    }
}
?>

<h2>Definice</h2>
<p style="max-width: 640px;">
  Spravujte hierarchii definic konfigurátorů. Položky můžete vnořovat podle struktury nabídky
  a později na ně navázat komponenty.
</p>

<div class="definition-toolbar">
  <button type="button" id="definition-open-create" class="definition-primary">Přidat definici</button>
</div>

<div id="definition-form-errors" class="form-feedback hidden" role="status" aria-live="polite"></div>

<div class="definition-parent-cache" aria-hidden="true">
  <div
    id="definition-parent-select"
    class="definition-parent-select"
    data-island="select"
    hx-on:select:change="
      const hidden=this.querySelector('#definition-parent-value');
      if (hidden) {
        const raw = (event.detail && event.detail.value) || ''; 
        hidden.value = raw;
      }
    "
    style="display: none"
  >
    <?php $definitionsParentSwap = false;
    $selectedParent = null;
    include __DIR__ . '/definitions-parent-select.php'; ?>
  </div>
</div>

<template id="definition-create-template">
  <form
    class="definition-form definition-form--modal"
    hx-post="<?= htmlspecialchars($BASE) ?>/editor/definitions/create"
    action="<?= htmlspecialchars($BASE) ?>/editor/definitions/create"
    method="post"
    hx-target="#definitions-list"
    hx-select="#definitions-list"
    hx-swap="outerHTML"
  >
    <fieldset>
      <legend>Přidat novou definici</legend>
      <div class="definition-field">
        <label for="definition-modal-title">Název</label>
                <input
          type="text"
          id="definition-modal-title"
          name="title"
          maxlength="191"
          required
          placeholder="např. Nástavba"
        />
      </div>
      <div class="definition-field">
        <label id="definition-parent-label">Rodič</label>
        <div data-definition-select-slot></div>
        <p class="definition-help">
          Vyberte rodičovský uzel, nebo ponechte možnost Kořen pro položku nejvyšší úrovně.
        </p>
      </div>
      <div class="definition-field">
        <label for="definition-modal-position">Pozice</label>
                <input
          type="number"
          id="definition-modal-position"
          name="position"
          min="0"
          step="1"
          placeholder="automaticky"
        />
        <p class="definition-help">
          Pořadí mezi sourozenci (0 = první). Prázdné pole přidá uzel na konec.
        </p>
      </div>
    </fieldset>
    <div class="definition-modal-actions">
      <button type="button" class="definition-action" data-modal-close>Storno</button>
      <button type="submit" class="definition-primary">Uložit</button>
    </div>
  </form>
</template>

<div id="definitions-modal" class="definitions-modal hidden" aria-hidden="true"></div>

<?php include __DIR__ . '/definitions-tree.php'; ?>
