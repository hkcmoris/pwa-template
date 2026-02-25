<?php

declare(strict_types=1);

namespace Components;

use PDO;
use RuntimeException;
use Definitions\Repository as DefinitionsRepository;
use Shared\PositionService;

/**
 * @phpstan-type PriceEntry array{
 *   amount: string,
 *   currency: string,
 *   created_at: string
 * }
 *
 * @phpstan-type ComponentRow array{
 *   id: int,
 *   definition_id: int,
 *   parent_id: int|null,
 *   alternate_title: string|null,
 *   description: string|null,
 *   images: list<string>,
 *   color: string|null,
 *   dependency_tree: array<string, mixed>|list<mixed>,
 *   position: int,
 *   created_at: string,
 *   updated_at: string,
 *   definition_title: string,
 *   image: string|null,
 *   effective_title: string,
 *   price_history: list<PriceEntry>,
 *   latest_price: PriceEntry|null
 * }
 */
final class Repository
{
    private Formatter $formatter;

    private DefinitionsRepository $definitions;

    private QueryService $queries;

    private WriteService $writeService;

    private PositionService $positionService;

    private TreeBuilder $treeBuilder;

    private Validator $validator;

    public function __construct(
        PDO $pdo,
        ?Formatter $formatter = null,
        ?DefinitionsRepository $definitions = null,
        ?QueryService $queries = null,
        ?TreeBuilder $treeBuilder = null,
        ?Validator $validator = null,
        ?WriteService $writeService = null
    ) {
        $this->formatter = $formatter ?? new Formatter();
        $this->definitions = $definitions ?? new DefinitionsRepository($pdo);
        $this->queries = $queries ?? new QueryService($pdo, $this->formatter);
        $this->positionService = new PositionService($pdo, 'components');
        $this->validator = $validator ?? new Validator($this->definitions, $this->queries);
        $this->writeService = $writeService ?? new WriteService(
            $pdo,
            $this->queries,
            $this->validator,
            $this->positionService,
            $this->definitions
        );
        $this->treeBuilder = $treeBuilder ?? new TreeBuilder($this->queries, $this->formatter);
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
     * @return ComponentRow|null
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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchChildren(?int $parentId): array
    {
        return $this->queries->fetchChildren($parentId);
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
     * @param array<int, scalar|null> $images
     * @param array<int, mixed> $properties
     * @param array<int, mixed> $dependencyTree
     * @return ComponentRow
     */
    public function create(
        int $definitionId,
        ?int $parentId,
        ?string $alternateTitle,
        ?string $description,
        array $images,
        ?string $color,
        array $properties,
        array $dependencyTree,
        int $position,
        ?string $priceAmount = null,
        string $priceCurrency = 'CZK'
    ): array {
        $componentId = $this->writeService->create(
            $definitionId,
            $parentId,
            $alternateTitle,
            $description,
            $images,
            $color,
            $properties,
            $dependencyTree,
            $position,
            $priceAmount,
            $priceCurrency
        );

        $row = $this->find($componentId);

        if (!$row) {
            throw new RuntimeException('Komponentu se nepodařilo načíst po vložení.');
        }

        return $row;
    }

    /**
     * @param array<int, scalar|null> $images
     * @param array<int, mixed> $properties
     * @param array<int, mixed> $dependencyTree
     * @return ComponentRow
     */
    public function update(
        int $componentId,
        int $definitionId,
        ?int $parentId,
        ?string $alternateTitle,
        ?string $description,
        array $images,
        ?string $color,
        array $properties,
        array $dependencyTree,
        ?int $position,
        ?string $priceAmount = null,
        string $priceCurrency = 'CZK'
    ): array {
        $componentId = $this->writeService->update(
            $componentId,
            $definitionId,
            $parentId,
            $alternateTitle,
            $description,
            $images,
            $color,
            $properties,
            $dependencyTree,
            $position,
            $priceAmount,
            $priceCurrency
        );

        $row = $this->find($componentId);

        if (!$row) {
            throw new RuntimeException('Komponentu se nepodařilo načíst po aktualizaci.');
        }

        return $row;
    }

    public function move(int $componentId, ?int $parentId, int $position): void
    {
        $this->writeService->move($componentId, $parentId, $position);
    }

    public function delete(int $componentId): void
    {
        $this->writeService->delete($componentId);
    }

    /**
     * @return ComponentRow
     */
    public function clone(int $componentId): array
    {
        $cloneId = $this->writeService->clone($componentId);
        $row = $this->find($cloneId);

        if (!$row) {
            throw new RuntimeException('Klonovanou komponentu se nepodařilo načíst.');
        }

        return $row;
    }
}
