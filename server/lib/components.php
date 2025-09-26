<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/logger.php';

function components_fetch_rows(?PDO $pdo = null): array {
    $pdo = $pdo ?? get_db_connection();
    $sql = 'SELECT c.id, c.definition_id, c.parent_id, c.alternate_title, c.description, c.image, c.dependency_tree, c.position, c.created_at, c.updated_at, d.title AS definition_title
            FROM components c
            INNER JOIN definitions d ON d.id = c.definition_id
            ORDER BY (c.parent_id IS NULL) DESC, c.parent_id, c.position, c.id';
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll();
    $normalised = [];
    foreach ($rows as $row) {
        $row['dependency_tree'] = components_normalise_dependency_tree($row['dependency_tree'] ?? null);
        $row['effective_title'] = components_effective_title($row);
        $normalised[] = $row;
    }
    log_message('Fetched ' . count($normalised) . ' components from database', 'DEBUG');
    return $normalised;
}

function components_normalise_dependency_tree($raw): array {
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

function components_effective_title(array $row): string {
    $alt = isset($row['alternate_title']) ? trim((string) $row['alternate_title']) : '';
    if ($alt !== '') {
        return $alt;
    }
    return isset($row['definition_title']) ? (string) $row['definition_title'] : '';
}

function components_group_by_parent(array $rows): array {
    $grouped = [];
    foreach ($rows as $row) {
        $key = $row['parent_id'] === null ? 'root' : (string) $row['parent_id'];
        $grouped[$key][] = $row;
    }
    return $grouped;
}

function components_build_branch(array $grouped, string $key): array {
    if (!isset($grouped[$key])) {
        return [];
    }
    $branch = [];
    foreach ($grouped[$key] as $row) {
        $childKey = (string) $row['id'];
        $row['children'] = components_build_branch($grouped, $childKey);
        $branch[] = $row;
    }
    return $branch;
}

function components_build_tree(array $rows): array {
    $grouped = components_group_by_parent($rows);
    $tree = components_build_branch($grouped, 'root');
    log_message('Built component tree with ' . count($tree) . ' root nodes', 'DEBUG');
    return $tree;
}

function components_flatten_tree(array $tree, int $depth = 0): array {
    $flat = [];
    foreach ($tree as $node) {
        $children = $node['children'] ?? [];
        $copy = $node;
        $copy['depth'] = $depth;
        unset($copy['children']);
        $flat[] = $copy;
        if (!empty($children)) {
            $flat = array_merge($flat, components_flatten_tree($children, $depth + 1));
        }
    }
    return $flat;
}

function components_fetch_tree(?PDO $pdo = null): array {
    $rows = components_fetch_rows($pdo);
    return components_build_tree($rows);
}

