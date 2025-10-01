<?php
$tree = $componentsTree ?? [];
$BASE = rtrim((defined('BASE_PATH') ? BASE_PATH : ''), '/');

if (!function_exists('render_component_nodes')) {
    function render_component_nodes(array $nodes, string $path = ''): void
    {
        if (empty($nodes)) {
            return;
        }
        echo '<ul class="component-tree">';
        foreach ($nodes as $node) {
            $id = (int) $node['id'];
            $definitionId = (int) $node['definition_id'];
            $parentId = $node['parent_id'] === null ? '' : (string) (int) $node['parent_id'];
            $position = isset($node['position']) ? (int) $node['position'] : 0;
            $rawEffectiveTitle = (string) ($node['effective_title'] ?? '');
            $effectiveTitle = htmlspecialchars($rawEffectiveTitle, ENT_QUOTES, 'UTF-8');
            $definitionTitleRaw = (string) ($node['definition_title'] ?? '');
            $definitionTitle = htmlspecialchars($definitionTitleRaw, ENT_QUOTES, 'UTF-8');
            $rawAlternateTitle = isset($node['alternate_title']) && $node['alternate_title'] !== null
                ? (string) $node['alternate_title']
                : '';
            $alternateTitle = $rawAlternateTitle !== ''
                ? htmlspecialchars($rawAlternateTitle, ENT_QUOTES, 'UTF-8')
                : '';
            $rawDescription = (string) ($node['description'] ?? '');
            $description = $rawDescription !== ''
                ? htmlspecialchars($rawDescription, ENT_QUOTES, 'UTF-8')
                : '';
            $rawImage = isset($node['image']) ? (string) $node['image'] : '';
            $image = $rawImage !== ''
                ? htmlspecialchars($rawImage, ENT_QUOTES, 'UTF-8')
                : '';
            $rawColor = isset($node['color']) ? (string) $node['color'] : '';
            $color = $rawColor !== ''
                ? htmlspecialchars($rawColor, ENT_QUOTES, 'UTF-8')
                : '';
            $dependencyCount = isset($node['dependency_tree']) && is_array($node['dependency_tree'])
                ? count($node['dependency_tree'])
                : 0;
            $children = $node['children'] ?? [];
            $childCount = is_array($children) ? count($children) : 0;
            $nodePath = ltrim(($path === '' ? '' : $path . '/') . $id, '/');
            $mediaType = $rawColor !== '' ? 'color' : 'image';
            $latestPrice = isset($node['latest_price']) && is_array($node['latest_price'])
                ? $node['latest_price']
                : null;
            $latestAmountRaw = $latestPrice !== null && isset($latestPrice['amount'])
                ? (string) $latestPrice['amount']
                : '';
            $latestCurrencyRaw = $latestPrice !== null && isset($latestPrice['currency'])
                ? (string) $latestPrice['currency']
                : 'CZK';
            $latestCurrency = strtoupper($latestCurrencyRaw);
            $priceHistory = isset($node['price_history']) && is_array($node['price_history'])
                ? array_slice($node['price_history'], 0, 10)
                : [];
            $priceHistoryJsonRaw = json_encode($priceHistory, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($priceHistoryJsonRaw === false) {
                $priceHistoryJsonRaw = '[]';
            }
            $priceHistoryJson = htmlspecialchars($priceHistoryJsonRaw, ENT_QUOTES, 'UTF-8');
            $metaParts = [
                'ID ' . $id,
                'pozice ' . $position,
                'definice ' . $definitionTitle . ' (#' . $definitionId . ')',
            ];
            $metaLine = htmlspecialchars(implode(' | ', $metaParts), ENT_QUOTES, 'UTF-8');

            echo '<li class="component-item"'
                . ' data-id="' . $id . '"'
                . ' data-parent="' . htmlspecialchars($parentId, ENT_QUOTES, 'UTF-8') . '"'
                . ' data-position="' . $position . '"'
                . ' data-path="' . htmlspecialchars($nodePath, ENT_QUOTES, 'UTF-8') . '"'
                . ' data-children-count="' . $childCount . '"'
                . ' data-definition-id="' . $definitionId . '"'
                . '">';
            echo '<div class="component-node">';
            echo '<div class="component-node-header">';
            echo '<div class="component-node-info">';
            echo '<strong>' . $effectiveTitle . '</strong>';
            if ($alternateTitle !== '') {
                echo '<span class="component-alias">alias "' . $alternateTitle . '"</span>';
            }
            echo '<span class="component-meta">' . $metaLine . '</span>';
            echo '</div>';
            echo '<div class="component-actions">';
            echo '<button type="button" class="component-action"'
                . ' data-action="create-child"'
                . ' data-parent-id="' . $id . '"'
                . ' data-parent-title="' . $effectiveTitle . '"'
                . ' data-parent-children="' . $childCount . '"'
                . '">Přidat podkomponentu</button>';
            echo '<button type="button" class="component-action"'
                . ' data-action="edit"'
                . ' data-component-id="' . $id . '"'
                . ' data-title="' . $effectiveTitle . '"'
                . ' data-definition-id="' . $definitionId . '"'
                . ' data-alternate-title="' . htmlspecialchars($rawAlternateTitle, ENT_QUOTES, 'UTF-8') . '"'
                . ' data-description="' . htmlspecialchars($rawDescription, ENT_QUOTES, 'UTF-8') . '"'
                . ' data-image="' . htmlspecialchars($rawImage, ENT_QUOTES, 'UTF-8') . '"'
                . ' data-color="' . htmlspecialchars($rawColor, ENT_QUOTES, 'UTF-8') . '"'
                . ' data-media-type="' . $mediaType . '"'
                . ' data-position="' . $position . '"'
                . ' data-price-amount="' . htmlspecialchars($latestAmountRaw, ENT_QUOTES, 'UTF-8') . '"'
                . ' data-price-currency="' . htmlspecialchars($latestCurrency, ENT_QUOTES, 'UTF-8') . '"'
                . ' data-price-history="' . $priceHistoryJson . '"'
                . '">Upravit</button>';
            echo '<button type="button" class="component-action component-action--danger"'
                . ' data-action="delete"'
                . ' data-id="' . $id . '"'
                . ' data-title="' . $effectiveTitle . '"'
                . '">Smazat</button>';
            echo '</div>';
            echo '</div>';
            $hasDetails = $description !== '' || $image !== '' || $color !== '' || $dependencyCount > 0;
            if ($hasDetails) {
                echo '<dl class="component-node-details">';
                if ($description !== '') {
                    echo '<div><dt>Popis</dt><dd>' . $description . '</dd></div>';
                }
                if ($image !== '') {
                    echo '<div><dt>Obrázek</dt><dd>' . $image . '</dd></div>';
                }
                if ($color !== '') {
                    echo '<div><dt>Barva</dt><dd>'
                        . '<span class="component-color-chip" style="--chip-color:' . $color . ';"></span>'
                        . $color
                        . '</dd></div>';
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
  <?php if (empty($tree)) : ?>
    <p class="components-empty">Zatím nebyly vytvořeny žádné komponenty.</p>
  <?php else : ?>
      <?php render_component_nodes($tree); ?>
  <?php endif; ?>
</div>
