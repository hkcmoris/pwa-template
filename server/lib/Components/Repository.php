<?php

declare(strict_types=1);

namespace Components;

use Definitions\Repository as DefinitionsRepository;
use PDO;
use RuntimeException;
use Throwable;

use function log_message;

final class Repository
{
    private PDO $pdo;

    private Formatter $formatter;

    private DefinitionsRepository $definitions;

    public function __construct(PDO $pdo, ?Formatter $formatter = null, ?DefinitionsRepository $definitions = null)
    {
        $this->pdo = $pdo;
        $this->formatter = $formatter ?? new Formatter();
        $this->definitions = $definitions ?? new DefinitionsRepository($pdo);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchRows(): array
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

        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $componentIds = array_map(static fn($row) => isset($row['id']) ? (int) $row['id'] : 0, $rows);
        $priceHistoryMap = $this->fetchPriceHistory($componentIds);
        $normalised = [];

        foreach ($rows as $row) {
            $row['dependency_tree'] = $this->formatter->normaliseDependencyTree($row['dependency_tree'] ?? null);
            $row['effective_title'] = $this->formatter->effectiveTitle($row);
            $rowId = isset($row['id']) ? (int) $row['id'] : 0;
            $history = $priceHistoryMap[$rowId] ?? [];
            $row['price_history'] = $history;
            $row['latest_price'] = $history[0] ?? null;
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
     * @return array{0: ?string, 1: ?string}
     */
    private function normaliseMediaInputs(?string $image, ?string $color): array
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

    public function insertComponentRow(
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

        $this->reorderPositions($parentId);
        $count = $this->childrenCount($parentId);

        if ($position > $count) {
            $position = $count;
        }

        [$imageValue, $colorValue] = $this->normaliseMediaInputs($image, $color);
        $alternate = $alternateTitle !== null ? trim((string) $alternateTitle) : null;

        if ($alternate === '') {
            $alternate = null;
        }

        $descriptionValue = $description !== null ? trim((string) $description) : null;

        if ($descriptionValue === '') {
            $descriptionValue = null;
        }

        $shift = $this->pdo->prepare(
            'UPDATE components SET position = position + 1 '
            . 'WHERE parent_id <=> :parent AND position >= :position'
        );

        if ($parentId === null) {
            $shift->bindValue(':parent', null, PDO::PARAM_NULL);
        } else {
            $shift->bindValue(':parent', $parentId, PDO::PARAM_INT);
        }

        $shift->bindValue(':position', $position, PDO::PARAM_INT);
        $shift->execute();

        $stmt = $this->pdo->prepare(
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

        return (int) $this->pdo->lastInsertId();
    }

    public function insertPriceEntry(int $componentId, string $amount, string $currency = 'CZK'): void
    {
        $currencyValue = strtoupper(substr(trim($currency), 0, 3));

        if ($currencyValue === '') {
            $currencyValue = 'CZK';
        }

        $stmt = $this->pdo->prepare(
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

    public function seedDefinitionChildren(int $componentId, int $definitionId): void
    {
        $children = $this->definitions->fetchChildren($definitionId);

        if (empty($children)) {
            return;
        }

        $position = $this->childrenCount($componentId);

        foreach ($children as $child) {
            if (!isset($child['id'])) {
                continue;
            }

            $childDefinitionId = (int) $child['id'];

            if ($childDefinitionId <= 0) {
                continue;
            }

            $childId = $this->insertComponentRow(
                $childDefinitionId,
                $componentId,
                null,
                null,
                null,
                null,
                $position
            );

            $position += 1;
            $this->seedDefinitionChildren($childId, $childDefinitionId);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function create(
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
        $this->pdo->beginTransaction();

        try {
            if (!$this->definitions->find($definitionId)) {
                throw new RuntimeException('Vybraná definice neexistuje.');
            }

            if ($parentId !== null && !$this->parentExists($parentId)) {
                throw new RuntimeException('Vybraný rodič neexistuje.');
            }

            $componentId = $this->insertComponentRow(
                $definitionId,
                $parentId,
                $alternateTitle,
                $description,
                $image,
                $color,
                $position
            );

            $this->seedDefinitionChildren($componentId, $definitionId);

            if ($priceAmount !== null) {
                $this->insertPriceEntry($componentId, $priceAmount, $priceCurrency);
            }

            $this->pdo->commit();
            $row = $this->find($componentId);

            if (!$row) {
                throw new RuntimeException('Komponentu se nepodařilo načíst po vložení.');
            }

            return $row;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchTree(): array
    {
        $rows = $this->fetchRows();

        return $this->formatter->buildTree($rows);
    }

    public function isDescendant(int $ancestorId, int $candidateId): bool
    {
        if ($ancestorId === $candidateId) {
            return true;
        }

        $stmt = $this->pdo->prepare('SELECT parent_id FROM components WHERE id = :id LIMIT 1');
        $current = $candidateId;
        $visited = [];

        while (true) {
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
    }

    /**
     * @return array<string, mixed>
     */
    public function update(
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
        $this->pdo->beginTransaction();

        try {
            $current = $this->find($componentId);

            if (!$current) {
                throw new RuntimeException('Komponentu se nepodařilo najít.');
            }

            if (!$this->definitions->find($definitionId)) {
                throw new RuntimeException('Vybraná definice neexistuje.');
            }

            if ($parentId !== null) {
                if ($parentId === $componentId) {
                    throw new RuntimeException('Komponenta nemůže být sama sobě rodičem.');
                }

                if (!$this->parentExists($parentId)) {
                    throw new RuntimeException('Vybraný rodičovský prvek neexistuje.');
                }

                if ($this->isDescendant($componentId, $parentId)) {
                    throw new RuntimeException('Nelze přesunout komponentu pod jejího potomka.');
                }
            }

            $oldParentId = $current['parent_id'] === null ? null : (int) $current['parent_id'];
            $detach = $this->pdo->prepare('UPDATE components SET parent_id = NULL WHERE id = :id');
            $detach->bindValue(':id', $componentId, PDO::PARAM_INT);
            $detach->execute();
            $this->reorderPositions($oldParentId);

            if ($parentId !== null && $parentId !== $oldParentId) {
                $this->reorderPositions($parentId);
            } elseif ($parentId === null && $oldParentId !== null) {
                $this->reorderPositions(null);
            }

            $childCount = $this->childrenCount($parentId);

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

            $shift = $this->pdo->prepare(
                'UPDATE components SET position = position + 1 '
                . 'WHERE parent_id <=> :parent AND position >= :position'
            );

            if ($parentId === null) {
                $shift->bindValue(':parent', null, PDO::PARAM_NULL);
            } else {
                $shift->bindValue(':parent', $parentId, PDO::PARAM_INT);
            }

            $shift->bindValue(':position', $position, PDO::PARAM_INT);
            $shift->execute();

            $update = $this->pdo->prepare(
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

            if ($image === null) {
                $update->bindValue(':image', null, PDO::PARAM_NULL);
            } else {
                $update->bindValue(':image', $image, PDO::PARAM_STR);
            }

            if ($color === null) {
                $update->bindValue(':color', null, PDO::PARAM_NULL);
            } else {
                $update->bindValue(':color', $color, PDO::PARAM_STR);
            }

            $update->bindValue(':position', $position, PDO::PARAM_INT);
            $update->bindValue(':id', $componentId, PDO::PARAM_INT);
            $update->execute();

            if ($priceAmount !== null) {
                $this->insertPriceEntry($componentId, $priceAmount, $priceCurrency);
            }

            $this->pdo->commit();
            $row = $this->find($componentId);

            if (!$row) {
                throw new RuntimeException('Komponentu se nepodařilo načíst po aktualizaci.');
            }

            return $row;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function delete(int $componentId): void
    {
        $this->pdo->beginTransaction();

        try {
            $current = $this->find($componentId);

            if (!$current) {
                throw new RuntimeException('Komponenta nebyla nalezena.');
            }

            $stmt = $this->pdo->prepare('DELETE FROM components WHERE id = :id');
            $stmt->bindValue(':id', $componentId, PDO::PARAM_INT);
            $stmt->execute();
            $parentId = $current['parent_id'] === null ? null : (int) $current['parent_id'];
            $this->reorderPositions($parentId);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
