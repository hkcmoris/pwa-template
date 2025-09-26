<?php
$definitionOptions = $definitionsFlat ?? [];
$componentOptions = $componentsFlat ?? [];
?>
<form
  class="component-form component-form--modal"
  hx-post="<?= htmlspecialchars($BASE) ?>/editor/components-create"
  action="<?= htmlspecialchars($BASE) ?>/editor/components-create"
  method="post"
  hx-target="#components-list"
  hx-select="#components-list"
  hx-swap="outerHTML"
>
  <fieldset>
    <legend>Přidat novou komponentu</legend>
    <div class="component-field">
      <label for="component-modal-definition">Definice</label>
      <select id="component-modal-definition" name="definition_id" required>
        <option value="" disabled selected>Vyberte definici</option>
        <?php foreach ($definitionOptions as $definition): ?>
          <?php
            $depth = isset($definition['depth']) ? (int) $definition['depth'] : 0;
            $indent = $depth > 0 ? str_repeat('— ', $depth) : '';
            $title = htmlspecialchars($definition['title'] ?? '', ENT_QUOTES, 'UTF-8');
            $id = (int) ($definition['id'] ?? 0);
          ?>
          <option value="<?= $id ?>"><?= $indent . $title ?> (ID <?= $id ?>)</option>
        <?php endforeach; ?>
      </select>
      <p class="component-help">Povinné pole. Každá komponenta vychází z konkrétní definice.</p>
    </div>
    <div class="component-field">
      <label for="component-modal-title">Alternativní název</label>
      <input
        type="text"
        id="component-modal-title"
        name="alternate_title"
        maxlength="191"
        placeholder="např. Vchodový modul"
      >
      <p class="component-help">Nepovinné. Pokud je vyplněno, zobrazí se místo názvu definice.</p>
    </div>
    <div class="component-field">
      <label for="component-modal-parent">Rodič</label>
      <select id="component-modal-parent" name="parent_id">
        <option value="">Kořenová komponenta</option>
        <?php foreach ($componentOptions as $component): ?>
          <?php
            $depth = isset($component['depth']) ? (int) $component['depth'] : 0;
            $indent = $depth > 0 ? str_repeat('— ', $depth) : '';
            $title = htmlspecialchars($component['effective_title'] ?? ($component['alternate_title'] ?? ''), ENT_QUOTES, 'UTF-8');
            $id = (int) ($component['id'] ?? 0);
          ?>
          <option value="<?= $id ?>"><?= $indent . $title ?> (ID <?= $id ?>)</option>
        <?php endforeach; ?>
      </select>
      <p class="component-help">Volitelné. Zvolte rodičovskou komponentu pro vytvoření hierarchie.</p>
    </div>
    <div class="component-field">
      <label for="component-modal-position">Pozice</label>
      <input
        type="number"
        id="component-modal-position"
        name="position"
        min="0"
        step="1"
        placeholder="automaticky"
      >
      <p class="component-help">Pořadí mezi sourozenci (0 = první). Prázdné pole přidá položku na konec.</p>
    </div>
    <div class="component-field">
      <label for="component-modal-description">Popis</label>
      <textarea
        id="component-modal-description"
        name="description"
        rows="4"
        placeholder="Krátký popis komponenty"
      ></textarea>
    </div>
    <div class="component-field">
      <label for="component-modal-image">Obrázek</label>
      <input
        type="text"
        id="component-modal-image"
        name="image"
        maxlength="255"
        placeholder="např. /assets/components/modul.jpg"
      >
    </div>
  </fieldset>
  <div class="components-modal-actions">
    <button type="button" class="component-action" data-modal-close>Storno</button>
    <button type="submit" class="component-primary">Uložit</button>
  </div>
</form>
