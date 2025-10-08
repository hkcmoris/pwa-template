<?php

declare(strict_types=1);

namespace Definitions;

use function log_message;

final class Formatter
{
    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public function buildTree(array $rows): array
    {
        /** @var array<string, list<array<string, mixed>>> $grouped */
        $grouped = [];
        foreach ($rows as $row) {
            $key = $row['parent_id'] === null ? 'root' : (string) $row['parent_id'];
            $grouped[$key][] = $row;
        }
        log_message('Grouped definitions into ' . count($grouped) . ' parent categories', 'DEBUG');
        return $this->buildBranch($grouped, 'root');
    }

    /**
     * @param list<array<string, mixed>> $tree
     * @return list<array<string, mixed>>
     */
    public function flattenTree(array $tree, int $depth = 0): array
    {
        $flat = [];
        foreach ($tree as $node) {
            $children = $node['children'] ?? [];
            $copy = $node;
            unset($copy['children']);
            $copy['depth'] = $depth;
            $flat[] = $copy;
            if (!empty($children)) {
                $flat = array_merge($flat, $this->flattenTree($children, $depth + 1));
            }
        }
        return $flat;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $grouped
     * @return list<array<string, mixed>>
     */
    private function buildBranch(array $grouped, string $key): array
    {
        if (!isset($grouped[$key])) {
            return [];
        }
        $branch = [];
        foreach ($grouped[$key] as $row) {
            $childKey = (string) $row['id'];
            $node = $row;
            $node['children'] = $this->buildBranch($grouped, $childKey);
            $branch[] = $node;
        }
        return $branch;
    }
}
