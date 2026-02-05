<?php

declare(strict_types=1);

namespace Configuration;

use Components\Repository as ComponentsRepository;
use PDO;
use RuntimeException;
use Throwable;

use function get_db_connection;
use function log_message;

final class Repository
{
    // Repository implementation
    private PDO $pdo;

    private Formatter $formatter;

    private ComponentsRepository $components;

    private QueryService $queries;

    private Validator $validator;

    public function __construct(
        ?PDO $pdo = null,
        ?Formatter $formatter = null,
        ?ComponentsRepository $components = null,
        ?QueryService $queries = null,
        ?Validator $validator = null
    ) {
        $this->pdo = $pdo ?? get_db_connection();
        $this->formatter = $formatter ?? new Formatter();
        $this->components = $components ?? new ComponentsRepository($this->pdo);
        $this->queries = $queries ?? new QueryService($this->pdo);
        $this->validator = $validator ?? new Validator($this->components, $this->queries);
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
        return $this->queries->fetch($limit, $offset, $userId);
    }

    public function countAll(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM configurations');

        return (int) $stmt->fetchColumn();
    }

    public function countByUser(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM configurations WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function userExists(int $userId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE id = :id');
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false;
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
        return $this->queries->find($id);
    }

    /**
     * @return array{
     *     id: int,
     *     user_id: int,
     *     created_at: string,
     *     updated_at: string,
     *     children: list<array{id: int,configuration_id: int,component_id: int,position: int}>
     * }
     */
    public function fetchConfiguration(int $configurationId): array
    {
        $meta = $this->find($configurationId);
        if ($meta === null) {
            throw new RuntimeException('Konfigurace s ID ' . $configurationId . ' nebyla nalezena.');
        }

        $rows = $this->queries->fetchRows($configurationId);

        return $this->formatter->buildConfiguration($meta, $rows);
    }

    /**
     * @return array{
     *     id: int,
     *     user_id: int,
     *     created_at: string,
     *     updated_at: string
     * }
     */
    public function create(int $userId): array
    {
        $message = 'Creating configuration for user=' . $userId;
        $this->pdo->beginTransaction();
        try {
            if (!$this->userExists($userId)) {
                log_message('User ID ' . $userId . ' does not exist.', 'ERROR');
                throw new RuntimeException('Vybraný uživatel neexistuje.');
            }
            $stmt = $this->pdo->prepare(
                'INSERT INTO configurations (user_id) VALUES (:user_id)'
            );
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $id = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();
            $row = $this->find($id);
            if (!$row) {
                log_message('Failed to find configuration after insert with ID ' . $id, 'ERROR');
                throw new RuntimeException('Konfigurace nebyla nalezena po vložení.');
            }
            return $row;
        } catch (Throwable $e) {
            log_message('Error during configuration creation: ' . $e->getMessage(), 'ERROR');
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @param list<int> $componentIds
     */
    public function replaceOptions(int $configurationId, array $componentIds): void
    {
        $this->validator->assertConfigurationExists($configurationId);

        $this->pdo->beginTransaction();
        try {
            $delete = $this->pdo->prepare(
                'DELETE FROM configuration_options WHERE configuration_id = :configuration_id'
            );
            $delete->bindValue(':configuration_id', $configurationId, PDO::PARAM_INT);
            $delete->execute();

            if (!empty($componentIds)) {
                $insert = $this->pdo->prepare(
                    'INSERT INTO configuration_options (configuration_id, component_id, position)
                     VALUES (:configuration_id, :component_id, :position)'
                );

                foreach ($componentIds as $index => $componentId) {
                    $this->validator->assertComponentExists(
                        $componentId,
                        'Nelze uložit konfiguraci, protože jedna z komponent neexistuje.'
                    );
                    $insert->bindValue(':configuration_id', $configurationId, PDO::PARAM_INT);
                    $insert->bindValue(':component_id', $componentId, PDO::PARAM_INT);
                    $insert->bindValue(':position', (int) $index, PDO::PARAM_INT);
                    $insert->execute();
                }
            }

            $touch = $this->pdo->prepare(
                'UPDATE configurations SET updated_at = CURRENT_TIMESTAMP WHERE id = :id'
            );
            $touch->bindValue(':id', $configurationId, PDO::PARAM_INT);
            $touch->execute();

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            log_message('Failed to update configuration options: ' . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM configurations WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }
}
