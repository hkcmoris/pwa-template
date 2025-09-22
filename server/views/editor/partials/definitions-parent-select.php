<?php
$flat = $definitionsFlat ?? [];
$selectedParent = $selectedParent ?? null;
$rootLabel = 'Kořen (bez rodiče)';
$currentValue = '';
$currentLabel = $rootLabel;
if ($selectedParent !== null) {
    foreach ($flat as $item) {
        if ((int) $item['id'] === (int) $selectedParent) {
            $currentValue = (string) $item['id'];
            $currentLabel = $item['title'] . ' (ID ' . (int) $item['id'] . ')';
            break;
        }
    }
}
?>
<input type="hidden" id="definition-parent-value" name="parent_id" value="<?= htmlspecialchars($currentValue, ENT_QUOTES, 'UTF-8') ?>">
<div class="select" data-select data-value="<?= htmlspecialchars($currentValue, ENT_QUOTES, 'UTF-8') ?>" data-label="<?= htmlspecialchars($currentLabel, ENT_QUOTES, 'UTF-8') ?>">
  <button type="button" class="select__button" id="definition-parent-button" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="definition-parent-label definition-parent-button"><?= htmlspecialchars($currentLabel, ENT_QUOTES, 'UTF-8') ?></button>
  <ul class="select__list" role="listbox" tabindex="-1" hidden>
    <li role="option" class="select__option" data-value="" data-label="<?= htmlspecialchars($rootLabel, ENT_QUOTES, 'UTF-8') ?>" aria-selected="<?= $currentValue === '' ? 'true' : 'false' ?>"><?= htmlspecialchars($rootLabel, ENT_QUOTES, 'UTF-8') ?></li>
    <?php foreach ($flat as $item): ?>
      <?php
        $value = (string) $item['id'];
        $label = $item['title'] . ' (ID ' . (int) $item['id'] . ')';
        $indent = $item['depth'] > 0 ? str_repeat('— ', (int) $item['depth']) : '';
        $display = $indent . $label;
        $selected = $value === $currentValue ? 'true' : 'false';
      ?>
      <li role="option" class="select__option" data-value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" data-label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>" aria-selected="<?= $selected ?>"><?= htmlspecialchars($display, ENT_QUOTES, 'UTF-8') ?></li>
    <?php endforeach; ?>
  </ul>
</div>
