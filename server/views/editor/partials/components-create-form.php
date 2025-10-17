<?php
// Normalize base path for view usage.
$baseCandidate = defined('BASE_PATH') ? (string) BASE_PATH : '';
$BASE = isset($BASE) && $BASE !== '' ? (string) $BASE : $baseCandidate;
$BASE = rtrim($BASE, '/');
$definitionOptions = $definitionsFlat ?? [];
$componentOptions = $componentsFlat ?? [];
$definitionPlaceholder = 'Vyberte definici';
$parentPlaceholder = 'Kořenová komponenta';
?>
<form
  class="component-form component-form--modal"
  hx-post="<?= htmlspecialchars($BASE) ?>/editor/components/create"
  action="<?= htmlspecialchars($BASE) ?>/editor/components/create"
  method="post"
  hx-target="#components-list-wrapper"
  hx-select="#components-list-wrapper"
  hx-swap="outerHTML"
>
  <input type="hidden" id="component-modal-id" name="component_id" value="">
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
            class="select-button"
            id="component-modal-definition-button"
            aria-haspopup="listbox"
            aria-expanded="false"
            aria-labelledby="component-modal-definition-label component-modal-definition-button"
          ><?= htmlspecialchars($definitionPlaceholder, ENT_QUOTES, 'UTF-8') ?></button>
          <ul class="select-list" role="listbox" tabindex="-1" hidden>
            <li
              role="option"
              class="select-option"
              data-value=""
              data-label="<?= htmlspecialchars($definitionPlaceholder, ENT_QUOTES, 'UTF-8') ?>"
              aria-selected="true"
            ><?= htmlspecialchars($definitionPlaceholder, ENT_QUOTES, 'UTF-8') ?></li>
            <?php foreach ($definitionOptions as $definition) : ?>
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
                class="select-option"
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
            class="select-button"
            id="component-modal-parent-button"
            aria-haspopup="listbox"
            aria-expanded="false"
            aria-labelledby="component-modal-parent-label component-modal-parent-button"
          ><?= htmlspecialchars($parentPlaceholder, ENT_QUOTES, 'UTF-8') ?></button>
          <ul class="select-list" role="listbox" tabindex="-1" hidden>
            <li
              role="option"
              class="select-option"
              data-value=""
              data-label="<?= htmlspecialchars($parentPlaceholder, ENT_QUOTES, 'UTF-8') ?>"
              aria-selected="true"
            ><?= htmlspecialchars($parentPlaceholder, ENT_QUOTES, 'UTF-8') ?></li>
            <?php foreach ($componentOptions as $component) : ?>
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
                class="select-option"
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
    <div class="component-field component-field--full">
      <label for="component-modal-description">Popis</label>
      <textarea
        id="component-modal-description"
        name="description"
        rows="4"
        placeholder="Krátký popis komponenty"
      ></textarea>
    </div>
    <div class="component-field component-field--price component-field--full">
      <label for="component-modal-price">Cena</label>
      <div class="component-price-input">
        <input
          type="text"
          id="component-modal-price"
          name="price"
          inputmode="decimal"
          autocomplete="off"
          placeholder="např. 2499,00"
          data-price-input
        >
        <span class="component-price-suffix" data-price-currency>CZK</span>
      </div>
      <p class="component-help">Volitelné. Zadejte cenu s DPH ve formátu 1234,56 (max. dvě desetinná místa).</p>
      <div class="component-price-history" data-price-history-wrapper>
        <span class="component-price-history-label">Historie cen</span>
        <ul class="component-price-history-list" data-price-history-list>
          <li class="component-price-history-empty" data-empty-state>Žádné záznamy.</li>
        </ul>
      </div>
    </div>
    <div class="component-field component-field--media component-field--full">
      <span class="component-media-label">Reprezentace</span>
      <div class="component-media-toggle" data-media-toggle>
        <label><input type="radio" name="media_type" value="image" checked data-media-choice="image"> Obrazek</label>
        <label><input type="radio" name="media_type" value="color" data-media-choice="color"> Barva</label>
      </div>
      <div class="component-media-panel" data-media-panel="image">
        <label for="component-modal-image">Obrazek</label>
        <div class="component-image-picker" data-image-picker>
          <input
            type="hidden"
            id="component-modal-image"
            name="image"
            maxlength="255"
            data-media-input="image"
            data-image-input
          >
          <div class="component-image-selected" data-image-display>
            <span class="component-image-placeholder" data-image-placeholder>Žádný obrázek není vybrán.</span>
            <span class="component-image-path" data-image-path></span>
          </div>
          <div class="component-image-actions">
            <button type="button" class="component-action" data-image-select-open>Vybrat obrázek</button>
            <button type="button" class="component-action" data-image-clear disabled>Odebrat</button>
          </div>
        </div>
        <p class="component-help">Vyberte obrázek z galerie (volitelné).</p>
      </div>
      <div class="component-media-panel hidden" data-media-panel="color">
        <label for="component-modal-color">Barva</label>
        <div class="component-color-picker">
          <input
            type="text"
            id="component-modal-color"
            name="color"
            maxlength="21"
            placeholder="napr. #FF8800"
            data-color-text
          >
          <input
            type="color"
            id="component-modal-color-swatch"
            value="#ffffff"
            data-color-picker
            aria-label="Vybrat barvu komponenty"
          >
        </div>
        <p class="component-help">Hex format (#RGB nebo #RRGGBB). Swatch pomuze s vyberem.</p>
      </div>
    </div>
  </fieldset>
  <div class="components-modal-actions">
    <button type="button" class="component-action" data-modal-close>Storno</button>
    <button type="submit" class="component-primary">Uložit</button>
  </div>
</form>
