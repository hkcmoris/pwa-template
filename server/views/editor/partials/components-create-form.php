<?php
$definitionOptions = $definitionsFlat ?? [];
$componentOptions = $componentsFlat ?? [];
$definitionPlaceholder = 'Vyberte definici';
$parentPlaceholder = 'Kořenová komponenta';
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
      <label id="component-modal-definition-label" for="component-modal-definition">Definice</label>
      <div class="component-select" data-select-wrapper>
        <input type="hidden" id="component-modal-definition" name="definition_id" value="">
        <div
          class="select"
          data-select
          data-required="true"
          data-value=""
          data-label="<?= htmlspecialchars($definitionPlaceholder, ENT_QUOTES, 'UTF-8') ?>"
        >
          <button
            type="button"
            class="select__button"
            id="component-modal-definition-button"
            aria-haspopup="listbox"
            aria-expanded="false"
            aria-labelledby="component-modal-definition-label component-modal-definition-button"
          ><?= htmlspecialchars($definitionPlaceholder, ENT_QUOTES, 'UTF-8') ?></button>
          <ul class="select__list" role="listbox" tabindex="-1" hidden>
            <li
              role="option"
              class="select__option"
              data-value=""
              data-label="<?= htmlspecialchars($definitionPlaceholder, ENT_QUOTES, 'UTF-8') ?>"
              aria-selected="true"
            ><?= htmlspecialchars($definitionPlaceholder, ENT_QUOTES, 'UTF-8') ?></li>
            <?php foreach ($definitionOptions as $definition): ?>
              <?php
                $depth = isset($definition['depth']) ? (int) $definition['depth'] : 0;
                $indent = $depth > 0 ? str_repeat('-- ', $depth) : '';
                $rawTitle = (string) ($definition['title'] ?? '');
                $id = (int) ($definition['id'] ?? 0);
                $labelText = $rawTitle . ' (ID ' . $id . ')';
                $displayText = $indent . $labelText;
              ?>
              <li
                role="option"
                class="select__option"
                data-value="<?= $id ?>"
                data-label="<?= htmlspecialchars($labelText, ENT_QUOTES, 'UTF-8') ?>"
                aria-selected="false"
              ><?= htmlspecialchars($displayText, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
      <p class="component-help">Povinné pole. Každá komponenta vychází z konkrétní definice.</p>
    </div>
    <div class="component-field">
      <label for="component-modal-title">Alternativní název</label>
      <input
        type="text"
        id="component-modal-title"
        name="alternate_title"
        maxlength="191"
        placeholder="např. Střešní spojler"
      >
      <p class="component-help">Nepovinné. Pokud je vyplněno, zobrazí se místo názvu definice.</p>
    </div>
    <div class="component-field">
      <label id="component-modal-parent-label" for="component-modal-parent">Rodič</label>
      <div class="component-select" data-select-wrapper>
        <input type="hidden" id="component-modal-parent" name="parent_id" value="">
        <div
          class="select"
          data-select
          data-value=""
          data-label="<?= htmlspecialchars($parentPlaceholder, ENT_QUOTES, 'UTF-8') ?>"
        >
          <button
            type="button"
            class="select__button"
            id="component-modal-parent-button"
            aria-haspopup="listbox"
            aria-expanded="false"
            aria-labelledby="component-modal-parent-label component-modal-parent-button"
          ><?= htmlspecialchars($parentPlaceholder, ENT_QUOTES, 'UTF-8') ?></button>
          <ul class="select__list" role="listbox" tabindex="-1" hidden>
            <li
              role="option"
              class="select__option"
              data-value=""
              data-label="<?= htmlspecialchars($parentPlaceholder, ENT_QUOTES, 'UTF-8') ?>"
              aria-selected="true"
            ><?= htmlspecialchars($parentPlaceholder, ENT_QUOTES, 'UTF-8') ?></li>
            <?php foreach ($componentOptions as $component): ?>
              <?php
                $depth = isset($component['depth']) ? (int) $component['depth'] : 0;
                $indent = $depth > 0 ? str_repeat('-- ', $depth) : '';
                $rawTitle = (string) ($component['effective_title'] ?? $component['alternate_title'] ?? '');
                $id = (int) ($component['id'] ?? 0);
                $labelText = $rawTitle . ' (ID ' . $id . ')';
                $displayText = $indent . $labelText;
              ?>
              <li
                role="option"
                class="select__option"
                data-value="<?= $id ?>"
                data-label="<?= htmlspecialchars($labelText, ENT_QUOTES, 'UTF-8') ?>"
                aria-selected="false"
              ><?= htmlspecialchars($displayText, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
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