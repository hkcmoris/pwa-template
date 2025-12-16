<?php

declare(strict_types=1);

namespace Configuration;

use PDO;

use function log_message;

final class QueryService
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetch(int $userId, ?int $limit = null, int $offset = 0): array
    {
        $sql = <<<'SQL'
        SELECT
            c.id,
            c.user_id,
            c.created_at,
            c.updated_at
        FROM configurations c
        WHERE c.user_id = :user_id
        ORDER BY c.updated_at, c.created_at, c.id
        SQL;

        if ($limit !== null) {
            if ($limit <= 0) {
                $limit = 1;
            }

            if ($offset < 0) {
                $offset = 0;
            }

            $sql .= ' LIMIT :limit OFFSET :offset';
        }

        $stmt = $this->pdo->prepare($sql);

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);

        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }

        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }
}