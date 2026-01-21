<?php

declare(strict_types=1);

namespace Configuration;

use Components\Repository as ComponentsRepository;
use RuntimeException;

final class ConfigurationWizard
{
    private int $configurationId;

    private int $userId;

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
        int $userId,
        ?int $currentComponentId,
        WizardRepository $repository,
        ComponentsRepository $components,
        RuleEngine $rules
    ) {
        $this->configurationId = $configurationId;
        $this->userId = $userId;
        $this->currentComponentId = $currentComponentId;
        $this->repository = $repository;
        $this->components = $components;
        $this->rules = $rules;
    }

    public static function loadOrCreateDraft(int $userId): self
    {
        $repository = new WizardRepository();
        $components = new ComponentsRepository($repository->getPdo());
        $rules = new RuleEngine();

        $draft = $repository->findDraftByUser($userId);
        if ($draft === null) {
            $draft = $repository->createDraft($userId);
        }

        $wizard = new self(
            (int) $draft['id'],
            (int) $draft['user_id'],
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
     * @return array<string, mixed>|null
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

        $definitionId = isset($component['definition_id']) ? (int) $component['definition_id'] : 0;
        $parentComponentId = $currentId !== null ? (int) $currentId : null;

        $this->repository->insertSelection(
            $this->configurationId,
            $componentId,
            $definitionId,
            $parentComponentId
        );

        $this->repository->updateCurrentComponent($this->configurationId, $componentId);
        $this->currentComponentId = $componentId;
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
}
