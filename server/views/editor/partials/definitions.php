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
    hx-swap-oob="true"
    hx-on='select:change:window.hxDefinitionParent(event,this)'
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

<style>
  .definition-toolbar {
    margin: 1rem 0;
    display: flex;
    justify-content: flex-start;
  }

  .definition-primary {
    border: 1px solid var(--primary);
    background: var(--primary);
    color: var(--primary-contrast);
    border-radius: 0.35rem;
    padding: 0.4rem 0.75rem;
    font-weight: 600;
    cursor: pointer;
  }

  .definition-primary:hover,
  .definition-primary:focus-visible {
    background: var(--primary-hover);
    border-color: var(--primary-hover);
  }

  .form-feedback {
    font-size: 0.85rem;
    border-radius: 0.35rem;
    padding: 0.5rem 0.6rem;
    margin: 0.5rem 0 0;
  }

  .form-feedback.hidden {
    display: none;
  }

  .form-feedback.form-feedback--error {
    background: rgb(220 38 38 / 12%);
    border: 1px solid #dc2626;
    color: #dc2626;
  }

  .form-feedback.form-feedback--success {
    background: rgb(22 163 74 / 12%);
    border: 1px solid #16a34a;
    color: #166534;
  }

  .definition-parent-cache {
    display: none;
  }

  #definitions-list {
    margin: 1.5rem 0;
  }

  .definition-tree {
    list-style: none;
    padding-left: 1rem;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
  }

  .definition-tree ul {
    list-style: none;
    padding-left: 1rem;
    margin: 0.35rem 0 0;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    border-left: 1px dashed rgb(0 0 0 / 20%);
  }

  [data-theme="dark"] .definition-tree ul {
    border-color: rgb(255 255 255 / 25%);
  }

  .definition-item {
    position: relative;
  }

  .definition-node {
    display: flex;
    justify-content: space-between;
    gap: 0.75rem;
    align-items: flex-start;
    border: 1px solid rgb(0 0 0 / 15%);
    border-radius: 0.35rem;
    padding: 0.4rem 0.5rem;
    background: rgb(0 0 0 / 2%);
    cursor: grab;
  }

  .definition-node:active {
    cursor: grabbing;
  }

  [data-theme="dark"] .definition-node {
    border-color: rgb(255 255 255 / 20%);
    background: rgb(255 255 255 / 5%);
  }

  .definition-node strong {
    font-weight: 600;
  }

  .definition-meta {
    font-size: 0.75rem;
    color: #555;
    margin-left: 1rem;
  }

  [data-theme="dark"] .definition-meta {
    color: #cbd5e1;
  }

  .definition-actions {
    display: flex;
    align-items: center;
    gap: 0.35rem;
  }

  .definition-action {
    border: 1px solid var(--primary);
    background: transparent;
    color: var(--primary);
    border-radius: 0.25rem;
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    cursor: pointer;
  }

  .definition-action:hover,
  .definition-action:focus-visible {
    background: var(--primary);
    color: var(--primary-contrast);
  }

  .definition-action--danger {
    border-color: #dc2626;
    color: #dc2626;
  }

  .definition-action--danger:hover,
  .definition-action--danger:focus-visible {
    background: #dc2626;
    color: #fff;
  }

  .definition-drag-indicator {
    font-size: 1.1rem;
    line-height: 1;
    opacity: 0.6;
    cursor: grab;
  }

  .definition-item--dragging > .definition-node {
    opacity: 0.6;
  }

  .definition-item--drop-before > .definition-node {
    box-shadow: 0 -2px 0 0 var(--primary);
  }

  .definition-item--drop-after > .definition-node {
    box-shadow: 0 2px 0 0 var(--primary);
  }

  .definition-item--drop-inside > .definition-node {
    outline: 2px dashed var(--primary);
  }

  .definitions-empty {
    font-style: italic;
    color: #6b7280;
  }

  .definitions-modal {
    position: fixed;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgb(0 0 0 / 40%);
    padding: 1rem;
    z-index: 2000;
  }

  .definitions-modal.hidden {
    display: none;
  }

  .definitions-modal-panel {
    background: var(--bg);
    color: var(--fg);
    border-radius: 0.5rem;
    box-shadow: 0 12px 32px rgb(0 0 0 / 22%);
    max-width: 420px;
    width: 100%;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

  .definitions-modal-panel header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
  }

  .definitions-modal-panel header h3 {
    margin: 0;
    font-size: 1.1rem;
  }

  .definitions-modal-panel header button {
    border: 0;
    background: transparent;
    color: inherit;
    font-size: 1.2rem;
    cursor: pointer;
  }

  .definitions-modal-body,
  .definition-modal-body,
  .definition-form--modal {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
  }

  .definition-form--modal fieldset {
    border: 0;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
  }

  .definition-field {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
  }

  .definition-field label {
    font-weight: 600;
    font-size: 0.9rem;
  }

  .definition-form--modal input,
  .definition-form--modal .select-button {
    border: 1px solid var(--fg);
    border-radius: 0.35rem;
    padding: 0.4rem 0.5rem;
    font: inherit;
    background: transparent;
    color: inherit;
    width: 100%;
    text-align: left;
  }

  .definition-form--modal input:focus,
  .definition-form--modal .select-button:focus {
    outline: 2px solid var(--primary);
    outline-offset: 1px;
  }

  .definition-form--modal .select-button:hover,
  .definition-form--modal .select-button:focus-visible {
    background: var(--primary);
    color: var(--primary-contrast);
  }

  .definition-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
  }
</style>
