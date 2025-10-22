<?php

declare(strict_types=1);

namespace Components;

use Definitions\Repository as DefinitionsRepository;
use PDO;
use RuntimeException;
use Throwable;

final class Repository
{
    private PDO $pdo;

    private Formatter $formatter;

    private DefinitionsRepository $definitions;

    private QueryService $queries;

    private TreeBuilder $treeBuilder;

    private Validator $validator;

    public function __construct(
        PDO $pdo,
        ?Formatter $formatter = null,
        ?DefinitionsRepository $definitions = null,
        ?QueryService $queries = null,
        ?TreeBuilder $treeBuilder = null,
        ?Validator $validator = null
    ) {
        $this->pdo = $pdo;
        $this->formatter = $formatter ?? new Formatter();
        $this->definitions = $definitions ?? new DefinitionsRepository($pdo);
        $this->queries = $queries ?? new QueryService($pdo, $this->formatter);
        $this->treeBuilder = $treeBuilder ?? new TreeBuilder($this->queries, $this->formatter);
        $this->validator = $validator ?? new Validator($this->definitions, $this->queries);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchRows(?int $limit = null, int $offset = 0): array
    {
        return $this->queries->fetchRows($limit, $offset);
    }

    /**
     * @param array<int, int|string> $componentIds
     * @return array<int, array<int, array{amount: string, currency: string, created_at: string}>>
     */
    public function fetchPriceHistory(array $componentIds, int $limitPerComponent = 10): array
    {
        return $this->queries->fetchPriceHistory($componentIds, $limitPerComponent);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->queries->find($id);
    }

    public function parentExists(int $parentId): bool
    {
        return $this->queries->parentExists($parentId);
    }

    public function childrenCount(?int $parentId): int
    {
        return $this->queries->childrenCount($parentId);
    }

    public function nextPosition(?int $parentId): int
    {
        return $this->queries->nextPosition($parentId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchTree(): array
    {
        return $this->treeBuilder->fetchTree();
    }

    public function countAll(): int
    {
        return $this->queries->countAll();
    }

    public function isDescendant(int $ancestorId, int $candidateId): bool
    {
        return $this->validator->isDescendant($ancestorId, $candidateId);
    }

    /**
     * @param array<int, scalar|null>|null $images
     * @return array{0: array<int, string>, 1: ?string, 2: ?string}
     */
    private function resolveMediaInputs(?array $images, ?string $color): array
    {
        $normalisedImages = [];

        if ($images !== null) {
            foreach ($images as $value) {
                if (!is_string($value)) {
                    continue;
                }

                $trimmed = trim($value);

                if ($trimmed === '') {
                    continue;
                }

                if (!in_array($trimmed, $normalisedImages, true)) {
                    $normalisedImages[] = $trimmed;
                }
            }
        }

        if ($color !== null) {
            $color = trim((string) $color);
            if ($color === '') {
                $color = null;
            }
        }

        if (!empty($normalisedImages)) {
            $primary = $normalisedImages[0];
            $color = null;
        } else {
            $primary = null;
            $normalisedImages = [];
        }

        return [$normalisedImages, $primary, $color];
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
     * @param array<int, scalar|null> $images
     */
    public function insertComponentRow(
        int $definitionId,
        ?int $parentId,
        ?string $alternateTitle,
        ?string $description,
        array $images,
        ?string $color,
        int $position
    ): int {
        if ($position < 0) {
            $position = 0;
        }

        $this->reorderPositions($parentId);
        $count = $this->queries->childrenCount($parentId);

        if ($position > $count) {
            $position = $count;
        }

        [$imagesValue, $primaryImage, $colorValue] = $this->resolveMediaInputs($images, $color);
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
                images,
                color,
                dependency_tree,
                position
            ) VALUES (
                :definition,
                :parent,
                :alternate,
                :description,
                :images,
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

        $stmt->bindValue(':images', json_encode($imagesValue), PDO::PARAM_STR);

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

        $position = $this->queries->childrenCount($componentId);

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
                [],
                null,
                $position
            );

            $position += 1;
            $this->seedDefinitionChildren($childId, $childDefinitionId);
        }
    }

    /**
     * @param array<int, scalar|null> $images
     * @return array<string, mixed>
     */
    public function create(
        int $definitionId,
        ?int $parentId,
        ?string $alternateTitle,
        ?string $description,
        array $images,
        ?string $color,
        int $position,
        ?string $priceAmount = null,
        string $priceCurrency = 'CZK'
    ): array {
        $this->pdo->beginTransaction();

        try {
            $this->validator->assertDefinitionExists($definitionId);
            $this->validator->assertParentExists($parentId, 'Vybraný rodič neexistuje.');

            $componentId = $this->insertComponentRow(
                $definitionId,
                $parentId,
                $alternateTitle,
                $description,
                $images,
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
     * @param array<int, scalar|null> $images
     * @return array<string, mixed>
     */
    public function update(
        int $componentId,
        int $definitionId,
        ?int $parentId,
        ?string $alternateTitle,
        ?string $description,
        array $images,
        ?string $color,
        ?int $position,
        ?string $priceAmount = null,
        string $priceCurrency = 'CZK'
    ): array {
        $this->pdo->beginTransaction();

        try {
            $current = $this->validator->findComponentOrFail($componentId, 'Komponentu se nepodařilo najít.');
            $this->validator->assertDefinitionExists($definitionId);
            $this->validator->assertParentChangeIsValid($componentId, $parentId);

            $oldParentId = $current['parent_id'] === null ? null : (int) $current['parent_id'];
            $oldPosition = isset($current['position']) ? (int) $current['position'] : 0;
            $sameParent = ($oldParentId === null && $parentId === null)
                || ($oldParentId !== null && $parentId !== null && $oldParentId === $parentId);

            $childCount = $this->queries->childrenCount($parentId);

            if ($sameParent) {
                $childCount = max(0, $childCount - 1);
            }

            if ($position === null || $position < 0) {
                $position = $childCount;
            } elseif ($position > $childCount) {
                $position = $childCount;
            }

            $needsReorder = !$sameParent || $position !== $oldPosition;

            if ($needsReorder) {
                $detach = $this->pdo->prepare(
                    'UPDATE components SET parent_id = NULL, position = NULL WHERE id = :id'
                );

                $detach->bindValue(':id', $componentId, PDO::PARAM_INT);
                $detach->execute();
            }

            if (!$sameParent) {
                $closeGap = $this->pdo->prepare(
                    'UPDATE components SET position = position - 1 '
                    . 'WHERE parent_id <=> :parent AND position > :position'
                );

                if ($oldParentId === null) {
                    $closeGap->bindValue(':parent', null, PDO::PARAM_NULL);
                } else {
                    $closeGap->bindValue(':parent', $oldParentId, PDO::PARAM_INT);
                }

                $closeGap->bindValue(':position', $oldPosition, PDO::PARAM_INT);
                $closeGap->execute();

                $openGap = $this->pdo->prepare(
                    'UPDATE components SET position = position + 1 '
                    . 'WHERE parent_id <=> :parent AND position >= :position'
                );

                if ($parentId === null) {
                    $openGap->bindValue(':parent', null, PDO::PARAM_NULL);
                } else {
                    $openGap->bindValue(':parent', $parentId, PDO::PARAM_INT);
                }

                $openGap->bindValue(':position', $position, PDO::PARAM_INT);
                $openGap->execute();
            } elseif ($position !== $oldPosition) {
                if ($position > $oldPosition) {
                    $shiftDown = $this->pdo->prepare(
                        'UPDATE components SET position = position - 1 '
                        . 'WHERE parent_id <=> :parent AND position > :old AND position <= :new'
                    );

                    if ($parentId === null) {
                        $shiftDown->bindValue(':parent', null, PDO::PARAM_NULL);
                    } else {
                        $shiftDown->bindValue(':parent', $parentId, PDO::PARAM_INT);
                    }

                    $shiftDown->bindValue(':old', $oldPosition, PDO::PARAM_INT);
                    $shiftDown->bindValue(':new', $position, PDO::PARAM_INT);
                    $shiftDown->execute();
                } else {
                    $shiftUp = $this->pdo->prepare(
                        'UPDATE components SET position = position + 1 '
                        . 'WHERE parent_id <=> :parent AND position >= :new AND position < :old'
                    );

                    if ($parentId === null) {
                        $shiftUp->bindValue(':parent', null, PDO::PARAM_NULL);
                    } else {
                        $shiftUp->bindValue(':parent', $parentId, PDO::PARAM_INT);
                    }

                    $shiftUp->bindValue(':new', $position, PDO::PARAM_INT);
                    $shiftUp->bindValue(':old', $oldPosition, PDO::PARAM_INT);
                    $shiftUp->execute();
                }
            }

            [$imagesValue, $primaryImage, $colorValue] = $this->resolveMediaInputs($images, $color);
            $alternate = $alternateTitle !== null ? trim((string) $alternateTitle) : null;

            if ($alternate === '') {
                $alternate = null;
            }

            $descriptionValue = $description !== null ? trim((string) $description) : null;

            if ($descriptionValue === '') {
                $descriptionValue = null;
            }

            $update = $this->pdo->prepare(
                <<<'SQL'
                UPDATE components
                SET definition_id = :definition,
                    parent_id = :parent,
                    alternate_title = :alternate,
                    description = :description,
                    images = :images,
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

            if ($alternate === null) {
                $update->bindValue(':alternate', null, PDO::PARAM_NULL);
            } else {
                $update->bindValue(':alternate', $alternate, PDO::PARAM_STR);
            }

            if ($descriptionValue === null) {
                $update->bindValue(':description', null, PDO::PARAM_NULL);
            } else {
                $update->bindValue(':description', $descriptionValue, PDO::PARAM_STR);
            }

            $update->bindValue(':images', json_encode($imagesValue), PDO::PARAM_STR);

            if ($colorValue === null) {
                $update->bindValue(':color', null, PDO::PARAM_NULL);
            } else {
                $update->bindValue(':color', $colorValue, PDO::PARAM_STR);
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
            $current = $this->validator->findComponentOrFail($componentId, 'Komponenta nebyla nalezena.');

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
