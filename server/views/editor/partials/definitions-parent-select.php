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

require_once __DIR__ . '/select-tree-helpers.php';

$definitionSiblingTotals = editor_select_tree_sibling_totals($flat);
$definitionTreeState = editor_select_tree_initial_state();
?>
<input
  type="hidden"
  id="definition-parent-value"
  name="parent_id"
  value="<?= htmlspecialchars($currentValue, ENT_QUOTES, 'UTF-8') ?>"
>
<div
  class="select"
  data-select
  data-value="<?= htmlspecialchars($currentValue, ENT_QUOTES, 'UTF-8') ?>"
  data-label="<?= htmlspecialchars($currentLabel, ENT_QUOTES, 'UTF-8') ?>"
>
  <button
    type="button"
    class="select-button"
    id="definition-parent-button"
    aria-haspopup="listbox"
    aria-expanded="false"
    aria-labelledby="definition-parent-label definition-parent-button"
  ><?= htmlspecialchars($currentLabel, ENT_QUOTES, 'UTF-8') ?></button>
  <ul class="select-list" role="listbox" tabindex="-1" hidden>
    <li
      role="option"
      class="select-option"
      data-value=""
      data-label="<?= htmlspecialchars($rootLabel, ENT_QUOTES, 'UTF-8') ?>"
      aria-selected="<?= $currentValue === '' ? 'true' : 'false' ?>"
    ><?= htmlspecialchars($rootLabel, ENT_QUOTES, 'UTF-8') ?></li>
    <?php foreach ($flat as $item) : ?>
        <?php
        $value = (string) $item['id'];
        $title = (string) $item['title'];
        $id = (int) $item['id'];
        $label = $title . ' (ID ' . $id . ')';
        $depth = isset($item['depth']) ? (int) $item['depth'] : 0;
        $depth = max(0, min($depth, 12));
        $optionClass = editor_select_tree_option_class(
            $item,
            $depth,
            $definitionSiblingTotals,
            $definitionTreeState
        );
        $selected = $value === $currentValue ? 'true' : 'false';
        ?>
      <li
        role="option"
        class="select-option <?= $optionClass ?>"
        data-depth="<?= $depth ?>"
        data-value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"
        data-label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"
        aria-selected="<?= $selected ?>"
      >
        <?= editor_select_tree_svg($depth, $optionClass) ?>
        <?= editor_select_tree_label($title, $id) ?>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
