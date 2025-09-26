<?php
$tree = $componentsTree ?? [];
$BASE = rtrim((defined('BASE_PATH') ? BASE_PATH : ''), '/');

if (!function_exists('render_component_nodes')) {
    function render_component_nodes(array $nodes): void {
        if (empty($nodes)) {
            return;
        }
        echo '<ul class="component-tree">';
        foreach ($nodes as $node) {
            $id = (int) $node['id'];
            $definitionId = (int) $node['definition_id'];
            $effectiveTitle = htmlspecialchars($node['effective_title'] ?? '', ENT_QUOTES, 'UTF-8');
            $definitionTitle = htmlspecialchars($node['definition_title'] ?? '', ENT_QUOTES, 'UTF-8');
            $alternateTitle = isset($node['alternate_title']) && $node['alternate_title'] !== null
                ? htmlspecialchars($node['alternate_title'], ENT_QUOTES, 'UTF-8')
                : '';
            $description = htmlspecialchars($node['description'] ?? '', ENT_QUOTES, 'UTF-8');
            $image = isset($node['image']) ? htmlspecialchars((string) $node['image'], ENT_QUOTES, 'UTF-8') : '';
            $dependencyCount = isset($node['dependency_tree']) && is_array($node['dependency_tree'])
                ? count($node['dependency_tree'])
                : 0;
            echo '<li class="component-item" data-id="' . $id . '">';
            echo '<div class="component-node">';
            echo '<div class="component-node__header">';
            echo '<strong>' . $effectiveTitle . '</strong>';
            if ($alternateTitle !== '') {
                echo '<span class="component-node__subtitle">alias „' . $alternateTitle . '“</span>';
            }
            echo '</div>';
            echo '<dl class="component-node__meta">';
            echo '<div><dt>Definice</dt><dd>' . $definitionTitle . ' (#' . $definitionId . ')</dd></div>';
            if ($description !== '') {
                echo '<div><dt>Popis</dt><dd>' . $description . '</dd></div>';
            }
            if ($image !== '') {
                echo '<div><dt>Obrázek</dt><dd>' . $image . '</dd></div>';
            }
            echo '<div><dt>Závislosti</dt><dd>' . $dependencyCount . '</dd></div>';
            echo '</dl>';
            echo '</div>';
            $children = $node['children'] ?? [];
            if (!empty($children)) {
                render_component_nodes($children);
            }
            echo '</li>';
        }
        echo '</ul>';
    }
}
?>
<div id="components-list">
  <?php if (empty($tree)): ?>
    <p class="components-empty">Zatím nebyly vytvořeny žádné komponenty.</p>
  <?php else: ?>
    <?php render_component_nodes($tree); ?>
  <?php endif; ?>
</div>
