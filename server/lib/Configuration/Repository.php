<?php

declare(strict_types=1);

namespace Configuration;

use PDO;

final class Repository
{
    // Repository implementation
    private PDO $pdo;

    private QueryService $queries;

    public function __construct(
        PDO $pdo
    ) {
        $this->pdo = $pdo;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetch(int $userId, ?int $limit = null, int $offset = 0): array
    {
        return $this->queries->fetch($userId, $limit, $offset);
    }
}
?>