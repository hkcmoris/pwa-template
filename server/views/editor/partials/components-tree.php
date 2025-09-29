<?php
$tree = $componentsTree ?? [];
$BASE = rtrim((defined('BASE_PATH') ? BASE_PATH : ''), '/');

if (!function_exists('render_component_nodes')) {
    function render_component_nodes(array $nodes, string $path = ''): void {
        if (empty($nodes)) {
            return;
        }
        echo '<ul class="component-tree">';
        foreach ($nodes as $node) {
            $id = (int) $node['id'];
            $definitionId = (int) $node['definition_id'];
            $parentId = $node['parent_id'] === null ? '' : (string) (int) $node['parent_id'];
            $position = isset($node['position']) ? (int) $node['position'] : 0;
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
            $children = $node['children'] ?? [];
            $childCount = is_array($children) ? count($children) : 0;
            $nodePath = ltrim(($path === '' ? '' : $path . '/') . $id, '/');
            $metaParts = [
                'ID ' . $id,
                'pozice ' . $position,
                'definice ' . $definitionTitle . ' (#' . $definitionId . ')',
            ];
            $metaLine = htmlspecialchars(implode(' | ', $metaParts), ENT_QUOTES, 'UTF-8');

            echo '<li class="component-item" data-id="' . $id . '" data-parent="' . htmlspecialchars($parentId, ENT_QUOTES, 'UTF-8') . '" data-position="' . $position . '" data-path="' . htmlspecialchars($nodePath, ENT_QUOTES, 'UTF-8') . '" data-children-count="' . $childCount . '" data-definition-id="' . $definitionId . '">';
            echo '<div class="component-node">';
            echo '<div class="component-node__header">';
            echo '<div class="component-node__info">';
            echo '<strong>' . $effectiveTitle . '</strong>';
            if ($alternateTitle !== '') {
                echo '<span class="component-alias">alias "' . $alternateTitle . '"</span>';
            }
            echo '<span class="component-meta">' . $metaLine . '</span>';
            echo '</div>';
            echo '<div class="component-actions">';
            echo '<button type="button" class="component-action" data-action="create-child" data-parent-id="' . $id . '" data-parent-title="' . $effectiveTitle . '" data-parent-children="' . $childCount . '">Přidat podkomponentu</button>';
            echo '<button type="button" class="component-action" data-action="edit" data-id="' . $id . '" data-title="' . $effectiveTitle . '" data-definition-id="' . $definitionId . '" data-alternate-title="' . $alternateTitle . '" data-description="' . $description . '" data-image="' . $image . '" data-position="' . $position . '">Upravit</button>';
            echo '<button type="button" class="component-action component-action--danger" data-action="delete" data-id="' . $id . '" data-title="' . $effectiveTitle . '">Smazat</button>';
            echo '</div>';
            echo '</div>';
            $hasDetails = $description !== '' || $image !== '' || $dependencyCount > 0;
            if ($hasDetails) {
                echo '<dl class="component-node__details">';
                if ($description !== '') {
                    echo '<div><dt>Popis</dt><dd>' . $description . '</dd></div>';
                }
                if ($image !== '') {
                    echo '<div><dt>Obrázek</dt><dd>' . $image . '</dd></div>';
                }
                echo '<div><dt>Závislosti</dt><dd>' . $dependencyCount . '</dd></div>';
                echo '</dl>';
            }
            echo '</div>';
            if (!empty($children)) {
                render_component_nodes($children, $nodePath);
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