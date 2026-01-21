<?php
$tree = $definitionsTree ?? [];
$BASE = rtrim((defined('BASE_PATH') ? BASE_PATH : ''), '/');

if (!function_exists('definitions_normalise_meta_array')) {
    /**
     * @param mixed $meta
     * @return array<string, mixed>|null
     */
    function definitions_normalise_meta_array($meta): ?array
    {
        if (is_array($meta)) {
            return $meta;
        }

        if (is_string($meta) && $meta !== '') {
            $decoded = json_decode($meta, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}

if (!function_exists('definitions_extract_value_range')) {
    /**
     * @param mixed $meta
     * @return array{min: int|null, max: int|null}|null
     */
    function definitions_extract_value_range($meta): ?array
    {
        $data = definitions_normalise_meta_array($meta);

        if ($data === null) {
            return null;
        }

        if (isset($data['value_range']) && is_array($data['value_range'])) {
            $data = array_merge($data, $data['value_range']);
        }

        $normaliseInt = static function ($value): ?int {
            if ($value === null) {
                return null;
            }

            if (is_int($value)) {
                return $value;
            }

            if (is_float($value)) {
                return (int) $value;
            }

            if (is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed === '') {
                    return null;
                }

                if (preg_match('/^-?\d+$/', $trimmed) === 1) {
                    return (int) $trimmed;
                }
            }

            return null;
        };

        $min = $normaliseInt($data['value_min'] ?? $data['min'] ?? $data['from'] ?? null);
        $max = $normaliseInt($data['value_max'] ?? $data['max'] ?? $data['to'] ?? null);

        if ($min === null && $max === null) {
            return null;
        }

        return ['min' => $min, 'max' => $max];
    }
}

if (!function_exists('definitions_format_value_range')) {
    /**
     * @param array{min: int|null, max: int|null}|null $range
     */
    function definitions_format_value_range(?array $range): ?string
    {
        if ($range === null) {
            return null;
        }

        $min = $range['min'];
        $max = $range['max'];

        if ($min !== null && $max !== null) {
            if ($min === $max) {
                return (string) $min;
            }

            return $min . '–' . $max;
        }

        if ($min !== null) {
            return '≥ ' . $min;
        }

        if ($max !== null) {
            return '≤ ' . $max;
        }

        return null;
    }
}

if (!function_exists('render_definition_nodes')) {
    /**
     * @param array<int, array<string, mixed>> $nodes
     */
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
            $nodePath = ltrim(($path === '' ? '' : $path . '/') . $id, '/');
            $range = definitions_extract_value_range($node['meta'] ?? null);
            $rangeLabel = definitions_format_value_range($range);
            $rangeAttributes = '';
            if ($range !== null) {
                if ($range['min'] !== null) {
                    $rangeAttributes .= ' data-value-min="' . (int) $range['min'] . '"';
                }
                if ($range['max'] !== null) {
                    $rangeAttributes .= ' data-value-max="' . (int) $range['max'] . '"';
                }
            }
            echo '<li class="definition-item"'
                . ' data-id="' . $id . '"'
                . ' data-parent="' . $parentId . '"'
                . ' data-position="' . $position . '"'
                . ' data-path="' . htmlspecialchars($nodePath, ENT_QUOTES, 'UTF-8') . '"'
                . ' data-title="' . htmlspecialchars($node['title'], ENT_QUOTES, 'UTF-8') . '"'
                . ($range !== null ? ' data-has-range="true"' : '')
                . $rangeAttributes
                . '>';
            echo '<div class="definition-node" draggable="true">';
            echo '<div class="definition-position">' . $position . '</div>';
            echo '<div class="definition-node-info">';
            echo '<strong>' . htmlspecialchars($node['title'], ENT_QUOTES, 'UTF-8') . '</strong>';
            if ($rangeLabel !== null) {
                echo ' <span class="definition-range-label">Rozsah: [ '
                    . htmlspecialchars($rangeLabel, ENT_QUOTES, 'UTF-8')
                    . ' ]</span>';
            }
            echo '</div>';
            echo '<div class="definition-actions">';
            if ($range === null) {
                echo '<button type="button" class="definition-action"'
                    . ' data-action="create-child"'
                    . '>'
                    . '<svg'
                    . ' fill="currentColor"'
                    . ' width="16px"'
                    . ' height="16px"'
                    . ' display="block"'
                    . ' style="display: block;"'
                    . '>'
                    . '<use href="#icon-add"></use>'
                    . '</svg>'
                    . '</button>';
            }
            $rangeButtonLabel = $range === null ? 'Nastavit rozsah' : 'Upravit rozsah';
            echo '<button type="button" class="definition-action"'
                . ' data-action="configure-range"'
                . '>'
                . '<svg'
                . ' fill="currentColor"'
                . ' width="32px"'
                . ' height="32px"'
                . ' display="block"'
                . ' style="display: block; margin: -8px;"'
                . '>'
                . '<use href="#icon-range"></use>'
                . '</svg>'
                // . $rangeButtonLabel
                . '</button>';
            echo '<button type="button" class="definition-action"'
                . ' data-action="rename"'
                . '>'
                . '<svg'
                . ' fill="currentColor"'
                . ' width="16px"'
                . ' height="16px"'
                . ' display="block"'
                . ' style="display: block;"'
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
                . ' style="display: block;"'
                . '>'
                . '<use href="#icon-trash"></use>'
                . '</svg>'
                . '</button>';
            echo '<span class="definition-drag-indicator" aria-hidden="true">⋮⋮</span>';
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
