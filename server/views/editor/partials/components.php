<?php
require_once __DIR__ . '/../../../lib/db.php';
require_once __DIR__ . '/../../../lib/components.php';

$BASE = rtrim((defined('BASE_PATH') ? BASE_PATH : ''), '/');
$pdo = get_db_connection();
$componentsTree = components_fetch_tree($pdo);
$componentsFlat = components_flatten_tree($componentsTree);
?>

<h2>Komponenty</h2>
<p style="max-width:640px">Komponenty rozšiřují definice konfigurátoru o konkrétní stavební bloky. Každá komponenta vychází z vybrané definice, může mít vlastní hierarchii a ukládá popis, obrázek i závislosti na dalších volbách.</p>

<div class="component-toolbar">
  <a class="component-primary" href="#" aria-disabled="true">Přidat komponentu (připravujeme)</a>
</div>

<div class="component-summary">
  <p><strong>Celkem komponent:</strong> <?= count($componentsFlat) ?></p>
</div>

<?php include __DIR__ . '/components-tree.php'; ?>

<style>
  .component-toolbar{margin:1rem 0;display:flex;justify-content:flex-start}
  .component-primary{border:1px solid var(--primary);background:var(--primary);color:var(--primary-contrast);border-radius:.35rem;padding:.4rem .75rem;font-weight:600;cursor:not-allowed;opacity:.6;text-decoration:none}
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
</style>
