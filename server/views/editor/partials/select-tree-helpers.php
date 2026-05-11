<?php

if (!function_exists('editor_select_tree_sibling_key')) {
    /**
     * @param array<string, mixed> $item
     */
    function editor_select_tree_sibling_key(array $item, int $depth): string
    {
        $parentId = $item['parent_id'] ?? null;
        $parentKey = $parentId === null ? 'root' : (string) (int) $parentId;
        return $depth . '|' . $parentKey;
    }
}

if (!function_exists('editor_select_tree_sibling_totals')) {
    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, int>
     */
    function editor_select_tree_sibling_totals(array $items): array
    {
        $totals = [];
        foreach ($items as $item) {
            $depth = isset($item['depth']) ? (int) $item['depth'] : 0;
            $depth = max(0, min($depth, 12));
            if ($depth === 0) {
                continue;
            }
            $key = editor_select_tree_sibling_key($item, $depth);
            $totals[$key] = ($totals[$key] ?? 0) + 1;
        }
        return $totals;
    }
}

if (!function_exists('editor_select_tree_option_class')) {
    /**
     * @param array<string, mixed> $item
     * @param array<string, int> $totals
     * @param array<string, mixed> $state
     */
    function editor_select_tree_option_class(
        array $item,
        int $depth,
        array $totals,
        array &$state
    ): string {
        foreach (array_keys($state['active_guides']) as $guideDepth) {
            if ($guideDepth >= $depth) {
                unset($state['active_guides'][$guideDepth]);
            }
        }

        $classes = 'depth-' . $depth;
        for ($guideDepth = 1; $guideDepth < $depth; $guideDepth++) {
            if (!empty($state['active_guides'][$guideDepth])) {
                $classes .= ' select-option--guide-' . $guideDepth;
            }
        }

        if ($depth === 0) {
            $state['previous_depth'] = $depth;
            $state['previous_key'] = null;
            return $classes;
        }

        $key = editor_select_tree_sibling_key($item, $depth);
        $index = $state['seen'][$key] ?? 0;
        $state['seen'][$key] = $index + 1;
        $lastIndex = ($totals[$key] ?? 1) - 1;
        $isLast = $index === $lastIndex;
        $connectFromPreviousMid = $index > 0
            && $state['previous_depth'] === $depth
            && $state['previous_key'] === $key;

        $classes .= ' select-option--branch';
        $classes .= $connectFromPreviousMid
            ? ' select-option--branch-from-mid'
            : ' select-option--branch-from-top';
        if ($index === 0) {
            $classes .= ' select-option--branch-first';
        }
        if ($isLast) {
            $classes .= ' select-option--branch-last';
        }
        $state['active_guides'][$depth] = !$isLast;
        $state['previous_depth'] = $depth;
        $state['previous_key'] = $key;
        return $classes;
    }
}

if (!function_exists('editor_select_tree_initial_state')) {
    /**
     * @return array<string, mixed>
     */
    function editor_select_tree_initial_state(): array
    {
        return [
            'seen' => [],
            'active_guides' => [],
            'previous_depth' => null,
            'previous_key' => null,
        ];
    }
}

if (!function_exists('editor_select_tree_svg')) {
    function editor_select_tree_svg(int $depth, string $optionClass): string
    {
        if ($depth <= 0) {
            return '';
        }

        $width = ($depth * 16) + 16;
        $paths = [];

        for ($guideDepth = 1; $guideDepth < $depth; $guideDepth++) {
            if (strpos($optionClass, 'select-option--guide-' . $guideDepth) !== false) {
                $x = 10 + (($guideDepth - 1) * 16);
                $paths[] = '<path d="M ' . $x . ' 0 V 100" />';
            }
        }

        $x = 10 + (($depth - 1) * 16);
        $yStart = 0;//strpos($optionClass, 'select-option--branch-from-mid') !== false ? -50 : 0;
        $isLast = strpos($optionClass, 'select-option--branch-last') !== false;
        if ($isLast) {
            $paths[] = '<path d="M ' . $x . ' ' . $yStart . ' V 15 Q '
                . $x . ' 50 ' . ($x + 8) . ' 50 H ' . ($x + 10) . '" />';
        } else {
            $paths[] = '<path d="M ' . $x . ' ' . $yStart . ' V 100 M '
                . $x . ' 50 H ' . ($x + 10) . '" />';
        }

        return '<svg class="select-option-tree" style="--tree-width: ' . $width . 'px"'
            . ' viewBox="0 0 ' . $width . ' 100" preserveAspectRatio="none"'
            . ' aria-hidden="true" focusable="false">'
            . '<g>' . implode('', $paths) . '</g></svg>';
    }
}

if (!function_exists('editor_select_tree_label')) {
    function editor_select_tree_label(string $title, int $id): string
    {
        return '<span class="select-option-label">'
            . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
            . '</span> <span class="select-option-meta">(ID '
            . $id
            . ')</span>';
    }
}
