<?php

declare(strict_types=1);

namespace Components;

use function log_message;

final class Formatter
{
    /**
     * @return array{0: ?string, 1: ?string}
     */
    public function normalisePriceInput(?string $rawInput): array
    {
        $value = $rawInput !== null ? trim((string) $rawInput) : '';

        if ($value === '') {
            return [null, null];
        }

        $sanitised = str_replace(',', '.', preg_replace('/\s+/', '', $value));

        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $sanitised)) {
            return [null, 'Cena musí být nezáporné číslo s nejvýše dvěma desetinnými místy.'];
        }

        [$whole, $fraction] = array_pad(explode('.', $sanitised, 2), 2, '');

        if ($fraction === '') {
            $fraction = '00';
        } else {
            $fraction = str_pad(substr($fraction, 0, 2), 2, '0');
        }

        $normalisedWhole = ltrim($whole, '0');

        if ($normalisedWhole === '') {
            $normalisedWhole = '0';
        }

        if (strlen($normalisedWhole) > 10) {
            return [null, 'Cena je příliš vysoká.'];
        }

        return [$normalisedWhole . '.' . $fraction, null];
    }

    /**
     * @param mixed $raw
     * @return array<int, mixed>
     */
    public function normaliseDependencyTree($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $row
     */
    public function effectiveTitle(array $row): string
    {
        $alt = isset($row['alternate_title']) ? trim((string) $row['alternate_title']) : '';

        if ($alt !== '') {
            return $alt;
        }

        return isset($row['definition_title']) ? (string) $row['definition_title'] : '';
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupByParent(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $key = $row['parent_id'] === null ? 'root' : (string) $row['parent_id'];
            $grouped[$key][] = $row;
        }

        return $grouped;
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $grouped
     * @return array<int, array<string, mixed>>
     */
    private function buildBranch(array $grouped, string $key): array
    {
        if (!isset($grouped[$key])) {
            return [];
        }

        $branch = [];

        foreach ($grouped[$key] as $row) {
            $childKey = (string) $row['id'];
            $row['children'] = $this->buildBranch($grouped, $childKey);
            $branch[] = $row;
        }

        return $branch;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    public function buildTree(array $rows): array
    {
        $grouped = $this->groupByParent($rows);
        $tree = $this->buildBranch($grouped, 'root');
        log_message('Built component tree with ' . count($tree) . ' root nodes', 'DEBUG');

        return $tree;
    }

    /**
     * @param array<int, array<string, mixed>> $tree
     * @return array<int, array<string, mixed>>
     */
    public function flattenTree(array $tree, int $depth = 0): array
    {
        $flat = [];

        foreach ($tree as $node) {
            $children = $node['children'] ?? [];
            $copy = $node;
            $copy['depth'] = $depth;
            unset($copy['children']);
            $flat[] = $copy;

            if (!empty($children)) {
                $flat = array_merge($flat, $this->flattenTree($children, $depth + 1));
            }
        }

        return $flat;
    }
}
