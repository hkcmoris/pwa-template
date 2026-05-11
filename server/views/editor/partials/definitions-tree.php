<?php
$tree = $definitionsPage ?? $definitionsTree ?? [];
$BASE = rtrim((defined('BASE_PATH') ? BASE_PATH : ''), '/');
if (!isset($definitionPageSize)) {
    $definitionPageSize = count($tree);
}
if (!isset($totalDefinitions)) {
    $totalDefinitions = count($tree);
}
if (!isset($nextOffset)) {
    $nextOffset = count($tree);
}
if (!isset($hasMore)) {
    $hasMore = false;
}
$definitionsChunkOnly = $definitionsChunkOnly ?? false;

if (!function_exists('render_definition_items')) {
    /**
     * @param array<int, array<string, mixed>> $nodes
     */
    function render_definition_items(
        array $nodes,
        string $listAttributes = '',
        bool $wrap = true
    ): void {
        if (empty($nodes)) {
            return;
        }
        if ($wrap) {
            echo '<ul class="definition-tree"' . $listAttributes . '>';
        }
        foreach ($nodes as $node) {
            $id = (int) $node['id'];
            $parentId = $node['parent_id'] === null ? '' : (string) (int) $node['parent_id'];
            $position = (int) $node['position'];
            $nodePath = isset($node['id_path']) ? (string) $node['id_path'] : (string) $id;
            $posPath = isset($node['pos_path']) ? (string) $node['pos_path'] : (string) $position;
            $depth = isset($node['depth']) ? (int) $node['depth'] : 0;
            $depth = max(0, min($depth, 12)); // Clamp for sanity and potential CSS class limits.
            $depthClass = 'depth-' . $depth;
            $childrenCount = isset($node['children_count']) ? (int) $node['children_count'] : 0;
            echo '<li class="definition-item ' . $depthClass . '"'
                . ' data-id="' . $id . '"'
                . ' data-parent="' . $parentId . '"'
                . ' data-position="' . $position . '"'
                . ' data-path="' . htmlspecialchars($nodePath, ENT_QUOTES, 'UTF-8') . '"'
                . ' data-title="' . htmlspecialchars($node['title'], ENT_QUOTES, 'UTF-8') . '"'
                . ' data-depth="' . $depth . '"'
                . ' data-children-count="' . $childrenCount . '"'
                . '>';
            echo '<div class="definition-node" draggable="true">';
            echo '<div class="definition-position">' . htmlspecialchars($posPath, ENT_QUOTES, 'UTF-8') . '</div>';
            echo '<div class="definition-node-info">';
            echo '<strong>' . htmlspecialchars($node['title'], ENT_QUOTES, 'UTF-8') . '</strong>';
            echo '</div>';
            echo '<div class="definition-actions">';
            echo '<button type="button" class="definition-action"'
                . ' data-action="create-child"'
                . '>'
                . '<svg'
                . ' fill="currentColor"'
                . ' width="16px"'
                . ' height="16px"'
                . ' display="block"'
                . '>'
                . '<use href="#icon-add"></use>'
                . '</svg>'
                . '</button>';
            echo '<button type="button" class="definition-action"'
                . ' data-action="rename"'
                . '>'
                . '<svg'
                . ' fill="currentColor"'
                . ' width="16px"'
                . ' height="16px"'
                . ' display="block"'
                . '>'
                . '<use href="#icon-rename"></use>'
                . '</svg>'
                . '</button>';
            echo '<button type="button" class="definition-action definition-action--danger"'
                . ' data-action="delete"'
                . '>'
                . '<svg'
                . ' fill="currentColor"'
                . ' width="16px"'
                . ' height="16px"'
                . ' display="block"'
                . '>'
                . '<use href="#icon-trash"></use>'
                . '</svg>'
                . '</button>';
            echo '<span class="definition-drag-indicator" aria-hidden="true">⋮⋮</span>';
            echo '</div>';
            echo '</div>';
            echo '</li>';
        }
        if ($wrap) {
            echo '</ul>';
        }
    }
}
?>
<?php if ($definitionsChunkOnly) : ?>
    <?php render_definition_items($tree, '', false); ?>
    <?php return; ?>
<?php endif; ?>
<div id="definitions-list">
  <?php if (empty($tree)) : ?>
    <p class="definitions-empty">Zatím nebyly vytvořeny žádné definice.</p>
  <?php else : ?>
      <?php
        $listAttributes = ' id="definitions-tree"'
          . ' data-page-size="' . (int) $definitionPageSize . '"'
          . ' data-total="' . (int) $totalDefinitions . '"'
          . ' data-next-offset="' . (int) $nextOffset . '"';
        render_definition_items($tree, $listAttributes);
        ?>
      <div
        id="definitions-list-sentinel"
        data-definition-sentinel
        data-next-offset="<?= (int) $nextOffset ?>"
        data-page-size="<?= (int) $definitionPageSize ?>"
        data-total="<?= (int) $totalDefinitions ?>"
        data-has-more="<?= $hasMore ? '1' : '0' ?>"
        aria-hidden="true"
      ></div>
  <?php endif; ?>
</div>
