<?php

/**
 * @return list<array<string, mixed>>
 */
function definitions_fetch_rows(?PDO $pdo = null): array
{

    $pdo = $pdo ?? get_db_connection();
    $stmt = $pdo->query('SELECT id, parent_id, title, position, meta, created_at, updated_at
                          FROM definitions
                          ORDER BY (parent_id IS NULL) DESC, parent_id, position, id');
    log_message('Fetched ' . $stmt->rowCount() . ' definitions from database', 'DEBUG');
    /** @var list<array<string, mixed>> $rows */
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $rows;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function definitions_build_tree(array $rows): array
{

    /** @var array<string, list<array<string, mixed>>> $grouped */
    $grouped = [];
    foreach ($rows as $row) {
        $key = $row['parent_id'] === null ? 'root' : (string) $row['parent_id'];
        $grouped[$key][] = $row;
    }
    log_message('Grouped definitions into ' . count($grouped) . ' parent categories', 'DEBUG');
    return definitions_build_branch($grouped, 'root');
}

/**
 * @param array<string, list<array<string, mixed>>> $grouped
 * @return list<array<string, mixed>>
 */
function definitions_build_branch(array $grouped, string $key): array
{

    if (!isset($grouped[$key])) {
        return [];
    }
    $branch = [];
    foreach ($grouped[$key] as $row) {
        $childKey = (string) $row['id'];
        $node = $row;
        $node['children'] = definitions_build_branch($grouped, $childKey);
        $branch[] = $node;
    }
    return $branch;
}

/**
 * @param list<array<string, mixed>> $tree
 * @return list<array<string, mixed>>
 */
function definitions_flatten_tree(array $tree, int $depth = 0): array
{

    $flat = [];
    foreach ($tree as $node) {
        $children = $node['children'] ?? [];
        $copy = $node;
        unset($copy['children']);
        $copy['depth'] = $depth;
        $flat[] = $copy;
        if (!empty($children)) {
            $flat = array_merge($flat, definitions_flatten_tree($children, $depth + 1));
        }
    }
    return $flat;
}

/**
 * @return list<array<string, mixed>>
 */
function definitions_fetch_tree(?PDO $pdo = null): array
{

    $rows = definitions_fetch_rows($pdo);
    return definitions_build_tree($rows);
}

/**
 * @return array<string, mixed>|null
 */
function definitions_find(PDO $pdo, int $id): ?array
{

    $stmt = $pdo->prepare(
        'SELECT id, parent_id, title, position, meta, created_at, updated_at FROM definitions WHERE id = :id'
    );
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    /** @var array<string, mixed>|false $row */
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function definitions_parent_exists(PDO $pdo, int $parentId): bool
{

    $stmt = $pdo->prepare('SELECT 1 FROM definitions WHERE id = :id LIMIT 1');
    $stmt->bindValue(':id', $parentId, PDO::PARAM_INT);
    $stmt->execute();
    return (bool) $stmt->fetchColumn();
}

function definitions_next_position(PDO $pdo, ?int $parentId): int
{

    $stmt = $pdo->prepare('SELECT COALESCE(MAX(position), -1) FROM definitions WHERE parent_id <=> :parent');
    if ($parentId === null) {
        $stmt->bindValue(':parent', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':parent', $parentId, PDO::PARAM_INT);
    }
    $stmt->execute();
    $max = (int) $stmt->fetchColumn();
    return $max + 1;
}

function definitions_reorder_positions__DEPRECATED(PDO $pdo, ?int $parentId): void
{

    $stmt = $pdo->prepare('SELECT id FROM definitions WHERE parent_id <=> :parent ORDER BY position, id');
    if ($parentId === null) {
        $stmt->bindValue(':parent', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':parent', $parentId, PDO::PARAM_INT);
    }
    $stmt->execute();
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $update = $pdo->prepare('UPDATE definitions SET position = :position WHERE id = :id');
    foreach ($ids as $index => $id) {
        $update->bindValue(':position', $index, PDO::PARAM_INT);
        $update->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $update->execute();
    }
}

function definitions_reorder_positions(PDO $pdo, ?int $parentId): void
{

    // Phase 1: move all positions away to avoid unique collisions
    $bump = $pdo->prepare('UPDATE definitions SET position = position + 1000000 WHERE parent_id <=> :parent');
    log_message('Phase 1: Reordering positions for parent_id ' . var_export($parentId, true), 'DEBUG');
    if ($parentId === null) {
        $bump->bindValue(':parent', null, PDO::PARAM_NULL);
    } else {
        $bump->bindValue(':parent', $parentId, PDO::PARAM_INT);
    }
    log_message('Bump query: ' . $bump->queryString, 'DEBUG');
    $bump->execute();
// Phase 2: reassign compact 0..n-1 deterministically
    $stmt = $pdo->prepare('SELECT id FROM definitions WHERE parent_id <=> :parent ORDER BY position, id');
    if ($parentId === null) {
        $stmt->bindValue(':parent', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':parent', $parentId, PDO::PARAM_INT);
    }
    log_message('Phase 2: Select query: ' . $stmt->queryString, 'DEBUG');
    $stmt->execute();
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $update = $pdo->prepare('UPDATE definitions SET position = :position WHERE id = :id');
    foreach ($ids as $index => $id) {
        $update->bindValue(':position', $index, PDO::PARAM_INT);
        $update->bindValue(':id', (int) $id, PDO::PARAM_INT);
        log_message(
            'Phase 2: Update query: ' . $update->queryString . ' with position=' . $index . ' and id=' . (int)$id,
            'DEBUG'
        );
        $update->execute();
    }
}

/**
 * @return array<string, mixed>
 */
function definitions_update_title(PDO $pdo, int $id, string $title): array
{

    $stmt = $pdo->prepare('UPDATE definitions SET title = :title WHERE id = :id');
    $stmt->bindValue(':title', $title, PDO::PARAM_STR);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $row = definitions_find($pdo, $id);
    if (!$row) {
        throw new RuntimeException('Definice neexistuje.');
    }
    return $row;
}

/**
 * @return array<string, mixed>
 */
function definitions_create(PDO $pdo, string $title, ?int $parentId, int $position): array
{

    $message = 'Creating definition with title=' . $title
        . ', parentId=' . var_export($parentId, true)
        . ', position=' . $position;
    log_message($message, 'DEBUG');
    $pdo->beginTransaction();
    try {
        if ($parentId !== null && !definitions_parent_exists($pdo, $parentId)) {
            log_message('Parent ID ' . $parentId . ' does not exist.', 'ERROR');
            throw new RuntimeException('Vybraný rodič neexistuje.');
        }
        if ($position < 0) {
            $position = 0;
        }
        definitions_reorder_positions($pdo, $parentId);
        $count = definitions_children_count($pdo, $parentId);
        if ($position > $count) {
            $position = $count;
        }
        $shift = $pdo->prepare(
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
        $stmt = $pdo->prepare(
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
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
        $row = definitions_find($pdo, $id);
        if (!$row) {
            log_message('Failed to find definition after insert with ID ' . $id, 'ERROR');
            throw new RuntimeException('Definice nebyla nalezena po vložení.');
        }
        return $row;
    } catch (Throwable $e) {
        log_message('Error during definition creation: ' . $e->getMessage(), 'ERROR');
        $pdo->rollBack();
        throw $e;
    }
}
function definitions_delete(PDO $pdo, int $id): void
{

    $pdo->beginTransaction();
    try {
        $row = definitions_find($pdo, $id);
        if (!$row) {
            throw new RuntimeException('Definice neexistuje.');
        }
        $parentId = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
        $position = (int) $row['position'];
        $stmt = $pdo->prepare('DELETE FROM definitions WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $stmt = $pdo->prepare(
            'UPDATE definitions SET position = position - 1 WHERE parent_id <=> :parent AND position > :position'
        );
        if ($parentId === null) {
            $stmt->bindValue(':parent', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':parent', $parentId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':position', $position, PDO::PARAM_INT);
        $stmt->execute();
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function definitions_get_parent_id(PDO $pdo, int $id): ?int
{

    $stmt = $pdo->prepare('SELECT parent_id FROM definitions WHERE id = :id');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $value = $stmt->fetchColumn();
    if ($value === false) {
        return null;
    }
    return $value === null ? null : (int) $value;
}

function definitions_children_count(PDO $pdo, ?int $parentId): int
{

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM definitions WHERE parent_id <=> :parent');
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
function definitions_fetch_children(PDO $pdo, int $parentId): array
{

    $stmt = $pdo->prepare('SELECT id, parent_id, title, position, meta, created_at, updated_at
           FROM definitions
          WHERE parent_id = :parent
          ORDER BY position, id');
    $stmt->bindValue(':parent', $parentId, PDO::PARAM_INT);
    $stmt->execute();
    /** @var list<array<string, mixed>> $rows */
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $rows;
}

function definitions_move(PDO $pdo, int $id, ?int $newParentId, int $newPosition): void
{

    $pdo->beginTransaction();
    try {
        $node = definitions_find($pdo, $id);
        if (!$node) {
            throw new RuntimeException('Definice neexistuje.');
        }
        $oldParentId = $node['parent_id'] !== null ? (int) $node['parent_id'] : null;
        $oldPosition = (int) $node['position'];
        if ($newParentId !== null && !definitions_parent_exists($pdo, $newParentId)) {
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
                $ancestor = definitions_get_parent_id($pdo, $ancestor);
            }
        }
        if ($newPosition < 0) {
            $newPosition = 0;
        }
        $sameParent = ($newParentId === $oldParentId);
        if ($sameParent && $newPosition === $oldPosition) {
            $pdo->commit();
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
        $lockParent($pdo, $oldParentId);
        if (!$sameParent) {
            $lockParent($pdo, $newParentId);
        }

        // park at a safe slot within old parent
        $maxStmt = $pdo->prepare('SELECT COALESCE(MAX(position), -1) FROM definitions WHERE parent_id <=> :parent');
        $maxStmt->bindValue(':parent', $oldParentId, $oldParentId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $maxStmt->execute();
        $parking = ((int) $maxStmt->fetchColumn()) + 1000 + $id;
        $maxStmt->closeCursor();
        $parkStmt = $pdo->prepare('UPDATE definitions SET position = :position WHERE id = :id');
        $parkStmt->bindValue(':position', $parking, PDO::PARAM_INT);
        $parkStmt->bindValue(':id', $id, PDO::PARAM_INT);
        $parkStmt->execute();
    // close gap in old parent
        $cleanup = $pdo->prepare('UPDATE definitions
                 SET position = position - 1
               WHERE parent_id <=> :parent
                 AND id <> :id
                 AND position > :position');
        $cleanup->bindValue(':parent', $oldParentId, $oldParentId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $cleanup->bindValue(':id', $id, PDO::PARAM_INT);
        $cleanup->bindValue(':position', $oldPosition, PDO::PARAM_INT);
        $cleanup->execute();
        if ($sameParent) {
            $siblingCount = definitions_children_count($pdo, $oldParentId);
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
            $targetCount = definitions_children_count($pdo, $newParentId);
            if ($newPosition > $targetCount) {
                $newPosition = $targetCount;
            }
            if ($newPosition < 0) {
                $newPosition = 0;
            }
        }

        $targetParent = $sameParent ? $oldParentId : $newParentId;
        $shift = $pdo->prepare('UPDATE definitions
                 SET position = position + 1
               WHERE parent_id <=> :parent
                 AND position >= :position');
        $shift->bindValue(':parent', $targetParent, $targetParent === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $shift->bindValue(':position', $newPosition, PDO::PARAM_INT);
        $shift->execute();
        $update = $pdo->prepare('UPDATE definitions SET parent_id = :parent, position = :position WHERE id = :id');
        $update->bindValue(':parent', $targetParent, $targetParent === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $update->bindValue(':position', $newPosition, PDO::PARAM_INT);
        $update->bindValue(':id', $id, PDO::PARAM_INT);
        $update->execute();
        definitions_reorder_positions($pdo, $targetParent);
        if (!$sameParent) {
            definitions_reorder_positions($pdo, $oldParentId);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
