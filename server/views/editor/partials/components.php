<?php

use Components\Formatter;
use Components\Repository;
use Definitions\Formatter as DefinitionsFormatter;
use Definitions\Repository as DefinitionsRepository;

require_once __DIR__ . '/../../../bootstrap.php';

$BASE = rtrim((defined('BASE_PATH') ? BASE_PATH : ''), '/');
$pdo = get_db_connection();
$formatter = new Formatter();
$repository = new Repository($pdo, $formatter);
$componentsTree = $repository->fetchTree();
$componentsFlat = $formatter->flattenTree($componentsTree);
$definitionsFormatter = new DefinitionsFormatter();
$definitionsRepository = new DefinitionsRepository($pdo);
$definitionsTree = $definitionsRepository->fetchTree($definitionsFormatter);
$definitionsFlat = $definitionsFormatter->flattenTree($definitionsTree);
?>

<?php
$componentStyleEntry = 'src/styles/editor/components.css';
if (isset($_SERVER['HTTP_HX_REQUEST'])) {
    $componentCssHref = vite_asset_href($componentStyleEntry, $isDevEnv ?? false, $BASE);
    if ($componentCssHref !== null) {
        ?>
<link
  rel="stylesheet"
  id="editor-partial-style"
  href="<?= htmlspecialchars($componentCssHref, ENT_QUOTES, 'UTF-8') ?>"
  hx-swap-oob="true"
>
        <?php
    }
}
?>

<div
  id="components-root"
  data-island="components"
  data-base="<?= htmlspecialchars($BASE) ?>"
>
  <h2>Komponenty</h2>
  <p style="max-width:640px">
    Komponenty rozšiřují definice konfigurátoru o konkrétní stavební bloky.
    Každá komponenta vychází z vybrané definice, může mít vlastní hierarchii
    a ukládá popis, obrázek i závislosti na dalších volbách.
  </p>
  <div class="component-toolbar">
    <button type="button" id="component-open-create" class="component-primary">Přidat komponentu</button>
  </div>
  <div id="component-form-errors" class="form-feedback hidden" role="status" aria-live="polite"></div>
  <div id="component-summary" class="component-summary">
    <p><strong>Celkem komponent:</strong> <?= count($componentsFlat) ?></p>
  </div>
  <template id="component-create-template">
    <?php include __DIR__ . '/components-create-form.php'; ?>
  </template>
  <div id="components-modal" class="components-modal hidden" aria-hidden="true"></div>
  <?php include __DIR__ . '/components-tree.php'; ?>
</div>

