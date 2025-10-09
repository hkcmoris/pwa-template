<?php

declare(strict_types=1);

namespace Components;

use PDO;

use function log_message;

final class QueryService
{
    private PDO $pdo;

    private Formatter $formatter;

    public function __construct(PDO $pdo, Formatter $formatter)
    {
        $this->pdo = $pdo;
        $this->formatter = $formatter;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchRows(?int $limit = null, int $offset = 0): array
    {
        $sql = <<<'SQL'
        SELECT
            c.id,
            c.definition_id,
            c.parent_id,
            c.alternate_title,
            c.description,
            c.image,
            c.color,
            c.dependency_tree,
            c.position,
            c.created_at,
            c.updated_at,
            d.title AS definition_title
        FROM components c
        INNER JOIN definitions d ON d.id = c.definition_id
        ORDER BY (c.parent_id IS NULL) DESC, c.parent_id, c.position, c.id
        SQL;

        if ($limit !== null) {
            if ($limit <= 0) {
                $limit = 1;
            }

            if ($offset < 0) {
                $offset = 0;
            }

            $sql .= ' LIMIT :limit OFFSET :offset';
        }

        $stmt = $this->pdo->prepare($sql);

        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }

        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $componentIds = array_map(static fn($row) => isset($row['id']) ? (int) $row['id'] : 0, $rows);
        $priceHistoryMap = $this->fetchPriceHistory($componentIds);
        $needsMetadata = $limit !== null;
        $childrenCountMap = $needsMetadata ? $this->fetchChildrenCounts($componentIds) : [];
        $depthMap = $needsMetadata ? $this->computeDepthMap($componentIds) : [];
        $normalised = [];

        foreach ($rows as $row) {
            $row['dependency_tree'] = $this->formatter->normaliseDependencyTree($row['dependency_tree'] ?? null);
            $row['effective_title'] = $this->formatter->effectiveTitle($row);
            $rowId = isset($row['id']) ? (int) $row['id'] : 0;
            $history = $priceHistoryMap[$rowId] ?? [];
            $row['price_history'] = $history;
            $row['latest_price'] = $history[0] ?? null;
            $row['children_count'] = $childrenCountMap[$rowId] ?? 0;
            $row['depth'] = $depthMap[$rowId] ?? 0;
            $normalised[] = $row;
        }

        log_message('Fetched ' . count($normalised) . ' components from database', 'DEBUG');

        return $normalised;
    }

    /**
     * @param array<int, int|string> $componentIds
     * @return array<int, array<int, array{amount: string, currency: string, created_at: string}>>
     */
    public function fetchPriceHistory(array $componentIds, int $limitPerComponent = 10): array
    {
        $mappedIds = array_map(static fn($id) => (int) $id, $componentIds);
        $uniqueIds = array_values(
            array_filter(
                array_unique($mappedIds),
                static fn($id) => $id > 0
            )
        );

        if (empty($uniqueIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($uniqueIds), '?'));
        $sql = 'SELECT component_id, amount, currency, created_at'
            . ' FROM prices'
            . ' WHERE component_id IN (' . $placeholders . ')'
            . ' ORDER BY component_id, created_at DESC';
        $stmt = $this->pdo->prepare($sql);

        foreach ($uniqueIds as $index => $componentId) {
            $stmt->bindValue($index + 1, $componentId, PDO::PARAM_INT);
        }

        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $history = [];

        foreach ($rows as $row) {
            $componentId = isset($row['component_id']) ? (int) $row['component_id'] : 0;
            if ($componentId <= 0) {
                continue;
            }

            if (!isset($history[$componentId])) {
                $history[$componentId] = [];
            }

            if (count($history[$componentId]) >= $limitPerComponent) {
                continue;
            }

            $amount = isset($row['amount']) ? (string) $row['amount'] : '';
            $currency = array_key_exists('currency', $row) && $row['currency'] !== null
                ? strtoupper((string) $row['currency'])
                : 'CZK';
            $createdAt = isset($row['created_at']) ? (string) $row['created_at'] : '';

            $history[$componentId][] = [
                'amount' => $amount,
                'currency' => $currency,
                'created_at' => $createdAt,
            ];
        }

        return $history;
    }

    /**
     * @param array<int, int> $componentIds
     * @return array<int, int>
     */
    private function fetchChildrenCounts(array $componentIds): array
    {
        $uniqueIds = array_values(
            array_filter(
                array_unique(array_map(static fn($id) => (int) $id, $componentIds)),
                static fn($id) => $id > 0
            )
        );

        if ($uniqueIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($uniqueIds), '?'));
        $sql = 'SELECT parent_id, COUNT(*) AS total '
               . 'FROM components '
               . 'WHERE parent_id IN (' . $placeholders . ') '
               . 'GROUP BY parent_id';
        $stmt = $this->pdo->prepare($sql);

        foreach ($uniqueIds as $index => $componentId) {
            $stmt->bindValue($index + 1, $componentId, PDO::PARAM_INT);
        }

        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $counts = [];

        foreach ($rows as $row) {
            if (!isset($row['parent_id'])) {
                continue;
            }

            $key = (int) $row['parent_id'];
            $counts[$key] = isset($row['total']) ? (int) $row['total'] : 0;
        }

        return $counts;
    }

    /**
     * @param array<int, int> $componentIds
     * @return array<int, int>
     */
    private function computeDepthMap(array $componentIds): array
    {
        $ids = array_values(
            array_filter(
                array_unique(array_map(static fn($id) => (int) $id, $componentIds)),
                static fn($id) => $id > 0
            )
        );

        if ($ids === []) {
            return [];
        }

        $depths = [];
        $stmt = $this->pdo->prepare('SELECT parent_id FROM components WHERE id = :id LIMIT 1');

        $computeDepth = function (int $id) use (&$depths, $stmt, &$computeDepth): int {
            if (isset($depths[$id])) {
                return $depths[$id];
            }

            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $parent = $stmt->fetchColumn();
            $stmt->closeCursor();

            if ($parent === false || $parent === null) {
                $depths[$id] = 0;

                return 0;
            }

            $depth = $computeDepth((int) $parent) + 1;
            $depths[$id] = $depth;

            return $depth;
        };

        foreach ($ids as $id) {
            $computeDepth($id);
        }

        return $depths;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $sql = <<<'SQL'
        SELECT
            c.id,
            c.definition_id,
            c.parent_id,
            c.alternate_title,
            c.description,
            c.image,
            c.color,
            c.dependency_tree,
            c.position,
            c.created_at,
            c.updated_at,
            d.title AS definition_title
        FROM components c
        INNER JOIN definitions d ON d.id = c.definition_id
        WHERE c.id = :id
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['dependency_tree'] = $this->formatter->normaliseDependencyTree($row['dependency_tree'] ?? null);
        $row['effective_title'] = $this->formatter->effectiveTitle($row);
        $priceHistory = $this->fetchPriceHistory([$id]);
        $history = $priceHistory[$id] ?? [];
        $row['price_history'] = $history;
        $row['latest_price'] = $history[0] ?? null;

        return $row;
    }

    public function parentExists(int $parentId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM components WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $parentId, PDO::PARAM_INT);
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }

    public function childrenCount(?int $parentId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM components WHERE parent_id <=> :parent');

        if ($parentId === null) {
            $stmt->bindValue(':parent', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':parent', $parentId, PDO::PARAM_INT);
        }

        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function nextPosition(?int $parentId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(position), -1) FROM components WHERE parent_id <=> :parent');

        if ($parentId === null) {
            $stmt->bindValue(':parent', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':parent', $parentId, PDO::PARAM_INT);
        }

        $stmt->execute();
        $max = (int) $stmt->fetchColumn();

        return $max + 1;
    }

    public function countAll(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM components');

        return (int) $stmt->fetchColumn();
    }

    public function fetchParentId(int $id): ?int
    {
        $stmt = $this->pdo->prepare('SELECT parent_id FROM components WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $parent = $stmt->fetchColumn();
        $stmt->closeCursor();

        if ($parent === false || $parent === null) {
            return null;
        }

        return (int) $parent;
    }
}
