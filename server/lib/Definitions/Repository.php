<?php

declare(strict_types=1);

namespace Definitions;

use PDO;
use RuntimeException;
use Throwable;

use function get_db_connection;
use function log_message;

final class Repository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? get_db_connection();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchRows(?int $limit = null, int $offset = 0): array
    {
        $sql = <<<SQL
        SELECT id, parent_id, title, position, meta, created_at, updated_at
        FROM definitions
        ORDER BY (parent_id IS NULL) DESC, parent_id, position, id
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
        log_message('Fetched ' . $stmt->rowCount() . ' definitions from database', 'DEBUG');
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchTree(?Formatter $formatter = null): array
    {
        $formatter = $formatter ?? new Formatter();
        return $formatter->buildTree($this->fetchRows());
    }

    public function countAll(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM definitions');

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, parent_id, title, position, meta, created_at, updated_at FROM definitions WHERE id = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function parentExists(int $parentId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM definitions WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $parentId, PDO::PARAM_INT);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    public function nextPosition(?int $parentId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(position), -1) FROM definitions WHERE parent_id <=> :parent');
        if ($parentId === null) {
            $stmt->bindValue(':parent', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':parent', $parentId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $max = (int) $stmt->fetchColumn();
        return $max + 1;
    }

    /** @deprecated */
    public function reorderPositionsDeprecated(?int $parentId): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM definitions WHERE parent_id <=> :parent ORDER BY position, id');
        if ($parentId === null) {
            $stmt->bindValue(':parent', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':parent', $parentId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $update = $this->pdo->prepare('UPDATE definitions SET position = :position WHERE id = :id');
        foreach ($ids as $index => $id) {
            $update->bindValue(':position', $index, PDO::PARAM_INT);
            $update->bindValue(':id', (int) $id, PDO::PARAM_INT);
            $update->execute();
        }
    }

    public function reorderPositions(?int $parentId): void
    {
        $bump = $this->pdo->prepare('UPDATE definitions SET position = position + 1000000 WHERE parent_id <=> :parent');
        log_message('Phase 1: Reordering positions for parent_id ' . var_export($parentId, true), 'DEBUG');
        if ($parentId === null) {
            $bump->bindValue(':parent', null, PDO::PARAM_NULL);
        } else {
            $bump->bindValue(':parent', $parentId, PDO::PARAM_INT);
        }
        log_message('Bump query: ' . $bump->queryString, 'DEBUG');
        $bump->execute();

        $stmt = $this->pdo->prepare('SELECT id FROM definitions WHERE parent_id <=> :parent ORDER BY position, id');
        if ($parentId === null) {
            $stmt->bindValue(':parent', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':parent', $parentId, PDO::PARAM_INT);
        }
        log_message('Phase 2: Select query: ' . $stmt->queryString, 'DEBUG');
        $stmt->execute();
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $update = $this->pdo->prepare('UPDATE definitions SET position = :position WHERE id = :id');
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
     * @return array<string, mixed>
     */
    public function updateTitle(int $id, string $title): array
    {
        $stmt = $this->pdo->prepare('UPDATE definitions SET title = :title WHERE id = :id');
        $stmt->bindValue(':title', $title, PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $this->find($id);
        if (!$row) {
            throw new RuntimeException('Definice neexistuje.');
        }
        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function create(string $title, ?int $parentId, int $position): array
    {
        $message = 'Creating definition with title=' . $title
            . ', parentId=' . var_export($parentId, true)
            . ', position=' . $position;
        log_message($message, 'DEBUG');
        $this->pdo->beginTransaction();
        try {
            if ($parentId !== null && !$this->parentExists($parentId)) {
                log_message('Parent ID ' . $parentId . ' does not exist.', 'ERROR');
                throw new RuntimeException('Vybraný rodič neexistuje.');
            }
            if ($position < 0) {
                $position = 0;
            }
            $this->reorderPositions($parentId);
            $count = $this->childrenCount($parentId);
            if ($position > $count) {
                $position = $count;
            }
            $shift = $this->pdo->prepare(
                'UPDATE definitions SET position = position + 1 WHERE parent_id <=> :parent AND position >= :position'
            );
            if ($parentId === null) {
                $shift->bindValue(':parent', null, PDO::PARAM_NULL);
            } else {
                $shift->bindValue(':parent', $parentId, PDO::PARAM_INT);
            }
            $shift->bindValue(':position', $position, PDO::PARAM_INT);
            log_message('Shift query: ' . $shift->queryString, 'DEBUG');
            $shift->execute();
            $stmt = $this->pdo->prepare(
                'INSERT INTO definitions (parent_id, title, position, meta) VALUES (:parent, :title, :position, NULL)'
            );
            if ($parentId === null) {
                $stmt->bindValue(':parent', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':parent', $parentId, PDO::PARAM_INT);
            }
            $stmt->bindValue(':title', $title, PDO::PARAM_STR);
            $stmt->bindValue(':position', $position, PDO::PARAM_INT);
            log_message('Insert query: ' . $stmt->queryString, 'DEBUG');
            $stmt->execute();
            $id = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();
            $row = $this->find($id);
            if (!$row) {
                log_message('Failed to find definition after insert with ID ' . $id, 'ERROR');
                throw new RuntimeException('Definice nebyla nalezena po vložení.');
            }
            return $row;
        } catch (Throwable $e) {
            log_message('Error during definition creation: ' . $e->getMessage(), 'ERROR');
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function delete(int $id): void
    {
        $this->pdo->beginTransaction();
        try {
            $row = $this->find($id);
            if (!$row) {
                throw new RuntimeException('Definice neexistuje.');
            }
            $parentId = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
            $position = (int) $row['position'];
            $stmt = $this->pdo->prepare('DELETE FROM definitions WHERE id = :id');
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $stmt = $this->pdo->prepare(
                'UPDATE definitions SET position = position - 1 WHERE parent_id <=> :parent AND position > :position'
            );
            if ($parentId === null) {
                $stmt->bindValue(':parent', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':parent', $parentId, PDO::PARAM_INT);
            }
            $stmt->bindValue(':position', $position, PDO::PARAM_INT);
            $stmt->execute();
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function getParentId(int $id): ?int
    {
        $stmt = $this->pdo->prepare('SELECT parent_id FROM definitions WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $value = $stmt->fetchColumn();
        if ($value === false) {
            return null;
        }
        return $value === null ? null : (int) $value;
    }

    public function childrenCount(?int $parentId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM definitions WHERE parent_id <=> :parent');
        if ($parentId === null) {
            $stmt->bindValue(':parent', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':parent', $parentId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchChildren(int $parentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, parent_id, title, position, meta, created_at, updated_at
               FROM definitions
              WHERE parent_id = :parent
           ORDER BY position, id'
        );
        $stmt->bindValue(':parent', $parentId, PDO::PARAM_INT);
        $stmt->execute();
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows;
    }

    public function move(int $id, ?int $newParentId, int $newPosition): void
    {
        $this->pdo->beginTransaction();
        try {
            $node = $this->find($id);
            if (!$node) {
                throw new RuntimeException('Definice neexistuje.');
            }
            $oldParentId = $node['parent_id'] !== null ? (int) $node['parent_id'] : null;
            $oldPosition = (int) $node['position'];
            if ($newParentId !== null && !$this->parentExists($newParentId)) {
                throw new RuntimeException('Vybraný rodič neexistuje.');
            }
            if ($newParentId === $id) {
                throw new RuntimeException('Nelze přesunout uzel pod sebe samotného.');
            }
            if ($newParentId !== null) {
                $ancestor = $newParentId;
                while ($ancestor !== null) {
                    if ($ancestor === $id) {
                        throw new RuntimeException('Nelze přesunout uzel pod vlastní potomky.');
                    }
                    $ancestor = $this->getParentId($ancestor);
                }
            }
            if ($newPosition < 0) {
                $newPosition = 0;
            }
            $sameParent = ($newParentId === $oldParentId);
            if ($sameParent && $newPosition === $oldPosition) {
                $this->pdo->commit();
                return;
            }
            $lockParent = static function (PDO $pdo, ?int $parentId): void {
                $stmt = $pdo->prepare(
                    'SELECT id FROM definitions WHERE parent_id <=> :parent ORDER BY position FOR UPDATE'
                );
                if ($parentId === null) {
                    $stmt->bindValue(':parent', null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':parent', $parentId, PDO::PARAM_INT);
                }
                $stmt->execute();
                $stmt->closeCursor();
            };
            $lockParent($this->pdo, $oldParentId);
            if (!$sameParent) {
                $lockParent($this->pdo, $newParentId);
            }

            $maxStmt = $this->pdo->prepare(
                'SELECT COALESCE(MAX(position), -1) FROM definitions WHERE parent_id <=> :parent'
            );
            $maxStmt->bindValue(':parent', $oldParentId, $oldParentId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $maxStmt->execute();
            $parking = ((int) $maxStmt->fetchColumn()) + 1000 + $id;
            $maxStmt->closeCursor();
            $parkStmt = $this->pdo->prepare('UPDATE definitions SET position = :position WHERE id = :id');
            $parkStmt->bindValue(':position', $parking, PDO::PARAM_INT);
            $parkStmt->bindValue(':id', $id, PDO::PARAM_INT);
            $parkStmt->execute();

            $cleanup = $this->pdo->prepare('UPDATE definitions
                     SET position = position - 1
                   WHERE parent_id <=> :parent
                     AND id <> :id
                     AND position > :position');
            $cleanup->bindValue(':parent', $oldParentId, $oldParentId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $cleanup->bindValue(':id', $id, PDO::PARAM_INT);
            $cleanup->bindValue(':position', $oldPosition, PDO::PARAM_INT);
            $cleanup->execute();
            if ($sameParent) {
                $siblingCount = $this->childrenCount($oldParentId);
                if ($newPosition > $siblingCount) {
                    $newPosition = $siblingCount;
                }
                if ($newPosition < 0) {
                    $newPosition = 0;
                }
                if ($newPosition > $oldPosition) {
                    $newPosition -= 1;
                }
            } else {
                $targetCount = $this->childrenCount($newParentId);
                if ($newPosition > $targetCount) {
                    $newPosition = $targetCount;
                }
                if ($newPosition < 0) {
                    $newPosition = 0;
                }
            }

            $targetParent = $sameParent ? $oldParentId : $newParentId;
            $shift = $this->pdo->prepare('UPDATE definitions
                     SET position = position + 1
                   WHERE parent_id <=> :parent
                     AND position >= :position');
            $shift->bindValue(':parent', $targetParent, $targetParent === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $shift->bindValue(':position', $newPosition, PDO::PARAM_INT);
            $shift->execute();
            $update = $this->pdo->prepare(
                'UPDATE definitions SET parent_id = :parent, position = :position WHERE id = :id'
            );
            $update->bindValue(':parent', $targetParent, $targetParent === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $update->bindValue(':position', $newPosition, PDO::PARAM_INT);
            $update->bindValue(':id', $id, PDO::PARAM_INT);
            $update->execute();
            $this->reorderPositions($targetParent);
            if (!$sameParent) {
                $this->reorderPositions($oldParentId);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
