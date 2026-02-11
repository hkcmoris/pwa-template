<?php

declare(strict_types=1);

namespace Configuration;

use Components\Formatter as ComponentsFormatter;
use PDO;
use RuntimeException;
use Throwable;

use function get_db_connection;
use function log_message;

final class WizardRepository
{
    private PDO $pdo;

    private ComponentsFormatter $componentsFormatter;

    public function __construct(?PDO $pdo = null, ?ComponentsFormatter $componentsFormatter = null)
    {
        $this->pdo = $pdo ?? get_db_connection();
        $this->componentsFormatter = $componentsFormatter ?? new ComponentsFormatter();
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findDraftByUser(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, title, status, current_component_id, created_at, updated_at,
                    (
                        SELECT COUNT(*)
                        FROM configurations sibling
                        WHERE sibling.user_id = configurations.user_id
                          AND sibling.status = configurations.status
                          AND sibling.id <= configurations.id
                    ) AS draft_number
             FROM configurations
             WHERE user_id = :user_id AND status = :status
             ORDER BY updated_at DESC, id DESC
             LIMIT 1'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':status', 'draft');
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findDraftByIdForUser(int $configurationId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, title, status, current_component_id, created_at, updated_at,
                    (
                        SELECT COUNT(*)
                        FROM configurations sibling
                        WHERE sibling.user_id = configurations.user_id
                          AND sibling.status = configurations.status
                          AND sibling.id <= configurations.id
                    ) AS draft_number
             FROM configurations
             WHERE id = :id AND user_id = :user_id AND status = :status
             LIMIT 1'
        );
        $stmt->bindValue(':id', $configurationId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':status', 'draft');
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findDraftsByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, title, status, current_component_id, created_at, updated_at,
                    (
                        SELECT COUNT(*)
                        FROM configurations sibling
                        WHERE sibling.user_id = configurations.user_id
                          AND sibling.status = configurations.status
                          AND sibling.id <= configurations.id
                    ) AS draft_number
             FROM configurations
             WHERE user_id = :user_id AND status = :status
             ORDER BY updated_at DESC, id DESC'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':status', 'draft');
        $stmt->execute();

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function createDraft(int $userId): array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO configurations (user_id, title, status, current_component_id)
                 VALUES (:user_id, :title, :status, NULL)'
            );
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':title', null, PDO::PARAM_NULL);
            $stmt->bindValue(':status', 'draft');
            $stmt->execute();
            $id = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            log_message('Failed to create draft configuration: ' . $e->getMessage(), 'ERROR');
            throw $e;
        }

        $draft = $this->findConfiguration($id);
        if ($draft === null) {
            throw new RuntimeException('Draft configuration could not be loaded.');
        }

        return $draft;
    }

    public function renameDraft(int $configurationId, int $userId, string $title): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE configurations
             SET title = :title, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND user_id = :user_id AND status = :status'
        );
        $stmt->bindValue(':id', $configurationId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':title', $title);
        $stmt->bindValue(':status', 'draft');
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function deleteDraft(int $configurationId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM configurations
             WHERE id = :id AND user_id = :user_id AND status = :status'
        );
        $stmt->bindValue(':id', $configurationId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':status', 'draft');
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findConfiguration(int $configurationId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, title, status, current_component_id, created_at, updated_at,
                    (
                        SELECT COUNT(*)
                        FROM configurations sibling
                        WHERE sibling.user_id = configurations.user_id
                          AND sibling.status = configurations.status
                          AND sibling.id <= configurations.id
                    ) AS draft_number
             FROM configurations
             WHERE id = :id'
        );
        $stmt->bindValue(':id', $configurationId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function updateCurrentComponent(int $configurationId, ?int $componentId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE configurations
             SET current_component_id = :component_id, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->bindValue(':id', $configurationId, PDO::PARAM_INT);
        if ($componentId === null) {
            $stmt->bindValue(':component_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':component_id', $componentId, PDO::PARAM_INT);
        }
        $stmt->execute();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchSelectedPath(int $configurationId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                cs.id,
                cs.component_id,
                cs.definition_id,
                cs.parent_component_id,
                cs.created_at,
                c.alternate_title,
                c.description,
                c.images,
                c.color,
                c.dependency_tree,
                c.position,
                c.parent_id,
                d.title AS definition_title
             FROM configuration_selections cs
             INNER JOIN components c ON c.id = cs.component_id
             INNER JOIN definitions d ON d.id = c.definition_id
             WHERE cs.configuration_id = :configuration_id
             ORDER BY cs.id ASC'
        );
        $stmt->bindValue(':configuration_id', $configurationId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $normalised = [];

        foreach ($rows as $row) {
            $row['dependency_tree'] = $this->componentsFormatter->normaliseDependencyTree(
                $row['dependency_tree'] ?? null
            );
            $images = $this->componentsFormatter->normaliseImages($row['images'] ?? null);
            $row['images'] = $images;
            $row['image'] = $images[0] ?? null;
            $row['effective_title'] = $this->componentsFormatter->effectiveTitle($row);
            $normalised[] = $row;
        }

        return $normalised;
    }

    public function insertSelection(
        int $configurationId,
        int $componentId,
        int $definitionId,
        ?int $parentComponentId
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO configuration_selections
                (configuration_id, component_id, definition_id, parent_component_id)
             VALUES (:configuration_id, :component_id, :definition_id, :parent_component_id)'
        );
        $stmt->bindValue(':configuration_id', $configurationId, PDO::PARAM_INT);
        $stmt->bindValue(':component_id', $componentId, PDO::PARAM_INT);
        $stmt->bindValue(':definition_id', $definitionId, PDO::PARAM_INT);
        if ($parentComponentId === null) {
            $stmt->bindValue(':parent_component_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':parent_component_id', $parentComponentId, PDO::PARAM_INT);
        }
        $stmt->execute();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function deleteLastSelection(int $configurationId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, component_id, parent_component_id
             FROM configuration_selections
             WHERE configuration_id = :configuration_id
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->bindValue(':configuration_id', $configurationId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $delete = $this->pdo->prepare(
            'DELETE FROM configuration_selections WHERE id = :id'
        );
        $delete->bindValue(':id', (int) $row['id'], PDO::PARAM_INT);
        $delete->execute();

        return $row;
    }
}
