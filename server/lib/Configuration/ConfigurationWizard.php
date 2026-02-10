<?php

declare(strict_types=1);

namespace Configuration;

use Components\Repository as ComponentsRepository;
use RuntimeException;

/**
 * @phpstan-type PriceEntry array{
 *   amount: string,
 *   currency: string,
 *   created_at: string
 * }
 *
 * @phpstan-type ComponentRow array{
 *   id: int,
 *   definition_id: int,
 *   parent_id: int|null,
 *   alternate_title: string|null,
 *   description: string|null,
 *   images: list<string>,
 *   color: string|null,
 *   dependency_tree: array<string, mixed>|list<mixed>,
 *   position: int,
 *   created_at: string,
 *   updated_at: string,
 *   definition_title: string,
 *   image: string|null,
 *   effective_title: string,
 *   price_history: list<PriceEntry>,
 *   latest_price: PriceEntry|null
 * }
 */
final class ConfigurationWizard
{
    private int $configurationId;

    private ?int $currentComponentId;

    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $selectedPath = null;

    private WizardRepository $repository;

    private ComponentsRepository $components;

    private RuleEngine $rules;

    private function __construct(
        int $configurationId,
        ?int $currentComponentId,
        WizardRepository $repository,
        ComponentsRepository $components,
        RuleEngine $rules
    ) {
        $this->configurationId = $configurationId;
        $this->currentComponentId = $currentComponentId;
        $this->repository = $repository;
        $this->components = $components;
        $this->rules = $rules;
    }

    public static function loadOrCreateDraft(
        int $userId,
        ?int $draftId = null,
        bool $forceCreateNew = false
    ): self {
        $repository = new WizardRepository();
        $components = new ComponentsRepository($repository->getPdo());
        $rules = new RuleEngine();

        if ($forceCreateNew) {
            $draft = $repository->createDraft($userId);
        } elseif ($draftId !== null) {
            $draft = $repository->findDraftByIdForUser($draftId, $userId);
            if ($draft === null) {
                $draft = $repository->findDraftByUser($userId);
            }
            if ($draft === null) {
                $draft = $repository->createDraft($userId);
            }
        } else {
            $draft = $repository->findDraftByUser($userId);
            if ($draft === null) {
                $draft = $repository->createDraft($userId);
            }
        }

        $wizard = new self(
            (int) $draft['id'],
            isset($draft['current_component_id']) ? (int) $draft['current_component_id'] : null,
            $repository,
            $components,
            $rules
        );

        if ($wizard->currentComponentId === null) {
            $path = $wizard->getSelectedPath();
            if (!empty($path)) {
                $last = end($path);
                $componentId = isset($last['component_id']) ? (int) $last['component_id'] : null;
                if ($componentId) {
                    $wizard->currentComponentId = $componentId;
                    $wizard->repository->updateCurrentComponent($wizard->configurationId, $componentId);
                }
            } else {
                $rootComponent = $wizard->findStartRootComponent();
                if ($rootComponent !== null) {
                    $wizard->currentComponentId = (int) $rootComponent['id'];
                    $wizard->repository->updateCurrentComponent($wizard->configurationId, $wizard->currentComponentId);
                }
            }
        }

        return $wizard;
    }

    public function getConfigurationId(): int
    {
        return $this->configurationId;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSelectedPath(): array
    {
        if ($this->selectedPath === null) {
            $this->selectedPath = $this->repository->fetchSelectedPath($this->configurationId);
        }

        return $this->selectedPath;
    }

    /**
     * @return ComponentRow|null
     */
    public function getCurrentComponent(): ?array
    {
        if ($this->currentComponentId === null) {
            return null;
        }

        return $this->components->find($this->currentComponentId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAvailableOptions(): array
    {
        if ($this->currentComponentId === null) {
            $this->ensureStartRootComponent();
        }

        $children = $this->components->fetchChildren($this->currentComponentId);
        if ($children === []) {
            return [];
        }

        $path = $this->getSelectedPath();
        $available = [];

        foreach ($children as $child) {
            if ($this->rules->allowsComponent($child, $path)) {
                $available[] = $child;
            }
        }

        return $available;
    }

    public function selectComponent(int $componentId): void
    {
        if ($this->currentComponentId === null) {
            $this->ensureStartRootComponent();
        }

        $component = $this->components->find($componentId);
        if ($component === null) {
            throw new RuntimeException('Vybraná komponenta nebyla nalezena.');
        }

        $parentId = $component['parent_id'] ?? null;
        $currentId = $this->currentComponentId;

        if ($currentId === null) {
            if ($parentId !== null) {
                throw new RuntimeException('Tato komponenta není dostupná na aktuálním kroku.');
            }
        } elseif ((int) $parentId !== $currentId) {
            throw new RuntimeException('Tato komponenta není dostupná na aktuálním kroku.');
        }

        $path = $this->getSelectedPath();
        if (!$this->rules->allowsComponent($component, $path)) {
            throw new RuntimeException('Tato volba není kompatibilní se současnou konfigurací.');
        }

        $definitionId = (int) $component['definition_id'];
        $parentComponentId = $currentId !== null ? (int) $currentId : null;

        $this->repository->insertSelection(
            $this->configurationId,
            $componentId,
            $definitionId,
            $parentComponentId
        );

        $this->repository->updateCurrentComponent($this->configurationId, $componentId);
        $this->currentComponentId = $componentId;
        $nextRoot = $this->maybeAdvanceToNextRootComponent($componentId);
        if ($nextRoot !== null) {
            $this->currentComponentId = (int) $nextRoot['id'];
            $this->repository->updateCurrentComponent($this->configurationId, $this->currentComponentId);
        }
        $this->selectedPath = null;
    }

    public function goBack(): void
    {
        $last = $this->repository->deleteLastSelection($this->configurationId);
        if ($last === null) {
            $this->currentComponentId = null;
            $this->repository->updateCurrentComponent($this->configurationId, null);
            $this->selectedPath = [];
            return;
        }

        $remaining = $this->repository->fetchSelectedPath($this->configurationId);
        $newCurrent = null;

        if (!empty($remaining)) {
            $lastSelection = end($remaining);
            $newCurrent = isset($lastSelection['component_id']) ? (int) $lastSelection['component_id'] : null;
        } else {
            $rootComponent = $this->findStartRootComponent();
            $newCurrent = $rootComponent !== null ? (int) $rootComponent['id'] : null;
        }

        $this->repository->updateCurrentComponent($this->configurationId, $newCurrent);
        $this->currentComponentId = $newCurrent;
        $this->selectedPath = $remaining;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSummary(): array
    {
        $selected = $this->getSelectedPath();
        $current = $this->getCurrentComponent();
        $isComplete = $current !== null && $this->getAvailableOptions() === [];

        return [
            'configuration_id' => $this->configurationId,
            'selected_path' => $selected,
            'current_component' => $current,
            'is_complete' => $isComplete,
        ];
    }

    private function ensureStartRootComponent(): void
    {
        if ($this->currentComponentId !== null) {
            return;
        }

        $rootComponent = $this->findStartRootComponent();
        if ($rootComponent === null) {
            return;
        }

        $this->currentComponentId = (int) $rootComponent['id'];
        $this->repository->updateCurrentComponent($this->configurationId, $this->currentComponentId);
    }

    /**
     * @return ComponentRow|null
     */
    private function findStartRootComponent(): ?array
    {
        $roots = $this->components->fetchChildren(null);
        if ($roots === []) {
            return null;
        }

        foreach ($roots as $root) {
            if ((int) ($root['position'] ?? -1) === 0) {
                return $root;
            }
        }

        return $roots[0];
    }

    /**
     * @return ComponentRow|null
     */
    private function maybeAdvanceToNextRootComponent(int $componentId): ?array
    {
        $children = $this->components->fetchChildren($componentId);
        if ($children !== []) {
            return null;
        }

        $rootComponent = $this->resolveRootComponent($componentId);
        if ($rootComponent === null) {
            return null;
        }

        $roots = $this->components->fetchChildren(null);
        if ($roots === []) {
            return null;
        }

        $rootId = (int) $rootComponent['id'];
        foreach ($roots as $index => $root) {
            if ((int) $root['id'] === $rootId) {
                return $roots[$index + 1] ?? null;
            }
        }

        return null;
    }

    /**
     * @return ComponentRow|null
     */
    private function resolveRootComponent(int $componentId): ?array
    {
        $component = $this->components->find($componentId);
        if ($component === null) {
            return null;
        }

        while (!empty($component['parent_id'])) {
            $component = $this->components->find((int) $component['parent_id']);
            if ($component === null) {
                return null;
            }
        }

        return $component;
    }
}
