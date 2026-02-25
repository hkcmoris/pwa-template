<?php

declare(strict_types=1);

namespace Editor;

use Components\Formatter as ComponentFormatter;
use Components\Repository as ComponentRepository;
use Definitions\Formatter as DefinitionsFormatter;
use Definitions\Repository as DefinitionsRepository;
use InvalidArgumentException;

final class ComponentPresenter
{
    private ComponentRepository $components;

    private ComponentFormatter $componentFormatter;

    private DefinitionsRepository $definitions;

    private DefinitionsFormatter $definitionsFormatter;

    private int $pageSize;

    public function __construct(
        ComponentRepository $components,
        ComponentFormatter $componentFormatter,
        DefinitionsRepository $definitions,
        DefinitionsFormatter $definitionsFormatter,
        int $pageSize = 50
    ) {
        if ($pageSize <= 0) {
            throw new InvalidArgumentException('Component page size must be greater than zero.');
        }

        $this->components = $components;
        $this->componentFormatter = $componentFormatter;
        $this->definitions = $definitions;
        $this->definitionsFormatter = $definitionsFormatter;
        $this->pageSize = $pageSize;
    }

    /**
     * @param array{message?: string|null, message_type?: string} $options
     *
     * @return array{
     *     summary: array{totalComponents: int},
     *     createForm: array{
     *         definitionsFlat: array<int, array<string, mixed>>,
     *         componentsFlat: array<int, array<string, mixed>>
     *     },
     *     listHtml: array{
     *         componentsPage: array<int, array<string, mixed>>,
     *         componentPageSize: int,
     *         totalComponents: int,
     *         nextOffset: int,
     *         hasMore: bool,
     *         offset: int
     *     },
     *     message: array{content: string|null, type: string}
     * }
     */
    public function presentInitial(array $options = []): array
    {
        $componentsFlat = $this->fetchComponentsFlat();
        $definitionsFlat = $this->fetchDefinitionsFlat();
        $listData = $this->buildListData($componentsFlat, 0);

        $message = $options['message'] ?? null;
        $messageType = $options['message_type'] ?? 'success';

        return [
            'summary' => [
                'totalComponents' => $listData['totalComponents'],
            ],
            'createForm' => [
                'definitionsFlat' => $definitionsFlat,
                'componentsFlat' => $componentsFlat,
            ],
            'listHtml' => $listData,
            'message' => [
                'content' => $message,
                'type' => $messageType,
            ],
        ];
    }

    /**
     * @return array{
     *     componentsPage: array<int, array<string, mixed>>,
     *     componentPageSize: int,
     *     totalComponents: int,
     *     nextOffset: int,
     *     hasMore: bool,
     *     offset: int
     * }
     */
    public function presentPage(int $offset): array
    {
        $componentsFlat = $this->fetchComponentsFlat();

        return $this->buildListData($componentsFlat, $offset);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchComponentsFlat(): array
    {
        $tree = $this->components->fetchTree();

        return $this->componentFormatter->flattenTree($tree);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchDefinitionsFlat(): array
    {
        $tree = $this->definitions->fetchTree($this->definitionsFormatter);

        return $this->definitionsFormatter->flattenTree($tree);
    }

    /**
     * @param array<int, array<string, mixed>> $componentsFlat
     *
     * @return array{
     *     componentsPage: array<int, array<string, mixed>>,
     *     componentPageSize: int,
     *     totalComponents: int,
     *     nextOffset: int,
     *     hasMore: bool,
     *     offset: int
     * }
     */
    private function buildListData(array $componentsFlat, int $offset): array
    {
        $total = count($componentsFlat);
        $normalisedOffset = $this->normaliseOffset($offset, $total);
        $page = array_slice($componentsFlat, $normalisedOffset, $this->pageSize);
        $nextOffset = $normalisedOffset + count($page);
        $hasMore = $nextOffset < $total;

        return [
            'componentsPage' => $page,
            'componentPageSize' => $this->pageSize,
            'totalComponents' => $total,
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
