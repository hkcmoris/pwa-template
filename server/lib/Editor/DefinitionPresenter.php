<?php

declare(strict_types=1);

namespace Editor;

use Definitions\Formatter;
use Definitions\Repository;
use InvalidArgumentException;

final class DefinitionPresenter
{
    private Repository $definitions;

    private Formatter $formatter;

    private int $pageSize;

    public function __construct(Repository $definitions, Formatter $formatter, int $pageSize = 50)
    {
        if ($pageSize <= 0) {
            throw new InvalidArgumentException('Definition page size must be greater than zero.');
        }

        $this->definitions = $definitions;
        $this->formatter = $formatter;
        $this->pageSize = $pageSize;
    }

    /**
     * @return array{
     *     definitionsPage: array<int, array<string, mixed>>,
     *     definitionPageSize: int,
     *     totalDefinitions: int,
     *     nextOffset: int,
     *     hasMore: bool,
     *     offset: int
     * }
     */
    public function presentPage(int $offset): array
    {
        $tree = $this->definitions->fetchTree($this->formatter);

        return $this->buildListData($tree, $offset);
    }

    /**
     * @param array<int, array<string, mixed>> $tree
     *
     * @return array{
     *     definitionsPage: array<int, array<string, mixed>>,
     *     definitionPageSize: int,
     *     totalDefinitions: int,
     *     nextOffset: int,
     *     hasMore: bool,
     *     offset: int
     * }
     */
    public function buildListData(array $tree, int $offset): array
    {
        $total = count($tree);
        $normalisedOffset = $this->normaliseOffset($offset, $total);
        $page = array_slice($tree, $normalisedOffset, $this->pageSize);
        $nextOffset = $normalisedOffset + count($page);
        $hasMore = $nextOffset < $total;

        return [
            'definitionsPage' => $page,
            'definitionPageSize' => $this->pageSize,
            'totalDefinitions' => $total,
            'nextOffset' => $nextOffset,
            'hasMore' => $hasMore,
            'offset' => $normalisedOffset,
        ];
    }

    private function normaliseOffset(int $offset, int $total): int
    {
        if ($total <= 0) {
            return 0;
        }

        if ($offset < 0) {
            $offset = 0;
        }

        $maxOffset = max(0, $total - 1);
        if ($offset > $maxOffset) {
            $offset = $maxOffset;
        }

        return $offset;
    }
}
