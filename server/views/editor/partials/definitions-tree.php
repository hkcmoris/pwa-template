<?php
$tree = $definitionsTree ?? [];
$BASE = rtrim((defined('BASE_PATH') ? BASE_PATH : ''), '/');

if (!function_exists('render_definition_nodes')) {
    function render_definition_nodes(array $nodes, string $path = ''): void
    {
        if (empty($nodes)) {
            return;
        }
        echo '<ul class="definition-tree">';
        foreach ($nodes as $node) {
            $id = (int) $node['id'];
            $parentId = $node['parent_id'] === null ? '' : (string) (int) $node['parent_id'];
            $position = (int) $node['position'];
            $children = $node['children'] ?? [];
            $childCount = count($children);
            $nodePath = ltrim(($path === '' ? '' : $path . '/') . $id, '/');
            echo '<li class="definition-item" data-id="' . $id . '" data-parent="' . htmlspecialchars($parentId, ENT_QUOTES, 'UTF-8') . '" data-position="' . $position . '" data-path="' . htmlspecialchars($nodePath, ENT_QUOTES, 'UTF-8') . '" data-children-count="' . $childCount . '">';
            echo '<div class="definition-node" draggable="true" data-id="' . $id . '" data-title="' . htmlspecialchars($node['title'], ENT_QUOTES, 'UTF-8') . '">';
            echo '<div class="definition-node-info">';
            echo '<strong>' . htmlspecialchars($node['title'], ENT_QUOTES, 'UTF-8') . '</strong>';
            $metaParts = [];
            $metaParts[] = 'ID ' . $id;
            $metaParts[] = 'pozice ' . $position;
            echo '<span class="definition-meta">' . implode(' | ', $metaParts) . '</span>';
            echo '</div>';
            echo '<div class="definition-actions">';
            echo '<button type="button" class="definition-action" draggable="false" data-action="create-child" data-parent-id="' . $id . '" data-parent-title="' . htmlspecialchars($node['title'], ENT_QUOTES, 'UTF-8') . '" data-parent-children="' . $childCount . '">Přidat poduzel</button>';
            echo '<button type="button" class="definition-action" draggable="false" data-action="rename" data-id="' . $id . '" data-title="' . htmlspecialchars($node['title'], ENT_QUOTES, 'UTF-8') . '">Přejmenovat</button>';
            echo '<button type="button" class="definition-action definition-action--danger" draggable="false" data-action="delete" data-id="' . $id . '" data-title="' . htmlspecialchars($node['title'], ENT_QUOTES, 'UTF-8') . '">Smazat</button>';
            echo '<span class="definition-drag-indicator" draggable="false" aria-hidden="true">⋮⋮</span>';
            echo '</div>';
            echo '</div>';
            if (!empty($children)) {
                render_definition_nodes($children, $nodePath);
            }
            echo '</li>';
        }
        echo '</ul>';
    }
}
?>
<div id="definitions-list" data-island="definitions-tree" data-base="<?= htmlspecialchars($BASE) ?>">
  <?php if (empty($tree)) : ?>
    <p class="definitions-empty">Zatím nebyly vytvořeny žádné definice.</p>
  <?php else : ?>
      <?php render_definition_nodes($tree); ?>
  <?php endif; ?>
</div>
