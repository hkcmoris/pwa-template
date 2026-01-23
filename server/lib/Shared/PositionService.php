<?php

declare(strict_types=1);

namespace Shared;

use PDO;
use RuntimeException;
use Throwable;

final class PositionService
{
    private PDO $pdo;
    private string $tableName;

    private const ALLOWED_TABLES = ['components', 'definitions'];

    public function __construct(PDO $pdo, string $tableName)
    {
        if (!in_array($tableName, self::ALLOWED_TABLES, true)) {
            throw new \InvalidArgumentException('Invalid tableName provided to PositionService: ' . $tableName);
        }

        $this->pdo = $pdo;
        $this->tableName = $tableName;
    }

    /**
     * Reorders the positions of all components/definitions under the given parent ID
     * to ensure they are sequential starting from 0.
     *
     * @param int|null $parentId The ID of the parent component/definition, or null for root components/definitions.
     */
    public function reorderPositions(?int $parentId): void
    {
        $bump = $this->pdo->prepare(
            'UPDATE ' . $this->tableName . ' SET position = position + 1000000 WHERE parent_id <=> :parent'
        );
        log_message(
            'Phase 1: Reordering ' . $this->tableName . ' positions for parent_id ' . var_export($parentId, true),
            'DEBUG'
        );
        $this->bindParent($bump, $parentId);
        log_message('Bump query: ' . $bump->queryString, 'DEBUG');

        $bump->execute();

        $stmt = $this->pdo->prepare(
            'SELECT id FROM ' . $this->tableName . ' WHERE parent_id <=> :parent ORDER BY position, id'
        );
        $this->bindParent($stmt, $parentId);
        log_message('Phase 2: Select query: ' . $stmt->queryString, 'DEBUG');

        $stmt->execute();

        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $update = $this->pdo->prepare('UPDATE ' . $this->tableName . ' SET position = :position WHERE id = :id');

        foreach ($ids as $index => $id) {
            $update->bindValue(':position', $index, PDO::PARAM_INT);
            $update->bindValue(':id', (int) $id, PDO::PARAM_INT);
            log_message(
                'Phase 2: Update query: ' . $update->queryString . ' with position=' . $index . ' and id=' . (int) $id,
                'DEBUG'
            );
            $update->execute();
        }
    }

    /**
     * Binds the parent ID parameter to the given PDO statement.
     *
     * @param \PDOStatement $stmt The PDO statement to bind the parameter to.
     * @param int|null $parentId The ID of the parent component/definition, or null for root components/definitions.
     */
    private function bindParent(\PDOStatement $stmt, ?int $parentId): void
    {
        if ($parentId === null) {
            $stmt->bindValue(':parent', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':parent', $parentId, PDO::PARAM_INT);
        }
    }

    /**
     * Fetches the maximum position value among components/definitions under the given parent ID.
     *
     * @param int|null $parentId The ID of the parent component/definition, or null for root components/definitions.
     * @return int The maximum position value, or -1 if there are no components/definitions under the given parent.
     */
    private function fetchMaxPosition(?int $parentId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(MAX(position), -1) FROM ' . $this->tableName . ' WHERE parent_id <=> :parent'
        );
        $this->bindParent($stmt, $parentId);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Locks all sibling components/definitions under the given parent ID for update.
     *
     * @param int|null $parentId The ID of the parent component/definition, or null for root components/definitions.
     */
    public function lockSiblings(?int $parentId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM ' . $this->tableName . ' WHERE parent_id <=> :parent ORDER BY position FOR UPDATE'
        );
        $this->bindParent($stmt, $parentId);
        log_message('Locking siblings with query: ' . $stmt->queryString, 'DEBUG');

        $stmt->execute();
        $stmt->closeCursor();
    }

    /**
     * Opens a gap in positions under the given parent ID starting from the specified position.
     *
     * @param int|null $parentId The ID of the parent component/definition, or null for root components/definitions.
     * @param int $fromPosition The position from which to start opening the gap.
     */
    public function openGap(?int $parentId, int $fromPosition): void
    {
        // Open gap in target (INCREMENT in DESC order)
        $shift = $this->pdo->prepare(
            'UPDATE ' . $this->tableName . '
            SET position = position + 1
            WHERE parent_id <=> :parent
            AND position >= :pos
            ORDER BY position DESC'
        );
        $this->bindParent($shift, $parentId);
        $shift->bindValue(':pos', $fromPosition, PDO::PARAM_INT);
        $shift->execute();
    }

    /**
     * Closes a gap in positions under the given parent ID starting from the specified position.
     *
     * @param int|null $parentId The ID of the parent component/definition, or null for root components/definitions.
     * @param int $fromPosition The position from which to start closing the gap.
     */
    public function closeGap(int $id, ?int $parentId, int $fromPosition): void
    {
        // Close gap in old parent (DECREMENT in ASC order)
        $cleanup = $this->pdo->prepare(
            'UPDATE ' . $this->tableName . '
            SET position = position - 1
            WHERE parent_id <=> :parent
            AND id <> :id
            AND position > :pos
            ORDER BY position ASC'
        );
        $this->bindParent($cleanup, $parentId);
        $cleanup->bindValue(':id', $id, PDO::PARAM_INT);
        $cleanup->bindValue(':pos', $fromPosition, PDO::PARAM_INT);
        $cleanup->execute();
    }

    /**
     * Moves a component/definition to a new parent and position.
     *
     * @param int $id The ID of the component/definition to move.
     * @param int|null $oldParentId The current parent ID of the component/definition, or null for root.
     * @param int $oldPosition The current position of the component/definition.
     * @param int|null $newParentId The new parent ID of the component/definition, or null for root.
     * @param int $newPosition The new position of the component/definition.
     *
     * @throws RuntimeException If the component/definition does not exist or if invalid operations are attempted.
     */
    public function moveNode(
        int $id,
        ?int $oldParentId,
        int $oldPosition,
        ?int $newParentId,
        int $newPosition
    ): void {
        $this->pdo->beginTransaction();

        try {
            $sameParent = ($oldParentId === $newParentId);

            // Normalize
            if ($newPosition < 0) {
                $newPosition = 0;
            }

            // Lock siblings (both sides)
            $this->lockSiblings($oldParentId);
            if (!$sameParent) {
                $this->lockSiblings($newParentId);
            }

            // Park the row to a guaranteed-free position (same parent is OK too)
            $maxOld = $this->fetchMaxPosition($oldParentId);
            $parking = $maxOld + 1000 + $id;

            $parkStmt = $this->pdo->prepare(
                'UPDATE ' . $this->tableName . ' SET position = :position WHERE id = :id'
            );
            $parkStmt->bindValue(':position', $parking, PDO::PARAM_INT);
            $parkStmt->bindValue(':id', $id, PDO::PARAM_INT);
            $parkStmt->execute();

            // Close gap in old parent (DECREMENT in ASC order)
            $this->closeGap($id, $oldParentId, $oldPosition);

            // Clamp target position using MAX(position)
            $targetParent = $sameParent ? $oldParentId : $newParentId;

            $maxTarget = $this->fetchMaxPosition($targetParent);
            $targetCount = $maxTarget + 1;

            if ($newPosition > $targetCount) {
                $newPosition = $targetCount;
            }

            // Same-parent adjustment: if moving down, target index shifts by -1
            if ($sameParent && $newPosition > $oldPosition) {
                $newPosition -= 1;
            }
            if ($newPosition < 0) {
                $newPosition = 0;
            }

            // Open gap in target (INCREMENT in DESC order)
            $this->openGap($targetParent, $newPosition);

            // Final update parent + position
            $update = $this->pdo->prepare(
                'UPDATE ' . $this->tableName . ' SET parent_id = :parent, position = :pos WHERE id = :id'
            );
            $this->bindParent($update, $targetParent);
            $update->bindValue(':pos', $newPosition, PDO::PARAM_INT);
            $update->bindValue(':id', $id, PDO::PARAM_INT);
            $update->execute();

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
