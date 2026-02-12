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
     * @return array{0: array<int, mixed>, 1: ?string}
     */
    public function normaliseDependencyTreeInput(?string $rawInput): array
    {
        $value = $rawInput !== null ? trim($rawInput) : '';

        if ($value === '') {
            return [[], null];
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return [[], 'Závislosti musí být platné JSON pole.'];
        }

        return [$decoded, null];
    }

    /**
     * @return array{0: array<int, array{name: string, value: string, unit: string}>, 1: ?string}
     */
    public function normalisePropertiesInput(?string $rawInput): array
    {
        $value = $rawInput !== null ? trim($rawInput) : '';

        if ($value === '') {
            return [[], null];
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return [[], 'Vlastnosti musí být ve formátu JSON pole.'];
        }

        return [$this->normaliseProperties($decoded), null];
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
     * @param mixed $raw
     * @return array<int, string>
     */
    public function normaliseImages($raw): array
    {
        $values = [];

        if (is_array($raw)) {
            $values = $raw;
        } elseif (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $values = $decoded;
            } else {
                $values = [$raw];
            }
        }

        $normalised = [];

        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }

            $trimmed = trim($value);

            if ($trimmed === '') {
                continue;
            }

            if (!in_array($trimmed, $normalised, true)) {
                $normalised[] = $trimmed;
            }
        }

        return $normalised;
    }

    /**
     * @param mixed $raw
     * @return array<int, array{name: string, value: string, unit: string}>
     */
    public function normaliseProperties($raw): array
    {
        if (!is_array($raw)) {
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $raw = $decoded;
                } else {
                    return [];
                }
            } else {
                return [];
            }
        }

        $normalised = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $name = isset($entry['name']) ? trim((string) $entry['name']) : '';
            $propertyValue = isset($entry['value']) ? trim((string) $entry['value']) : '';
            $unit = isset($entry['unit']) ? trim((string) $entry['unit']) : '';

            if ($name === '' && $propertyValue === '' && $unit === '') {
                continue;
            }

            $normalised[] = [
                'name' => mb_substr($name, 0, 120, 'UTF-8'),
                'value' => mb_substr($propertyValue, 0, 120, 'UTF-8'),
                'unit' => mb_substr($unit, 0, 32, 'UTF-8'),
            ];
        }

        return $normalised;
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
    private function buildBranch(
        array $grouped,
        string $key,
        string $idPath,
        string $posPath
    ): array {
        if (!isset($grouped[$key])) {
            return [];
        }

        $branch = [];

        foreach ($grouped[$key] as $row) {
            $childKey = (string) $row['id'];
            $nodeIdPath = $idPath === '' ? (string) $row['id'] : $idPath . '/' . $row['id'];
            $nodePosPath = $posPath === '' ? (string) $row['position'] : $posPath . '.' . $row['position'];
            $row['id_path'] = $nodeIdPath;
            $row['pos_path'] = $nodePosPath;
            $row['children'] = $this->buildBranch($grouped, $childKey, $nodeIdPath, $nodePosPath);
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
        $tree = $this->buildBranch($grouped, 'root', '', '');

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
            $children = isset($node['children']) && is_array($node['children']) ? $node['children'] : [];
            $copy = $node;
            $copy['depth'] = $depth;
            $copy['children_count'] = count($children);
            unset($copy['children']);
            $flat[] = $copy;

            if (!empty($children)) {
                $flat = array_merge($flat, $this->flattenTree($children, $depth + 1));
            }
        }

        return $flat;
    }
}
