<?php

declare(strict_types=1);

namespace Components;

final class TreeBuilder
{
    private QueryService $queries;

    private Formatter $formatter;

    public function __construct(QueryService $queries, Formatter $formatter)
    {
        $this->queries = $queries;
        $this->formatter = $formatter;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchTree(): array
    {
        $rows = $this->queries->fetchRows();

        return $this->formatter->buildTree($rows);
    }
}
