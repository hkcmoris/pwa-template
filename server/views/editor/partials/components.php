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
<style>
  .component-toolbar {
    margin: 1rem 0;
    display: flex;
    justify-content: flex-start
  }

  .component-primary {
    border: 1px solid var(--primary);
    background: var(--primary);
    color: var(--primary-contrast);
    border-radius: .35rem;
    padding: .4rem .75rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none
  }

  .component-primary:hover,
  .component-primary:focus-visible {
    background: var(--primary-hover);
    border-color: var(--primary-hover)
  }

  .component-action {
    border: 1px solid var(--primary);
    background: transparent;
    color: var(--primary);
    border-radius: .25rem;
    padding: .25rem .5rem;
    font-size: .75rem;
    cursor: pointer
  }

  .component-action:hover,
  .component-action:focus-visible {
    background: var(--primary);
    color: var(--primary-contrast)
  }

  .component-action--danger {
    border-color: #dc2626;
    color: #dc2626
  }

  .component-action--danger:hover,
  .component-action--danger:focus-visible {
    background: #dc2626;
    color: #fff
  }

  .component-summary {
    font-size: .9rem;
    color: var(--fg-muted, #4b5563)
  }

  .components-empty {
    font-style: italic;
    color: #6b7280
  }

  .component-tree {
    list-style: none;
    padding-left: 1rem;
    margin: 1.5rem 0;
    display: flex;
    flex-direction: column;
    gap: .35rem
  }

  .component-tree ul {
    list-style: none;
    padding-left: 1.25rem;
    margin: .35rem 0 0;
    display: flex;
    flex-direction: column;
    gap: .35rem;
    border-left: 1px dashed rgb(0 0 0 / 20%)
  }

  [data-theme="dark"] .component-tree ul {
    border-color: rgb(255 255 255 / 25%)
  }

  .component-item {
    position: relative
  }

  .component-node {
    border: 1px solid rgb(0 0 0 / 15%);
    border-radius: .4rem;
    padding: .6rem .75rem;
    background: rgb(0 0 0 / 2%);
    display: flex;
    flex-direction: column;
    gap: .6rem
  }

  [data-theme="dark"] .component-node {
    border-color: rgb(255 255 255 / 20%);
    background: rgb(255 255 255 / 5%)
  }

  .component-node-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: .75rem;
    flex-wrap: wrap
  }

  .component-node-info {
    display: flex;
    flex-direction: column;
    gap: .25rem
  }

  .component-node-info strong {
    font-weight: 600;
    font-size: 1rem
  }

  .component-alias {
    font-size: .75rem;
    color: #4b5563
  }

  [data-theme="dark"] .component-alias {
    color: #cbd5e1
  }

  .component-meta {
    font-size: .75rem;
    color: var(--fg-muted);
  }

  .component-actions {
    display: flex;
    flex-wrap: wrap;
    gap: .35rem
  }

  .component-node-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: .5rem;
    margin: 0
  }

  .component-node-details dt {
    font-weight: 600;
    font-size: .75rem;
    color: var(--fg-muted);
    margin-bottom: .2rem
  }

  .component-node-details dd {
    margin: 0;
    font-size: .85rem
  }

  .components-modal {
    position: fixed;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgb(0 0 0 / 40%);
    padding: 1rem;
    z-index: 2000
  }

  .components-modal.hidden {
    display: none
  }

  .components-modal-overlay {
    position: absolute;
    inset: 0;
    background: transparent;
    cursor: pointer
  }

  .components-modal-panel {
    position: relative;
    background: var(--bg);
    color: var(--fg);
    border-radius: .5rem;
    box-shadow: 0 12px 32px rgb(0 0 0 / 22%);
    max-width: 480px;
    width: 100%;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    z-index: 1
  }

  .components-modal-panel header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: .5rem
  }

  .components-modal-panel header h3 {
    margin: 0;
    font-size: 1.1rem
  }

  .components-modal-panel header button {
    border: none;
    background: transparent;
    color: inherit;
    font-size: 1.2rem;
    cursor: pointer
  }

  .components-modal-body {
    display: flex;
    flex-direction: column;
    gap: 1rem
  }

  .component-form--modal {
    display: flex;
    flex-direction: column;
    gap: .75rem
  }

  .component-form--modal fieldset {
    border: 0;
    padding: 0;
    margin: 0;
    display: grid;
    gap: 1rem;
    grid-template-columns: minmax(0, 1fr)
  }

  @media (width >= 720px) {
    .component-form--modal fieldset {
      grid-template-columns: repeat(2, minmax(0, 1fr));
      align-items: start
    }
  }

  .component-form--modal legend {
    font-weight: 600;
    font-size: .95rem;
    grid-column: 1 / -1
  }

  .component-field--full {
    grid-column: 1 / -1
  }

  .component-field {
    display: flex;
    flex-direction: column;
    gap: .35rem
  }

  .component-field label {
    font-weight: 600;
    font-size: .9rem
  }

  .component-field--price {
    gap: .5rem;
  }

  .component-price-input {
    display: flex;
    align-items: center;
    gap: .5rem;
    max-width: 320px;
  }

  .component-price-input input {
    flex: 1;
    text-align: right;
  }

  .component-price-suffix {
    font-weight: 600;
    font-size: .9rem;
  }

  .component-price-history {
    display: flex;
    flex-direction: column;
    gap: .35rem;
  }

  .component-price-history-label {
    font-size: .75rem;
    font-weight: 600;
    color: var(--fg-muted, #6b7280);
    text-transform: uppercase;
    letter-spacing: .04em;
  }

  [data-theme="dark"] .component-price-history-label {
    color: var(--fg-muted, #94a3b8);
  }

  .component-price-history-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: .35rem;
  }

  .component-price-history-item {
    display: flex;
    justify-content: space-between;
    gap: .75rem;
    font-size: .85rem;
  }

  .component-price-history-amount {
    font-weight: 600;
  }

  .component-price-history-item time {
    font-size: .8rem;
    color: var(--fg-muted, #6b7280);
  }

  [data-theme="dark"] .component-price-history-item time {
    color: var(--fg-muted, #cbd5e1);
  }

  .component-price-history-empty {
    font-size: .85rem;
    font-style: italic;
    color: var(--fg-muted, #6b7280);
  }

  [data-theme="dark"] .component-price-history-empty {
    color: var(--fg-muted, #94a3b8);
  }

  .component-form--modal input:not([type="hidden"]),
  .component-form--modal textarea {
    border: 1px solid var(--fg);
    border-radius: .35rem;
    padding: .4rem .5rem;
    font: inherit;
    background: transparent;
    color: inherit
  }

  .component-form--modal input:not([type="hidden"]):focus,
  .component-form--modal textarea:focus,
  .component-form--modal .select-button:focus {
    outline: 2px solid var(--primary);
    outline-offset: 1px
  }

  .component-field--media {
    display: flex;
    flex-direction: column;
    gap: .5rem
  }

  .component-media-label {
    font-weight: 600;
    font-size: .85rem
  }

  .component-media-toggle {
    display: flex;
    flex-wrap: wrap;
    gap: .75rem;
    font-size: .85rem
  }

  .component-media-toggle input {
    margin: 0 .25rem 0 0
  }

  .component-media-panel {
    display: flex;
    flex-direction: column;
    gap: .35rem
  }

  .component-media-panel.hidden {
    display: none
  }

  .component-color-picker {
    display: flex;
    align-items: center;
    gap: .5rem
  }

  .component-color-chip {
    display: inline-block;
    width: 1rem;
    height: 1rem;
    border-radius: 999px;
    border: 1px solid rgb(0 0 0 / 20%);
    margin-right: .35rem;
    vertical-align: middle;
    background: var(--chip-color, #000)
  }

  [data-theme="dark"] .component-color-chip {
    border-color: rgb(255 255 255 / 35%)
  }

  .component-color-picker input[type="color"] {
    width: 2.5rem;
    height: 2.5rem;
    padding: 0;
    border: 1px solid var(--fg);
    border-radius: .35rem;
    background: transparent
  }

  .component-modal-body {
    display: flex;
    flex-direction: column;
    gap: .75rem
  }

  .components-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: .5rem
  }

  .component-help {
    font-size: .75rem;
    color: #6b7280;
    margin: 0
  }

  [data-theme="dark"] .component-help {
    color: #cbd5e1
  }

  .form-feedback {
    font-size: .85rem;
    border-radius: .35rem;
    padding: .5rem .6rem;
    margin: .5rem 0 0
  }

  .form-feedback.hidden {
    display: none
  }

  .form-feedback.form-feedback--error {
    background: rgb(220 38 38 / 12%);
    border: 1px solid #dc2626;
    color: #dc2626
  }

  .form-feedback.form-feedback--success {
    background: rgb(22 163 74 / 12%);
    border: 1px solid #16a34a;
    color: #166534
  }

  .component-select .select {
    width: 100%
  }

  .component-select[data-select-invalid] .select-button {
    border-color: #dc2626
  }
</style>
