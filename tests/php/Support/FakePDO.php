<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PDO;
use RuntimeException;

class FakePDO extends PDO
{
    /**
     * @var array<int,array<string,mixed>>
     */
    public array $rows = [];

    /**
     * @var array<int,array{type:string,action:string}>
     */
    public array $operations = [];

    /**
     * @var array<int,array{sql:string,params:array<string,mixed>}>
     */
    public array $executions = [];

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    public function __construct(array $rows)
    {
        $this->rows = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $normalized = $row;
            $normalized['id'] = $id;
            $normalized['position'] = (int) $row['position'];
            $normalized['parent_id'] = array_key_exists('parent_id', $row) && $row['parent_id'] !== null
                ? (int) $row['parent_id']
                : null;
            $this->rows[$id] = $normalized;
        }
    }

    public function beginTransaction(): bool
    {
        $this->operations[] = ['type' => 'transaction', 'action' => 'begin'];
        return true;
    }

    public function commit(): bool
    {
        $this->operations[] = ['type' => 'transaction', 'action' => 'commit'];
        return true;
    }

    public function rollBack(): bool
    {
        $this->operations[] = ['type' => 'transaction', 'action' => 'rollback'];
        return true;
    }

    /**
     * @param string $query
     * @param array<int|string, mixed> $options
     * @return \PDOStatement|false
     */
    #[\ReturnTypeWillChange]
    public function prepare($query, $options = [])
    {
        return new FakeStatement($this, (string) $query);
    }

    /**
     * @param array<string,mixed> $params
     * @return array<int,array<string|int,mixed>>
     */
    public function executeQuery(string $query, array $params): array
    {
        $this->executions[] = ['sql' => $query, 'params' => $params];
        $normalizedQuery = trim(preg_replace('/\s+/', ' ', $query) ?? '');
        $prefix = 'SELECT id, parent_id, title, position, meta, created_at, updated_at FROM definitions WHERE id =';
        if (
            strncmp(
                $normalizedQuery,
                $prefix,
                strlen($prefix)
            ) === 0
        ) {
            $id = isset($params[':id']) ? (int) $params[':id'] : 0;
            $row = $this->rows[$id] ?? null;
            return $row ? [$row] : [];
        }

        if ($normalizedQuery === 'SELECT parent_id FROM definitions WHERE id = :id') {
            $id = isset($params[':id']) ? (int) $params[':id'] : 0;
            $row = $this->rows[$id] ?? null;
            if (!$row) {
                return [];
            }
            return [[0 => $row['parent_id']]];
        }

        if ($normalizedQuery === 'SELECT 1 FROM definitions WHERE id = :id LIMIT 1') {
            $id = isset($params[':id']) ? (int) $params[':id'] : 0;
            return isset($this->rows[$id]) ? [[0 => 1]] : [];
        }

        if ($normalizedQuery === 'SELECT COUNT(*) FROM definitions WHERE parent_id <=> :parent') {
            $parent = $params[':parent'] ?? null;
            $count = 0;
            foreach ($this->rows as $row) {
                if ($this->parentMatches($row['parent_id'], $parent)) {
                    $count++;
                }
            }
            return [[0 => $count]];
        }

        if ($normalizedQuery === 'SELECT COALESCE(MAX(position), -1) FROM definitions WHERE parent_id <=> :parent') {
            $parent = $params[':parent'] ?? null;
            $max = -1;
            foreach ($this->rows as $row) {
                if ($this->parentMatches($row['parent_id'], $parent)) {
                    $max = max($max, (int) $row['position']);
                }
            }
            return [[0 => $max]];
        }

        if (
            $normalizedQuery ===
            'SELECT id FROM definitions WHERE parent_id <=> :parent ORDER BY position FOR UPDATE'
        ) {
            $parent = $params[':parent'] ?? null;
            $ids = $this->selectIdsForParent($parent);
            return array_map(static fn(int $id): array => ['id' => $id], $ids);
        }

        if ($normalizedQuery === 'SELECT id FROM definitions WHERE parent_id <=> :parent ORDER BY position, id') {
            $parent = $params[':parent'] ?? null;
            $ids = $this->selectIdsForParent($parent);
            return array_map(static fn(int $id): array => ['id' => $id], $ids);
        }

        if ($normalizedQuery === 'UPDATE definitions SET position = :position WHERE id = :id') {
            $id = isset($params[':id']) ? (int) $params[':id'] : 0;
            if (isset($this->rows[$id])) {
                $this->rows[$id]['position'] = (int) $params[':position'];
            }
            return [];
        }

        if (
            $normalizedQuery ===
            'UPDATE definitions SET position = position - 1 ' .
            'WHERE parent_id <=> :parent AND id <> :id AND position > :position'
        ) {
            $parent = $params[':parent'] ?? null;
            $skipId = isset($params[':id']) ? (int) $params[':id'] : 0;
            $threshold = isset($params[':position']) ? (int) $params[':position'] : 0;
            foreach ($this->rows as $id => &$row) {
                if ($id === $skipId) {
                    continue;
                }
                if ($this->parentMatches($row['parent_id'], $parent) && (int) $row['position'] > $threshold) {
                    $row['position'] = (int) $row['position'] - 1;
                }
            }
            unset($row);
            return [];
        }

        if (
            $normalizedQuery ===
            'UPDATE definitions SET position = position + 1 WHERE parent_id <=> :parent AND position >= :position'
        ) {
            $parent = $params[':parent'] ?? null;
            $threshold = isset($params[':position']) ? (int) $params[':position'] : 0;
            foreach ($this->rows as &$row) {
                if ($this->parentMatches($row['parent_id'], $parent) && (int) $row['position'] >= $threshold) {
                    $row['position'] = (int) $row['position'] + 1;
                }
            }
            unset($row);
            return [];
        }

        if ($normalizedQuery === 'UPDATE definitions SET parent_id = :parent, position = :position WHERE id = :id') {
            $id = isset($params[':id']) ? (int) $params[':id'] : 0;
            if (isset($this->rows[$id])) {
                $this->rows[$id]['parent_id'] = $params[':parent'] === null ? null : (int) $params[':parent'];
                $this->rows[$id]['position'] = (int) $params[':position'];
            }
            return [];
        }

        if ($normalizedQuery === 'UPDATE definitions SET position = position + 1000000 WHERE parent_id <=> :parent') {
            $parent = $params[':parent'] ?? null;
            foreach ($this->rows as &$row) {
                if ($this->parentMatches($row['parent_id'], $parent)) {
                    $row['position'] = (int) $row['position'] + 1000000;
                }
            }
            unset($row);
            return [];
        }

        throw new RuntimeException('Unsupported query: ' . $query);
    }

    /**
     * @param mixed $rowParent
     * @param mixed $param
     */
    private function parentMatches($rowParent, $param): bool
    {
        if ($rowParent === null && ($param === null || $param === '')) {
            return true;
        }
        if ($rowParent === null) {
            return false;
        }
        if ($param === null || $param === '') {
            return false;
        }
        return (int) $rowParent === (int) $param;
    }

    /**
     * @param mixed $parent
     * @return array<int,int>
     */
    private function selectIdsForParent($parent): array
    {
        $result = [];
        foreach ($this->rows as $row) {
            if ($this->parentMatches($row['parent_id'], $parent)) {
                $result[] = $row;
            }
        }
        usort($result, static function (array $a, array $b): int {
            if ($a['position'] === $b['position']) {
                return $a['id'] <=> $b['id'];
            }
            return $a['position'] <=> $b['position'];
        });
        return array_map(static fn(array $row): int => (int) $row['id'], $result);
    }
}
