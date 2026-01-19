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
     * @return array<int, array{
     *    id: int,
     *    user_id: int,
     *    created_at: string,
     *    updated_at: string
     * }>
     */
    public function fetch(?int $limit = null, int $offset = 0, ?int $userId = null): array
    {
        $sql = <<<'SQL'
        SELECT
            c.id,
            c.user_id,
            c.created_at,
            c.updated_at
        FROM configurations c
        SQL;
        if ($userId !== null) {
            $sql .= ' WHERE c.user_id = :user_id';
        }
        $sql .= <<<'SQL'
        ORDER BY c.updated_at DESC, c.created_at DESC, c.id DESC
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

        if ($userId !== null) {
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        }
        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }

        $stmt->execute();
        log_message('Fetched ' . $stmt->rowCount() . ' configurations from database', 'DEBUG');
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    /**
     * @return array<int, array{
     *    id: int,
     *    configuration_id: int,
     *    component_id: int,
     *    position: int
     * }>
     */
    public function fetchRows(int $id): array
    {
        $sql = <<<'SQL'
        SELECT
            id,
            configuration_id,
            component_id,
            position
        FROM configuration_options
        WHERE configuration_id = :configuration_id
        ORDER BY position, id
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':configuration_id', $id, PDO::PARAM_INT);
        $stmt->execute();
        log_message('Fetched ' . $stmt->rowCount() . ' configuration components from database', 'DEBUG');
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    /**
     * @return array{
     *     id: int,
     *     user_id: int,
     *     created_at: string,
     *     updated_at: string
     * }|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, created_at, updated_at FROM configurations WHERE id = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
