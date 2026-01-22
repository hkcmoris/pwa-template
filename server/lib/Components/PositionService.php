<?php

declare(strict_types=1);

namespace Components;

use PDO;

final class PositionService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Reorders the positions of all components under the given parent ID
     * to ensure they are sequential starting from 0.
     *
     * @param int|null $parentId The ID of the parent component, or null for root components.
     */
    public function reorderPositions(?int $parentId): void
    {
        $bump = $this->pdo->prepare('UPDATE components SET position = position + 1000000 WHERE parent_id <=> :parent');

        if ($parentId === null) {
            $bump->bindValue(':parent', null, PDO::PARAM_NULL);
        } else {
            $bump->bindValue(':parent', $parentId, PDO::PARAM_INT);
        }

        $bump->execute();

        $stmt = $this->pdo->prepare('SELECT id FROM components WHERE parent_id <=> :parent ORDER BY position, id');

        if ($parentId === null) {
            $stmt->bindValue(':parent', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':parent', $parentId, PDO::PARAM_INT);
        }

        $stmt->execute();
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $update = $this->pdo->prepare('UPDATE components SET position = :position WHERE id = :id');

        foreach ($ids as $index => $id) {
            $update->bindValue(':position', $index, PDO::PARAM_INT);
            $update->bindValue(':id', (int) $id, PDO::PARAM_INT);
            $update->execute();
        }
    }

    /**
     * Locks all sibling components under the given parent ID for update.
     *
     * @param int|null $parentId The ID of the parent component, or null for root components.
     */
    public function lockSiblings(?int $parentId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM components WHERE parent_id <=> :parent ORDER BY position FOR UPDATE'
        );
        if ($parentId === null) {
            $stmt->bindValue(':parent', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':parent', $parentId, PDO::PARAM_INT);
        }

        $stmt->execute();
        $stmt->closeCursor();
    }
}
