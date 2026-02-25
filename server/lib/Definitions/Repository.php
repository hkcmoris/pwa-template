<?php

declare(strict_types=1);

namespace Definitions;

use PDO;
use RuntimeException;
use Throwable;
use Shared\PositionService;

use function get_db_connection;
use function log_message;

final class Repository
{
    private PDO $pdo;

    private PositionService $positionService;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? get_db_connection();
        $this->positionService = new PositionService($pdo, 'definitions');
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

    public function updateValueRange(int $id, ?int $min, ?int $max): void
    {
        $this->pdo->beginTransaction();
        try {
            $row = $this->find($id);
            if (!$row) {
                throw new RuntimeException('Definice neexistuje.');
            }

            $hasChildren = $this->childrenCount($id) > 0;
            if ($hasChildren && ($min !== null || $max !== null)) {
                throw new RuntimeException('Definice s potomky nelze změnit na rozsah hodnot.');
            }

            $metaData = [];
            if (isset($row['meta'])) {
                $existing = $row['meta'];
                if (is_string($existing) && $existing !== '') {
                    $decoded = json_decode($existing, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $metaData = $decoded;
                    }
                } elseif (is_array($existing)) {
                    $metaData = $existing;
                }
            }

            unset(
                $metaData['value_min'],
                $metaData['value_max'],
                $metaData['min'],
                $metaData['max'],
                $metaData['from'],
                $metaData['to']
            );

            if ($min === null && $max === null) {
                unset($metaData['value_range']);
            } else {
                $metaData['value_range'] = ['min' => $min, 'max' => $max];
            }

            $metaJson = null;
            if (!empty($metaData)) {
                $metaJson = json_encode($metaData, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
                if (!is_string($metaJson)) {
                    throw new RuntimeException('Nepodařilo se serializovat meta data.');
                }
            }

            $stmt = $this->pdo->prepare('UPDATE definitions SET meta = :meta WHERE id = :id');
            if ($metaJson === null) {
                $stmt->bindValue(':meta', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':meta', $metaJson, PDO::PARAM_STR);
            }
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function create(string $title, ?int $parentId, int $position): array
    {
        $message = 'Creating definition with title=' . $title
            . ', parentId=' . var_export($parentId, true)
            . ', position=' . $position;
        $this->pdo->beginTransaction();
        try {
            if ($parentId !== null && !$this->parentExists($parentId)) {
                log_message('Parent ID ' . $parentId . ' does not exist.', 'ERROR');
                throw new RuntimeException('Vybraný rodič neexistuje.');
            }
            if ($position < 0) {
                $position = 0;
            }
            $count = $this->childrenCount($parentId);
            if ($position > $count) {
                $position = $count;
            } else {
                $this->positionService->openGap($parentId, $position);
            }
            $stmt = $this->pdo->prepare(
                'INSERT INTO definitions (parent_id, title, position, meta) VALUES (:parent, :title, :position, NULL)'
            );
            $this->bindParent($stmt, $parentId);
            $stmt->bindValue(':title', $title, PDO::PARAM_STR);
            $stmt->bindValue(':position', $position, PDO::PARAM_INT);
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

            $this->positionService->moveNode(
                $id,
                $oldParentId,
                $oldPosition,
                $newParentId,
                $newPosition
            );
        } catch (Throwable $e) {
            throw $e;
        }
    }
}
