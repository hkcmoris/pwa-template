<?php

declare(strict_types=1);

namespace Configuration;

use Components\Repository as ComponentsRepository;
use RuntimeException;

final class Validator
{
    private ComponentsRepository $components;

    private QueryService $queries;

    public function __construct(ComponentsRepository $components, QueryService $queries)
    {
        $this->components = $components;
        $this->queries = $queries;
    }

    public function assertConfigurationExists(int $configurationId, string $message = 'Vybraná konfigurace neexistuje.'): void
    {
        if (!$this->queries->find($configurationId)) {
            throw new ValidationException($message);
        }
    }

    public function assertComponentExists(int $componentId, string $message = 'Vybraná komponenta neexistuje.'): void
    {
        if (!$this->components->find($componentId)) {
            throw new ValidationException($message);
        }
    }

    /**
     * @param array{
     *     id: int,
     *     user_id: int,
     *     created_at: string,
     *     updated_at: string,
     *     children: list<array{id: int,configuration_id: int,component_id: int,position: int}>
     * } $configuration
     */
    public function assertComponentCanBeInserted(array $configuration, int $componentId): void
    {
        $this->assertConfigurationExists($configuration['id'], 'Komponentu nelze vložit, protože konfigurace neexistuje.');
        $this->assertComponentExists($componentId, 'Komponentu nelze vložit, protože neexistuje.');
    }
}
