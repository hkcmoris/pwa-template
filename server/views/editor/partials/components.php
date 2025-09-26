<?php
require_once __DIR__ . '/../../../lib/db.php';
require_once __DIR__ . '/../../../lib/components.php';
require_once __DIR__ . '/../../../lib/definitions.php';

$BASE = rtrim((defined('BASE_PATH') ? BASE_PATH : ''), '/');
$pdo = get_db_connection();
$componentsTree = components_fetch_tree($pdo);
$componentsFlat = components_flatten_tree($componentsTree);
$definitionsTree = definitions_fetch_tree($pdo);
$definitionsFlat = definitions_flatten_tree($definitionsTree);
?>

<div id="components-root" data-island="components" data-base="<?= htmlspecialchars($BASE) ?>">
  <h2>Komponenty</h2>
  <p style="max-width:640px">Komponenty rozšiřují definice konfigurátoru o konkrétní stavební bloky. Každá komponenta vychází z vybrané definice, může mít vlastní hierarchii a ukládá popis, obrázek i závislosti na dalších volbách.</p>

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

<style>
  .component-toolbar{margin:1rem 0;display:flex;justify-content:flex-start}
  .component-primary{border:1px solid var(--primary);background:var(--primary);color:var(--primary-contrast);border-radius:.35rem;padding:.4rem .75rem;font-weight:600;cursor:pointer;text-decoration:none}
  .component-primary:hover,.component-primary:focus-visible{background:var(--primary-hover);border-color:var(--primary-hover)}
  .component-action{border:1px solid var(--primary);background:transparent;color:var(--primary);border-radius:.25rem;padding:.3rem .6rem;font-size:.85rem;cursor:pointer}
  .component-action:hover,.component-action:focus-visible{background:var(--primary);color:var(--primary-contrast)}
  .component-summary{font-size:.9rem;color:var(--fg-muted,#4b5563)}
  .components-empty{font-style:italic;color:#6b7280}
  .component-tree{list-style:none;padding-left:1rem;margin:1.5rem 0;display:flex;flex-direction:column;gap:.5rem}
  .component-tree ul{list-style:none;padding-left:1.25rem;margin:.35rem 0 0 0;display:flex;flex-direction:column;gap:.5rem;border-left:1px dashed rgba(0,0,0,.2)}
  [data-theme="dark"] .component-tree ul{border-color:rgba(255,255,255,.25)}
  .component-item{position:relative}
  .component-node{border:1px solid rgba(0,0,0,.15);border-radius:.4rem;padding:.6rem .75rem;background:rgba(0,0,0,.02);display:flex;flex-direction:column;gap:.5rem}
  [data-theme="dark"] .component-node{border-color:rgba(255,255,255,.2);background:rgba(255,255,255,.05)}
  .component-node__header{display:flex;flex-wrap:wrap;gap:.35rem;align-items:baseline}
  .component-node__header strong{font-weight:600;font-size:1rem}
  .component-node__subtitle{font-size:.85rem;color:#4b5563}
  [data-theme="dark"] .component-node__subtitle{color:#cbd5e1}
  .component-node__meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.5rem;margin:0}
  .component-node__meta dt{font-weight:600;font-size:.75rem;color:#4b5563;margin-bottom:.2rem}
  [data-theme="dark"] .component-node__meta dt{color:#cbd5e1}
  .component-node__meta dd{margin:0;font-size:.85rem}
  .components-modal{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.4);padding:1rem;z-index:2000}
  .components-modal.hidden{display:none}
  .components-modal__overlay{position:absolute;inset:0;background:transparent;cursor:pointer}
  .components-modal__panel{position:relative;background:var(--bg);color:var(--fg);border-radius:.5rem;box-shadow:0 12px 32px rgba(0,0,0,.22);max-width:480px;width:100%;padding:1rem;display:flex;flex-direction:column;gap:1rem;z-index:1}
  .components-modal__panel header{display:flex;justify-content:space-between;align-items:center;gap:.5rem}
  .components-modal__panel header h3{margin:0;font-size:1.1rem}
  .components-modal__panel header button{border:none;background:transparent;color:inherit;font-size:1.2rem;cursor:pointer}
  .components-modal__body{display:flex;flex-direction:column;gap:1rem}
  .component-form--modal{display:flex;flex-direction:column;gap:.75rem}
  .component-form--modal fieldset{border:0;padding:0;margin:0;display:flex;flex-direction:column;gap:.75rem}
  .component-field{display:flex;flex-direction:column;gap:.35rem}
  .component-field label{font-weight:600;font-size:.9rem}
  .component-form--modal input:not([type="hidden"]),.component-form--modal textarea,.component-form--modal .select__button{border:1px solid var(--fg);border-radius:.35rem;padding:.4rem .5rem;font:inherit;background:transparent;color:inherit}
  .component-form--modal input:not([type="hidden"]):focus,.component-form--modal textarea:focus,.component-form--modal .select__button:focus{outline:2px solid var(--primary);outline-offset:1px}
  .components-modal-actions{display:flex;justify-content:flex-end;gap:.5rem}
  .component-help{font-size:.75rem;color:#6b7280;margin:0}
  [data-theme="dark"] .component-help{color:#cbd5e1}
  .form-feedback{font-size:.85rem;border-radius:.35rem;padding:.5rem .6rem;margin:.5rem 0 0}
  .form-feedback.hidden{display:none}
  .form-feedback.form-feedback--error{background:rgba(220,38,38,.12);border:1px solid #dc2626;color:#dc2626}
  .form-feedback.form-feedback--success{background:rgba(22,163,74,.12);border:1px solid #16a34a;color:#166534}
  .component-select[data-select-invalid] .select__button{border-color:#dc2626}
</style>
