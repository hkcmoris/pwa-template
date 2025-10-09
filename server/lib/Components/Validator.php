<?php

declare(strict_types=1);

namespace Components;

use Definitions\Repository as DefinitionsRepository;
use RuntimeException;

final class Validator
{
    private DefinitionsRepository $definitions;

    private QueryService $queries;

    public function __construct(DefinitionsRepository $definitions, QueryService $queries)
    {
        $this->definitions = $definitions;
        $this->queries = $queries;
    }

    public function assertDefinitionExists(int $definitionId, string $message = 'Vybraná definice neexistuje.'): void
    {
        if (!$this->definitions->find($definitionId)) {
            throw new RuntimeException($message);
        }
    }

    public function assertParentExists(?int $parentId, string $message): void
    {
        if ($parentId !== null && !$this->queries->parentExists($parentId)) {
            throw new RuntimeException($message);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function findComponentOrFail(int $componentId, string $message): array
    {
        $component = $this->queries->find($componentId);

        if (!$component) {
            throw new RuntimeException($message);
        }

        return $component;
    }

    public function assertParentChangeIsValid(int $componentId, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        if ($parentId === $componentId) {
            throw new RuntimeException('Komponenta nemůže být sama sobě rodičem.');
        }

        $this->assertParentExists($parentId, 'Vybraný rodičovský prvek neexistuje.');

        if ($this->isDescendant($componentId, $parentId)) {
            throw new RuntimeException('Nelze přesunout komponentu pod jejího potomka.');
        }
    }

    public function isDescendant(int $ancestorId, int $candidateId): bool
    {
        if ($ancestorId === $candidateId) {
            return true;
        }

        $current = $candidateId;
        $visited = [];

        while (true) {
            if (isset($visited[$current])) {
                return false;
            }

            $visited[$current] = true;
            $parentId = $this->queries->fetchParentId($current);

            if ($parentId === null) {
                return false;
            }

            if ($parentId === $ancestorId) {
                return true;
            }

            $current = $parentId;
        }
    }
}
