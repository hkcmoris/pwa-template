<?php
require_once __DIR__ . '/db.php';

function definitions_fetch_rows(?PDO $pdo = null): array {
    $pdo = $pdo ?? get_db_connection();
    $stmt = $pdo->query('SELECT id, parent_id, title, position, meta, created_at, updated_at
                          FROM definitions
                          ORDER BY (parent_id IS NULL) DESC, parent_id, position, id');
    return $stmt->fetchAll();
}

function definitions_build_tree(array $rows): array {
    $grouped = [];
    foreach ($rows as $row) {
        $key = $row['parent_id'] === null ? 'root' : (string) $row['parent_id'];
        $grouped[$key][] = $row;
    }
    return definitions_build_branch($grouped, 'root');
}

function definitions_build_branch(array $grouped, string $key): array {
    if (!isset($grouped[$key])) {
        return [];
    }
    $branch = [];
    foreach ($grouped[$key] as $row) {
        $childKey = (string) $row['id'];
        $row['children'] = definitions_build_branch($grouped, $childKey);
        $branch[] = $row;
    }
    return $branch;
}

function definitions_flatten_tree(array $tree, int $depth = 0): array {
    $flat = [];
    foreach ($tree as $node) {
        $children = $node['children'] ?? [];
        $copy = $node;
        $copy['depth'] = $depth;
        unset($copy['children']);
        $flat[] = $copy;
        if (!empty($children)) {
            $flat = array_merge($flat, definitions_flatten_tree($children, $depth + 1));
        }
    }
    return $flat;
}

function definitions_fetch_tree(?PDO $pdo = null): array {
    $rows = definitions_fetch_rows($pdo);
    return definitions_build_tree($rows);
}

function definitions_find(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare('SELECT id, parent_id, title, position, meta, created_at, updated_at FROM definitions WHERE id = :id');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch();
    return $row ?: null;
}

function definitions_parent_exists(PDO $pdo, int $parentId): bool {
    $stmt = $pdo->prepare('SELECT 1 FROM definitions WHERE id = :id LIMIT 1');
    $stmt->bindValue(':id', $parentId, PDO::PARAM_INT);
    $stmt->execute();
    return (bool) $stmt->fetchColumn();
}

function definitions_next_position(PDO $pdo, ?int $parentId): int {
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

function definitions_reorder_positions(PDO $pdo, ?int $parentId): void {
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

function definitions_update_title(PDO $pdo, int $id, string $title): array {
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

function definitions_create(PDO $pdo, string $title, ?int $parentId, int $position): array {
    $pdo->beginTransaction();
    try {
        if ($parentId !== null && !definitions_parent_exists($pdo, $parentId)) {
            throw new RuntimeException('Vybraný rodič neexistuje.');
        }
        if ($position < 0) {
            $position = 0;
        }
        $count = definitions_children_count($pdo, $parentId);
        if ($position > $count) {
            $position = $count;
        }
        $shift = $pdo->prepare('UPDATE definitions SET position = position + 1 WHERE parent_id <=> :parent AND position >= :position');
        if ($parentId === null) {
            $shift->bindValue(':parent', null, PDO::PARAM_NULL);
        } else {
            $shift->bindValue(':parent', $parentId, PDO::PARAM_INT);
        }
        $shift->bindValue(':position', $position, PDO::PARAM_INT);
        $shift->execute();
        $stmt = $pdo->prepare('INSERT INTO definitions (parent_id, title, position, meta) VALUES (:parent, :title, :position, NULL)');
        if ($parentId === null) {
            $stmt->bindValue(':parent', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':parent', $parentId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':title', $title, PDO::PARAM_STR);
        $stmt->bindValue(':position', $position, PDO::PARAM_INT);
        $stmt->execute();
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
        $row = definitions_find($pdo, $id);
        if (!$row) {
            throw new RuntimeException('Definice nebyla nalezena po vložení.');
        }
        return $row;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
function definitions_delete(PDO $pdo, int $id): void {
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
        $stmt = $pdo->prepare('UPDATE definitions SET position = position - 1 WHERE parent_id <=> :parent AND position > :position');
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

function definitions_get_parent_id(PDO $pdo, int $id): ?int {
    $stmt = $pdo->prepare('SELECT parent_id FROM definitions WHERE id = :id');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $value = $stmt->fetchColumn();
    if ($value === false) {
        return null;
    }
    return $value === null ? null : (int) $value;
}

function definitions_children_count(PDO $pdo, ?int $parentId): int {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM definitions WHERE parent_id <=> :parent');
    if ($parentId === null) {
        $stmt->bindValue(':parent', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':parent', $parentId, PDO::PARAM_INT);
    }
    $stmt->execute();
    return (int) $stmt->fetchColumn();
}

function definitions_move(PDO $pdo, int $id, ?int $newParentId, int $newPosition): void {
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
        $cleanup = $pdo->prepare('UPDATE definitions SET position = position - 1 WHERE parent_id <=> :parent AND position > :position');
        if ($oldParentId === null) {
            $cleanup->bindValue(':parent', null, PDO::PARAM_NULL);
        } else {
            $cleanup->bindValue(':parent', $oldParentId, PDO::PARAM_INT);
        }
        $cleanup->bindValue(':position', $oldPosition, PDO::PARAM_INT);
        $cleanup->execute();
        $count = definitions_children_count($pdo, $newParentId);
        if ($newParentId === $oldParentId && $newPosition > $oldPosition) {
            $newPosition -= 1;
        }
        if ($newPosition > $count) {
            $newPosition = $count;
        }
        $shift = $pdo->prepare('UPDATE definitions SET position = position + 1 WHERE parent_id <=> :parent AND position >= :position');
        if ($newParentId === null) {
            $shift->bindValue(':parent', null, PDO::PARAM_NULL);
        } else {
            $shift->bindValue(':parent', $newParentId, PDO::PARAM_INT);
        }
        $shift->bindValue(':position', $newPosition, PDO::PARAM_INT);
        $shift->execute();
        $update = $pdo->prepare('UPDATE definitions SET parent_id = :parent, position = :position WHERE id = :id');
        if ($newParentId === null) {
            $update->bindValue(':parent', null, PDO::PARAM_NULL);
        } else {
            $update->bindValue(':parent', $newParentId, PDO::PARAM_INT);
        }
        $update->bindValue(':position', $newPosition, PDO::PARAM_INT);
        $update->bindValue(':id', $id, PDO::PARAM_INT);
        $update->execute();
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}


