<?php

declare(strict_types=1);

namespace Components;

use PDO;

final class ComponentStatsQuery
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
    * @param array<int, int> $componentIds
    * @return array<int, int>
    */
    public function fetchChildrenCounts(array $componentIds): array
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
    public function computeDepthMap(array $componentIds): array
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
}
