<?php
// Normalize base path for view usage.
$baseCandidate = defined('BASE_PATH') ? (string) BASE_PATH : '';
$BASE = isset($BASE) && $BASE !== '' ? (string) $BASE : $baseCandidate;
$BASE = rtrim($BASE, '/');
$definitionOptions = $definitionsFlat ?? [];
$componentOptions = $componentsFlat ?? [];
$definitionPlaceholder = 'Vyberte definici';
$parentPlaceholder = 'Kořenová komponenta';
$dependencyPlaceholder = 'Vyberte komponentu';
?>
<form
  class="component-form component-form--modal"
  hx-post="<?= htmlspecialchars($BASE) ?>/editor/components/create"
  action="<?= htmlspecialchars($BASE) ?>/editor/components/create"
  method="post"
  hx-target="#components-list-wrapper"
  hx-swap="innerHTML"
>
  <input type="hidden" id="component-modal-id" name="component_id" value="">
  <fieldset>
    <legend class="sr-only">Formulář komponenty</legend>
    <div class="component-modal-tabs" data-component-tabs>
      <div class="component-modal-tablist sub-nav-menu shadow-bevel" role="tablist" aria-label="Sekce formuláře komponenty">
        <button
          type="button"
          class="component-modal-tab sub-nav-link is-active"
          role="tab"
          id="component-tab-main"
          aria-controls="component-panel-main"
          aria-selected="true"
          data-component-tab="main"
        >Základní údaje</button>
        <button
          type="button"
          class="component-modal-tab sub-nav-link"
          role="tab"
          id="component-tab-price"
          aria-controls="component-panel-price"
          aria-selected="false"
          tabindex="-1"
          data-component-tab="price"
        >Cena</button>
        <button
          type="button"
          class="component-modal-tab sub-nav-link"
          role="tab"
          id="component-tab-media"
          aria-controls="component-panel-media"
          aria-selected="false"
          tabindex="-1"
          data-component-tab="media"
        >Barva / obrázky</button>
        <button
          type="button"
          class="component-modal-tab sub-nav-link"
          role="tab"
          id="component-tab-properties"
          aria-controls="component-panel-properties"
          aria-selected="false"
          tabindex="-1"
          data-component-tab="properties"
        >Vlastnosti</button>
        <button
          type="button"
          class="component-modal-tab sub-nav-link"
          role="tab"
          id="component-tab-dependencies"
          aria-controls="component-panel-dependencies"
          aria-selected="false"
          tabindex="-1"
          data-component-tab="dependencies"
        >Závislosti</button>
      </div>

      <section
        class="component-modal-tabpanel"
        role="tabpanel"
        id="component-panel-main"
        aria-labelledby="component-tab-main"
        data-component-panel="main"
      >
        <div class="component-field">
          <label id="component-modal-definition-label" for="component-modal-definition">
            Definice
            <span class="info-wrapper">
              <img width="24px" height="24px" src="<?= htmlspecialchars($BASE) ?>/public/assets/images/info.svg" />
              <span class="component-help">Povinné pole. Každá komponenta vychází z konkrétní definice.</span>
            </span>
          </label>
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
        </div>
        <div class="component-field">
          <label id="component-modal-parent-label" for="component-modal-parent">
            Rodič
            <span class="info-wrapper">
              <img width="24px" height="24px" src="<?= htmlspecialchars($BASE) ?>/public/assets/images/info.svg" />
              <span class="component-help">Volitelné. Zvolte rodičovskou komponentu pro vytvoření hierarchie.</span>
            </span>
          </label>
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
        </div>
        <div class="component-field">
          <label for="component-modal-position">
            Pozice
            <span class="info-wrapper">
              <img width="24px" height="24px" src="<?= htmlspecialchars($BASE) ?>/public/assets/images/info.svg" />
              <span class="component-help">
                Pořadí mezi sourozenci (0 = první). Prázdné pole přidá položku na konec.
              </span>
            </span>
          </label>
          <input
            type="number"
            id="component-modal-position"
            name="position"
            min="0"
            step="1"
            placeholder="automaticky"
          >
        </div>
        <div class="component-field">
          <label for="component-modal-title">
            Alternativní název
            <span class="info-wrapper">
              <img width="24px" height="24px" src="<?= htmlspecialchars($BASE) ?>/public/assets/images/info.svg" />
              <span class="component-help">Nepovinné. Pokud je vyplněno, zobrazí se místo názvu definice.</span>
            </span>
          </label>
          <input
            type="text"
            id="component-modal-title"
            name="alternate_title"
            maxlength="191"
            placeholder="např. Střešní spojler"
          >
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
      </section>

      <section
        class="component-modal-tabpanel hidden"
        role="tabpanel"
        id="component-panel-price"
        aria-labelledby="component-tab-price"
        data-component-panel="price"
      >
        <div class="component-field component-field--price component-field--full">
          <label for="component-modal-price">
            Cena
            <span class="info-wrapper">
              <img width="24px" height="24px" src="<?= htmlspecialchars($BASE) ?>/public/assets/images/info.svg" />
              <span class="component-help">
                Volitelné. Zadejte cenu s DPH ve formátu 1234,56 (max. dvě desetinná místa).
              </span>
            </span>
          </label>
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
          <div class="component-price-history" data-price-history-wrapper>
            <span class="component-price-history-label">Historie cen</span>
            <ul class="component-price-history-list" data-price-history-list>
              <li class="component-price-history-empty" data-empty-state>Žádné záznamy.</li>
            </ul>
          </div>
        </div>
      </section>

      <section
        class="component-modal-tabpanel hidden"
        role="tabpanel"
        id="component-panel-media" 
        aria-labelledby="component-tab-media"
        data-component-panel="media"
      >
        <div class="component-field component-field--media component-field--full">
          <span class="component-media-label">Reprezentace</span>
          <div class="component-media-toggle" data-media-toggle>
            <label>
              <input type="radio" name="media_type" value="image" checked data-media-choice="image">
              Obrázek
            </label>
            <label>
              <input type="radio" name="media_type" value="color" data-media-choice="color">
              Barva
            </label>
          </div>
          <div class="component-media-panel" data-media-panel="image">
            <label for="component-modal-image">
              Obrázek
              <span class="info-wrapper">
                <img width="24px" height="24px" src="<?= htmlspecialchars($BASE) ?>/public/assets/images/info.svg" />
                <span class="component-help">Vyberte obrázek z galerie (volitelné).</span>
              </span>
            </label>
            <div class="component-image-picker" data-image-picker>
              <input
                type="hidden"
                id="component-modal-image"
                name="images"
                data-media-input="image"
                data-images-input
              >
              <div class="component-image-selected" data-image-display>
                <span class="component-image-placeholder" data-image-placeholder>Žádný obrázek není vybrán.</span>
                <ul class="component-image-list" data-image-list></ul>
              </div>
              <div class="component-image-actions">
                <button type="button" class="component-action" data-image-select-open>Vybrat obrázek</button>
                <button 
                  type="button" 
                  class="component-action component-action--danger" 
                  data-image-clear 
                  disabled
                >Odebrat vše</button>
              </div>
            </div>
          </div>
          <div class="component-media-panel hidden" data-media-panel="color">
            <label for="component-modal-color">
              Barva
              <span class="info-wrapper">
                <img width="24px" height="24px" src="<?= htmlspecialchars($BASE) ?>/public/assets/images/info.svg" />
                <span class="component-help">Hex formát (#RGB nebo #RRGGBB). Swatch pomůže s výběrem.</span>
              </span>
            </label>
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
          </div>
        </div>
      </section>

      <section
        class="component-modal-tabpanel hidden"
        role="tabpanel"
        id="component-panel-properties"
        aria-labelledby="component-tab-properties"
        data-component-panel="properties"
      >
        <div class="component-field component-field--full">
          <label for="component-modal-properties">
            Vlastnosti komponenty
            <span class="info-wrapper">
              <img width="24px" height="24px" src="<?= htmlspecialchars($BASE) ?>/public/assets/images/info.svg" />
              <span class="component-help">
                Přidejte vlastnosti ve tvaru název, hodnota a jednotka (např. výkon 120 kW).
              </span>
            </span>
          </label>
          <input
            type="hidden"
            id="component-modal-properties"
            name="properties"
            value="[]"
            data-properties-input
          >
          <div class="component-properties-editor" data-properties-editor>
            <ul class="component-properties-list" data-properties-list></ul>
            <button
              type="button"
              class="component-action"
              data-properties-add
            >+ Přidat vlastnost</button>
          </div>
          <template data-properties-row-template>
            <li class="component-properties-item" data-properties-item>
              <input
                type="text"
                maxlength="120"
                placeholder="Název"
                data-property-name
              >
              <input
                type="text"
                maxlength="120"
                placeholder="Hodnota"
                data-property-value
              >
              <input
                type="text"
                maxlength="32"
                placeholder="Jednotka"
                data-property-unit
              >
              <button
                type="button"
                class="component-action component-action--danger"
                data-properties-remove
              >Odebrat</button>
            </li>
          </template>
        </div>
      </section>

      <section
        class="component-modal-tabpanel hidden"
        role="tabpanel"
        id="component-panel-dependencies"
        aria-labelledby="component-tab-dependencies"
        data-component-panel="dependencies"
      >
        <div class="component-field component-field--full">
          <label for="component-modal-dependency-tree">
            Nastavení závislostí
            <span class="info-wrapper">
              <img width="24px" height="24px" src="<?= htmlspecialchars($BASE) ?>/public/assets/images/info.svg" />
              <span class="component-help">
                Komponenta se nabídne podle zvoleného pravidla: buď pokud jsou vybrané
                všechny závislosti (AND), nebo alespoň jedna z nich (OR).
              </span>
            </span>
          </label>
          <input
            type="hidden"
            id="component-modal-dependency-tree"
            name="dependency_tree"
            value="[]"
            data-dependency-tree-input
          >
          <div class="component-dependency-editor" data-dependency-editor>
            <input type="hidden" value="and" data-dependency-operator-input>
            <div class="component-select component-dependency-operator" data-select-wrapper>
              <div
                class="select"
                data-select
                data-dependency-operator-select
                data-required="true"
                data-value="and"
                data-label="Splnit všechny skupiny (AND)"
              >
                <button
                  type="button"
                  class="select-button"
                  aria-haspopup="listbox"
                  aria-expanded="false"
                >Splnit všechny skupiny (AND)</button>
                <ul class="select-list" role="listbox" tabindex="-1" hidden>
                  <li
                    role="option"
                    class="select-option"
                    data-value="and"
                    data-label="Splnit všechny skupiny (AND)"
                    aria-selected="true"
                  >Splnit všechny skupiny (AND)</li>
                  <li
                    role="option"
                    class="select-option"
                    data-value="or"
                    data-label="Splnit aspoň jednu skupinu (OR)"
                    aria-selected="false"
                  >Splnit aspoň jednu skupinu (OR)</li>
                </ul>
              </div>
            </div>
            <ul class="component-dependency-group-list" data-dependency-group-list></ul>
            <div class="component-dependency-actions">
              <button
                type="button"
                class="component-action"
                data-dependency-group-add
              >+ Přidat skupinu</button>
              <button
                type="button"
                class="component-action"
                data-dependency-add
              >+ Přidat pravidlo do 1. skupiny</button>
            </div>
            <div class="component-forbidden-editor">
              <p class="component-dependency-subheading">Zakázané komponenty</p>
              <ul class="component-dependency-list" data-forbidden-list></ul>
              <button
                type="button"
                class="component-action"
                data-forbidden-add
              >+ Přidat zakázanou komponentu</button>
            </div>
            <p class="component-dependency-hint">
              Bez zadaných pravidel je komponenta dostupná vždy. Zakázané komponenty dostupnost vždy blokují.
            </p>
          </div>
          <template data-dependency-group-template>
            <li class="component-dependency-group" data-dependency-group>
              <div class="component-dependency-group-header">
                <input type="hidden" value="and" data-dependency-group-operator-input>
                <div class="component-select component-dependency-operator" data-select-wrapper>
                  <div
                    class="select"
                    data-select
                    data-dependency-group-operator-select
                    data-required="true"
                    data-value="and"
                    data-label="V této skupině musí platit vše (AND)"
                  >
                    <button
                      type="button"
                      class="select-button"
                      aria-haspopup="listbox"
                      aria-expanded="false"
                    >V této skupině musí platit vše (AND)</button>
                    <ul class="select-list" role="listbox" tabindex="-1" hidden>
                      <li
                        role="option"
                        class="select-option"
                        data-value="and"
                        data-label="V této skupině musí platit vše (AND)"
                        aria-selected="true"
                      >V této skupině musí platit vše (AND)</li>
                      <li
                        role="option"
                        class="select-option"
                        data-value="or"
                        data-label="V této skupině stačí jedna podmínka (OR)"
                        aria-selected="false"
                      >V této skupině stačí jedna podmínka (OR)</li>
                    </ul>
                  </div>
                </div>
                <button
                  type="button"
                  class="component-action component-action--danger"
                  data-dependency-group-remove
                >Odebrat skupinu</button>
              </div>
              <ul class="component-dependency-list" data-dependency-rules-list></ul>
              <button
                type="button"
                class="component-action"
                data-dependency-rule-add
              >+ Přidat pravidlo do skupiny</button>
            </li>
          </template>
          <template data-dependency-row-template>
            <li class="component-dependency-item" data-dependency-item>
              <div class="component-select" data-select-wrapper>
                <input type="hidden" value="" data-dependency-component-id>
                <div
                  class="select"
                  data-select
                  data-required="true"
                  data-value=""
                  data-label="<?= htmlspecialchars($dependencyPlaceholder, ENT_QUOTES, 'UTF-8') ?>"
                >
                  <button
                    type="button"
                    class="select-button"
                    aria-haspopup="listbox"
                    aria-expanded="false"
                  ><?= htmlspecialchars($dependencyPlaceholder, ENT_QUOTES, 'UTF-8') ?></button>
                  <ul class="select-list" role="listbox" tabindex="-1" hidden>
                    <li
                      role="option"
                      class="select-option"
                      data-value=""
                      data-label="<?= htmlspecialchars($dependencyPlaceholder, ENT_QUOTES, 'UTF-8') ?>"
                      aria-selected="true"
                    ><?= htmlspecialchars($dependencyPlaceholder, ENT_QUOTES, 'UTF-8') ?></li>
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
              <button
                type="button"
                class="component-action component-action--danger"
                data-dependency-remove
              >Odebrat</button>
            </li>
          </template>
          <template data-forbidden-row-template>
            <li class="component-dependency-item" data-forbidden-item>
              <div class="component-select" data-select-wrapper>
                <input type="hidden" value="" data-forbidden-component-id>
                <div
                  class="select"
                  data-select
                  data-required="true"
                  data-value=""
                  data-label="Vyberte zakázanou komponentu"
                >
                  <button
                    type="button"
                    class="select-button"
                    aria-haspopup="listbox"
                    aria-expanded="false"
                  >Vyberte zakázanou komponentu</button>
                  <ul class="select-list" role="listbox" tabindex="-1" hidden>
                    <li
                      role="option"
                      class="select-option"
                      data-value=""
                      data-label="Vyberte zakázanou komponentu"
                      aria-selected="true"
                    >Vyberte zakázanou komponentu</li>
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
              <button
                type="button"
                class="component-action component-action--danger"
                data-forbidden-remove
              >Odebrat</button>
            </li>
          </template>
        </div>
      </section>
    </div>
  </fieldset>
  <div class="components-modal-actions">
    <button type="button" class="component-action" data-modal-close>Storno</button>
    <button type="submit" class="component-primary">Uložit</button>
  </div>
</form>
