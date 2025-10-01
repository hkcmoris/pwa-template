<?php

function components_fetch_rows(?PDO $pdo = null): array
{

    $pdo = $pdo ?? get_db_connection();
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
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $componentIds = array_map(static fn($row) => isset($row['id']) ? (int) $row['id'] : 0, $rows);
    $priceHistoryMap = components_fetch_price_history($pdo, $componentIds);
    $normalised = [];
    foreach ($rows as $row) {
        $row['dependency_tree'] = components_normalise_dependency_tree($row['dependency_tree'] ?? null);
        $row['effective_title'] = components_effective_title($row);
        $rowId = isset($row['id']) ? (int) $row['id'] : 0;
        $history = $priceHistoryMap[$rowId] ?? [];
        $row['price_history'] = $history;
        $row['latest_price'] = $history[0] ?? null;
        $normalised[] = $row;
    }
    log_message('Fetched ' . count($normalised) . ' components from database', 'DEBUG');
    return $normalised;
}

function components_fetch_price_history(PDO $pdo, array $componentIds, int $limitPerComponent = 10): array
{

    $mappedIds = array_map(
        static fn($id) => (int) $id,
        $componentIds
    );
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
    $stmt = $pdo->prepare($sql);
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
        $currency = isset($row['currency']) && $row['currency'] !== null
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

function components_find(PDO $pdo, int $id): ?array
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
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $row['dependency_tree'] = components_normalise_dependency_tree($row['dependency_tree'] ?? null);
    $row['effective_title'] = components_effective_title($row);
    $priceHistory = components_fetch_price_history($pdo, [$id]);
    $history = $priceHistory[$id] ?? [];
    $row['price_history'] = $history;
    $row['latest_price'] = $history[0] ?? null;
    return $row;
}

function components_parent_exists(PDO $pdo, int $parentId): bool
{

    $stmt = $pdo->prepare('SELECT 1 FROM components WHERE id = :id LIMIT 1');
    $stmt->bindValue(':id', $parentId, PDO::PARAM_INT);
    $stmt->execute();
    return (bool) $stmt->fetchColumn();
}

function components_children_count(PDO $pdo, ?int $parentId): int
{

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM components WHERE parent_id <=> :parent');
    if ($parentId === null) {
        $stmt->bindValue(':parent', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':parent', $parentId, PDO::PARAM_INT);
    }
    $stmt->execute();
    return (int) $stmt->fetchColumn();
}

function components_next_position(PDO $pdo, ?int $parentId): int
{

    $stmt = $pdo->prepare('SELECT COALESCE(MAX(position), -1) FROM components WHERE parent_id <=> :parent');
    if ($parentId === null) {
        $stmt->bindValue(':parent', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':parent', $parentId, PDO::PARAM_INT);
    }
    $stmt->execute();
    $max = (int) $stmt->fetchColumn();
    return $max + 1;
}

function components_reorder_positions(PDO $pdo, ?int $parentId): void
{

    $bump = $pdo->prepare('UPDATE components SET position = position + 1000000 WHERE parent_id <=> :parent');
    if ($parentId === null) {
        $bump->bindValue(':parent', null, PDO::PARAM_NULL);
    } else {
        $bump->bindValue(':parent', $parentId, PDO::PARAM_INT);
    }
    $bump->execute();
    $stmt = $pdo->prepare('SELECT id FROM components WHERE parent_id <=> :parent ORDER BY position, id');
    if ($parentId === null) {
        $stmt->bindValue(':parent', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':parent', $parentId, PDO::PARAM_INT);
    }
    $stmt->execute();
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $update = $pdo->prepare('UPDATE components SET position = :position WHERE id = :id');
    foreach ($ids as $index => $id) {
        $update->bindValue(':position', $index, PDO::PARAM_INT);
        $update->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $update->execute();
    }
}

function components_normalise_media_inputs(?string $image, ?string $color): array
{

    if ($image !== null) {
        $image = trim((string) $image);
        if ($image === '') {
            $image = null;
        }
    }

    if ($color !== null) {
        $color = trim((string) $color);
        if ($color === '') {
            $color = null;
        }
    }

    if ($image !== null && $color !== null) {
        $color = null;
    }

    return [$image, $color];
}

function components_insert_component_row(
    PDO $pdo,
    int $definitionId,
    ?int $parentId,
    ?string $alternateTitle,
    ?string $description,
    ?string $image,
    ?string $color,
    int $position
): int {
    if ($position < 0) {
        $position = 0;
    }

    components_reorder_positions($pdo, $parentId);
    $count = components_children_count($pdo, $parentId);
    if ($position > $count) {
        $position = $count;
    }

    [$imageValue, $colorValue] = components_normalise_media_inputs($image, $color);
    $alternate = $alternateTitle !== null ? trim((string) $alternateTitle) : null;
    if ($alternate === '') {
        $alternate = null;
    }

    $descriptionValue = $description !== null ? trim((string) $description) : null;
    if ($descriptionValue === '') {
        $descriptionValue = null;
    }

    $shift = $pdo->prepare(
        'UPDATE components SET position = position + 1 ' .
        'WHERE parent_id <=> :parent AND position >= :position'
    );
    if ($parentId === null) {
        $shift->bindValue(':parent', null, PDO::PARAM_NULL);
    } else {
        $shift->bindValue(':parent', $parentId, PDO::PARAM_INT);
    }
    $shift->bindValue(':position', $position, PDO::PARAM_INT);
    $shift->execute();
    $stmt = $pdo->prepare(
        <<<'SQL'
        INSERT INTO components (
            definition_id,
            parent_id,
            alternate_title,
            description,
            image,
            color,
            dependency_tree,
            position
        ) VALUES (
            :definition,
            :parent,
            :alternate,
            :description,
            :image,
            :color,
            :dependency,
            :position
        )
        SQL
    );
    $stmt->bindValue(':definition', $definitionId, PDO::PARAM_INT);
    if ($parentId === null) {
        $stmt->bindValue(':parent', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':parent', $parentId, PDO::PARAM_INT);
    }

    if ($alternate === null) {
        $stmt->bindValue(':alternate', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':alternate', $alternate, PDO::PARAM_STR);
    }

    if ($descriptionValue === null) {
        $stmt->bindValue(':description', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':description', $descriptionValue, PDO::PARAM_STR);
    }

    if ($imageValue === null) {
        $stmt->bindValue(':image', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':image', $imageValue, PDO::PARAM_STR);
    }

    if ($colorValue === null) {
        $stmt->bindValue(':color', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':color', $colorValue, PDO::PARAM_STR);
    }

    $stmt->bindValue(':dependency', json_encode([]), PDO::PARAM_STR);
    $stmt->bindValue(':position', $position, PDO::PARAM_INT);
    $stmt->execute();
    return (int) $pdo->lastInsertId();
}

function components_insert_price_entry(PDO $pdo, int $componentId, string $amount, string $currency = 'CZK'): void
{

    $currencyValue = strtoupper(substr(trim($currency), 0, 3));
    if ($currencyValue === '') {
        $currencyValue = 'CZK';
    }

    $stmt = $pdo->prepare(
        <<<'SQL'
        INSERT INTO prices (
            component_id,
            amount,
            currency
        ) VALUES (
            :component,
            :amount,
            :currency
        )
        SQL
    );
    $stmt->bindValue(':component', $componentId, PDO::PARAM_INT);
    $stmt->bindValue(':amount', $amount, PDO::PARAM_STR);
    $stmt->bindValue(':currency', $currencyValue, PDO::PARAM_STR);
    $stmt->execute();
}

function components_normalise_price_input(?string $rawInput): array
{

    $value = $rawInput !== null ? trim((string) $rawInput) : '';
    if ($value === '') {
        return [null, null];
    }

    $sanitised = str_replace(',', '.', preg_replace('/\s+/', '', $value));
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $sanitised)) {
        return [null, 'Cena musí být nezáporné číslo s nejvýše dvěma desetinnými místy.'];
    }

    [$whole, $fraction] = array_pad(explode('.', $sanitised, 2), 2, '');
    if ($fraction === '') {
        $fraction = '00';
    } else {
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');
    }

    $normalisedWhole = ltrim($whole, '0');
    if ($normalisedWhole === '') {
        $normalisedWhole = '0';
    }

    if (strlen($normalisedWhole) > 10) {
        return [null, 'Cena je příliš vysoká.'];
    }

    return [$normalisedWhole . '.' . $fraction, null];
}

function components_seed_definition_children(PDO $pdo, int $componentId, int $definitionId): void
{

    $children = definitions_fetch_children($pdo, $definitionId);
    if (empty($children)) {
        return;
    }

    $position = components_children_count($pdo, $componentId);
    foreach ($children as $child) {
        if (!isset($child['id'])) {
            continue;
        }
        $childDefinitionId = (int) $child['id'];
        if ($childDefinitionId <= 0) {
            continue;
        }

        $childId = components_insert_component_row(
            $pdo,
            $childDefinitionId,
            $componentId,
            null,
            null,
            null,
            null,
            $position
        );
        $position += 1;
        components_seed_definition_children($pdo, $childId, $childDefinitionId);
    }
}

function components_create(
    PDO $pdo,
    int $definitionId,
    ?int $parentId,
    ?string $alternateTitle,
    ?string $description,
    ?string $image,
    ?string $color,
    int $position,
    ?string $priceAmount = null,
    string $priceCurrency = 'CZK'
): array {
    $pdo->beginTransaction();
    try {
        if (!definitions_find($pdo, $definitionId)) {
            throw new RuntimeException('Vybraná definice neexistuje.');
        }
        if ($parentId !== null && !components_parent_exists($pdo, $parentId)) {
            throw new RuntimeException('Vybraný rodič neexistuje.');
        }
        $componentId = components_insert_component_row(
            $pdo,
            $definitionId,
            $parentId,
            $alternateTitle,
            $description,
            $image,
            $color,
            $position
        );
        components_seed_definition_children($pdo, $componentId, $definitionId);
        if ($priceAmount !== null) {
            components_insert_price_entry($pdo, $componentId, $priceAmount, $priceCurrency);
        }

        $pdo->commit();
        $row = components_find($pdo, $componentId);
        if (!$row) {
            throw new RuntimeException('Komponentu se nepodařilo načíst po vložení.');
        }
        return $row;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function components_normalise_dependency_tree($raw): array
{

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

function components_effective_title(array $row): string
{

    $alt = isset($row['alternate_title']) ? trim((string) $row['alternate_title']) : '';
    if ($alt !== '') {
        return $alt;
    }
    return isset($row['definition_title']) ? (string) $row['definition_title'] : '';
}

function components_group_by_parent(array $rows): array
{

    $grouped = [];
    foreach ($rows as $row) {
        $key = $row['parent_id'] === null ? 'root' : (string) $row['parent_id'];
        $grouped[$key][] = $row;
    }
    return $grouped;
}

function components_build_branch(array $grouped, string $key): array
{

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

function components_build_tree(array $rows): array
{

    $grouped = components_group_by_parent($rows);
    $tree = components_build_branch($grouped, 'root');
    log_message('Built component tree with ' . count($tree) . ' root nodes', 'DEBUG');
    return $tree;
}

function components_flatten_tree(array $tree, int $depth = 0): array
{

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

function components_fetch_tree(?PDO $pdo = null): array
{

    $rows = components_fetch_rows($pdo);
    return components_build_tree($rows);
}

function components_is_descendant(PDO $pdo, int $ancestorId, int $candidateId): bool
{

    if ($ancestorId === $candidateId) {
        return true;
    }

    $stmt = $pdo->prepare('SELECT parent_id FROM components WHERE id = :id LIMIT 1');
    $current = $candidateId;
    $visited = [];
    while ($current !== null) {
        if (isset($visited[$current])) {
            return false;
        }

        $visited[$current] = true;
        $stmt->bindValue(':id', $current, PDO::PARAM_INT);
        $stmt->execute();
        $parent = $stmt->fetchColumn();
        $stmt->closeCursor();
        if ($parent === false || $parent === null) {
            return false;
        }

        $parentId = (int) $parent;
        if ($parentId === $ancestorId) {
            return true;
        }

        $current = $parentId;
    }

    return false;
}

function components_update(
    PDO $pdo,
    int $componentId,
    int $definitionId,
    ?int $parentId,
    ?string $alternateTitle,
    ?string $description,
    ?string $image,
    ?string $color,
    ?int $position,
    ?string $priceAmount = null,
    string $priceCurrency = 'CZK'
): array {
    $pdo->beginTransaction();
    try {
        $current = components_find($pdo, $componentId);
        if (!$current) {
            throw new RuntimeException('Komponentu se nepodařilo najít.');
        }

        if (!definitions_find($pdo, $definitionId)) {
            throw new RuntimeException('Vybraná definice neexistuje.');
        }

        if ($parentId !== null) {
            if ($parentId === $componentId) {
                throw new RuntimeException('Komponenta nemůže být sama sobě rodičem.');
            }

            if (!components_parent_exists($pdo, $parentId)) {
                throw new RuntimeException('Vybraný rodičovský prvek neexistuje.');
            }

            if (components_is_descendant($pdo, $componentId, $parentId)) {
                throw new RuntimeException('Nelze přesunout komponentu pod jejího potomka.');
            }
        }

        $oldParentId = $current['parent_id'] === null ? null : (int) $current['parent_id'];
    // position is UNSIGNED in the schema, so avoid temporary negative sentinels when detaching
        $detach = $pdo->prepare('UPDATE components SET parent_id = NULL WHERE id = :id');
        $detach->bindValue(':id', $componentId, PDO::PARAM_INT);
        $detach->execute();
        components_reorder_positions($pdo, $oldParentId);
        if ($parentId !== null && $parentId !== $oldParentId) {
            components_reorder_positions($pdo, $parentId);
        } elseif ($parentId === null && $oldParentId !== null) {
            components_reorder_positions($pdo, null);
        }

        $childCount = components_children_count($pdo, $parentId);
        if ($position === null || $position < 0) {
            $position = $childCount;
        } elseif ($position > $childCount) {
            $position = $childCount;
        }

        if ($image !== null) {
            $image = trim((string) $image);
            if ($image === '') {
                $image = null;
            }
        }
        if ($color !== null) {
            $color = trim((string) $color);
            if ($color === '') {
                $color = null;
            }
        }
        if ($image !== null && $color !== null) {
            $color = null;
        }

        $shift = $pdo->prepare(
            'UPDATE components SET position = position + 1 ' .
            'WHERE parent_id <=> :parent AND position >= :position'
        );
        if ($parentId === null) {
            $shift->bindValue(':parent', null, PDO::PARAM_NULL);
        } else {
            $shift->bindValue(':parent', $parentId, PDO::PARAM_INT);
        }
        $shift->bindValue(':position', $position, PDO::PARAM_INT);
        $shift->execute();
        $update = $pdo->prepare(
            <<<'SQL'
            UPDATE components
            SET definition_id = :definition,
                parent_id = :parent,
                alternate_title = :alternate,
                description = :description,
                image = :image,
                color = :color,
                position = :position
            WHERE id = :id
            SQL
        );
        $update->bindValue(':definition', $definitionId, PDO::PARAM_INT);
        if ($parentId === null) {
            $update->bindValue(':parent', null, PDO::PARAM_NULL);
        } else {
            $update->bindValue(':parent', $parentId, PDO::PARAM_INT);
        }
        if ($alternateTitle === null || $alternateTitle === '') {
            $update->bindValue(':alternate', null, PDO::PARAM_NULL);
        } else {
            $update->bindValue(':alternate', $alternateTitle, PDO::PARAM_STR);
        }
        if ($description === null || $description === '') {
            $update->bindValue(':description', null, PDO::PARAM_NULL);
        } else {
            $update->bindValue(':description', $description, PDO::PARAM_STR);
        }
        if ($image === null || $image === '') {
            $update->bindValue(':image', null, PDO::PARAM_NULL);
        } else {
            $update->bindValue(':image', $image, PDO::PARAM_STR);
        }
        if ($color === null || $color === '') {
            $update->bindValue(':color', null, PDO::PARAM_NULL);
        } else {
            $update->bindValue(':color', $color, PDO::PARAM_STR);
        }
        $update->bindValue(':position', $position, PDO::PARAM_INT);
        $update->bindValue(':id', $componentId, PDO::PARAM_INT);
        $update->execute();
        if ($priceAmount !== null) {
            components_insert_price_entry($pdo, $componentId, $priceAmount, $priceCurrency);
        }

        $pdo->commit();
        $row = components_find($pdo, $componentId);
        if (!$row) {
            throw new RuntimeException('Komponentu se nepodařilo načíst po aktualizaci.');
        }

        return $row;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function components_delete(PDO $pdo, int $componentId): void
{

    $pdo->beginTransaction();
    try {
        $current = components_find($pdo, $componentId);
        if (!$current) {
            throw new RuntimeException('Komponenta nebyla nalezena.');
        }

        $stmt = $pdo->prepare('DELETE FROM components WHERE id = :id');
        $stmt->bindValue(':id', $componentId, PDO::PARAM_INT);
        $stmt->execute();
        $parentId = $current['parent_id'] === null ? null : (int) $current['parent_id'];
        components_reorder_positions($pdo, $parentId);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
