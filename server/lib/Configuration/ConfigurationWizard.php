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
 *   allow_multi_select: bool,
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

    private ?string $configurationTitle;

    private ?int $currentComponentId;

    private ?int $draftNumber;

    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $selectedPath = null;

    private WizardRepository $repository;

    private ComponentsRepository $components;

    private RuleEngine $rules;

    private function __construct(
        int $configurationId,
        ?string $configurationTitle,
        ?int $currentComponentId,
        ?int $draftNumber,
        WizardRepository $repository,
        ComponentsRepository $components,
        RuleEngine $rules
    ) {
        $this->configurationId = $configurationId;
        $this->configurationTitle = $configurationTitle;
        $this->currentComponentId = $currentComponentId;
        $this->draftNumber = $draftNumber;
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
            isset($draft['title']) && $draft['title'] !== '' ? (string) $draft['title'] : null,
            isset($draft['current_component_id']) ? (int) $draft['current_component_id'] : null,
            isset($draft['draft_number']) ? (int) $draft['draft_number'] : null,
            $repository,
            $components,
            $rules
        );

        if ($wizard->currentComponentId === null) {
            $path = $wizard->getSelectedPath();
            if (empty($path)) {
                $rootComponent = $wizard->findStartRootComponent($path);
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
     * @return array<int, array<string, mixed>>
     */
    public function getBreadcrumbPath(): array
    {
        $selectedPath = $this->getSelectedPath();
        if ($selectedPath === []) {
            return [];
        }

        $breadcrumbPath = [];
        $pathPrefix = [];
        $multiSelectParentsInBreadcrumb = [];

        foreach ($selectedPath as $selection) {
            if ($this->selectionBelongsToMultiSelectStep($selection)) {
                $parentComponentId = isset($selection['parent_component_id'])
                    ? (int) $selection['parent_component_id']
                    : 0;
                if (
                    $parentComponentId > 0
                    && !isset($multiSelectParentsInBreadcrumb[$parentComponentId])
                ) {
                    $parentComponent = $this->components->find($parentComponentId);
                    if ($parentComponent !== null) {
                        $breadcrumbPath[] = [
                            'id' => $selection['id'] ?? null,
                            'effective_title' => $parentComponent['effective_title'],
                            'definition_title' => $parentComponent['definition_title'],
                        ];
                        $multiSelectParentsInBreadcrumb[$parentComponentId] = true;
                    }
                }
                $pathPrefix[] = $selection;
                continue;
            }

            $availableOptions = $this->resolveAvailableOptionsForSelectionParent($selection, $pathPrefix);
            if (count($availableOptions) !== 1) {
                $breadcrumbPath[] = $selection;
            }

            $pathPrefix[] = $selection;
        }

        return $breadcrumbPath;
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
            if ($this->currentComponentId === null) {
                return [];
            }
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

    /**
     * @param array<int, int> $componentIds
     */
    public function selectMultipleComponents(array $componentIds): void
    {
        if ($this->currentComponentId === null) {
            $this->ensureStartRootComponent();
        }

        if ($this->currentComponentId === null) {
            throw new RuntimeException('Aktuální krok nebyl nalezen.');
        }

        $current = $this->getCurrentComponent();
        if ($current === null || empty($current['allow_multi_select'])) {
            throw new RuntimeException('Aktuální krok nepodporuje výběr více možností.');
        }

        $availableOptions = $this->getAvailableOptions();
        $availableMap = [];
        foreach ($availableOptions as $option) {
            $optionId = isset($option['id']) ? (int) $option['id'] : 0;
            if ($optionId > 0) {
                $availableMap[$optionId] = $option;
            }
        }

        $normalisedIds = [];
        foreach ($componentIds as $componentId) {
            $id = (int) $componentId;
            if ($id > 0 && !in_array($id, $normalisedIds, true)) {
                $normalisedIds[] = $id;
            }
        }

        foreach ($normalisedIds as $optionId) {
            $option = $availableMap[$optionId] ?? null;
            if ($option === null) {
                throw new RuntimeException('Některé vybrané možnosti nejsou na aktuálním kroku dostupné.');
            }

            $definitionId = isset($option['definition_id']) ? (int) $option['definition_id'] : 0;
            if ($definitionId <= 0) {
                throw new RuntimeException('Některé vybrané možnosti nejsou validní.');
            }

            $this->repository->insertSelection(
                $this->configurationId,
                $optionId,
                $definitionId,
                $this->currentComponentId
            );
        }

        $updatedPath = $this->getSelectedPath();
        if ($normalisedIds !== []) {
            $this->selectedPath = null;
            $updatedPath = $this->getSelectedPath();
        }

        $nextRoot = $this->findNextEligibleRootAfter($this->currentComponentId, $updatedPath);
        if ($nextRoot !== null) {
            $newCurrent = (int) $nextRoot['id'];
        } elseif ($normalisedIds === []) {
            $fallbackNextRoot = $this->findNextRootAfter($this->currentComponentId);
            $newCurrent = $fallbackNextRoot !== null
                ? (int) $fallbackNextRoot['id']
                : null;
        } else {
            $newCurrent = (int) $normalisedIds[count($normalisedIds) - 1];
        }
        $this->repository->updateCurrentComponent($this->configurationId, $newCurrent);
        $this->currentComponentId = $newCurrent;
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

        if (array_key_exists('parent_component_id', $last) && $last['parent_component_id'] !== null) {
            $newCurrent = (int) $last['parent_component_id'];
        } elseif (!empty($remaining)) {
            $lastSelection = end($remaining);
            $newCurrent = isset($lastSelection['component_id']) ? (int) $lastSelection['component_id'] : null;
        } else {
            $rootComponent = $this->findStartRootComponent($remaining);
            $newCurrent = $rootComponent !== null ? (int) $rootComponent['id'] : null;
        }

        $this->repository->updateCurrentComponent($this->configurationId, $newCurrent);
        $this->currentComponentId = $newCurrent;
        $this->selectedPath = $remaining;
    }

    public function goToStep(int $selectionId): void
    {
        $path = $this->getSelectedPath();
        if ($path === []) {
            $this->currentComponentId = null;
            $this->repository->updateCurrentComponent($this->configurationId, null);
            return;
        }

        $targetIndex = null;
        foreach ($path as $index => $selection) {
            if ((int) ($selection['id'] ?? 0) === $selectionId) {
                $targetIndex = $index;
                break;
            }
        }

        if ($targetIndex === null) {
            throw new RuntimeException('Požadovaný krok nebyl nalezen.');
        }

        $targetSelection = $path[$targetIndex];
        $remaining = array_slice($path, 0, $targetIndex);

        if ($remaining === []) {
            $this->repository->deleteAllSelections($this->configurationId);
        } else {
            $lastRemainingSelection = end($remaining);
            $lastRemainingSelectionId = (int) ($lastRemainingSelection['id'] ?? 0);
            if ($lastRemainingSelectionId <= 0) {
                throw new RuntimeException('Požadovaný krok nebyl nalezen.');
            }

            $this->repository->deleteSelectionsAfter($this->configurationId, $lastRemainingSelectionId);
        }

        $newCurrent = isset($targetSelection['parent_component_id'])
            ? (int) $targetSelection['parent_component_id']
            : null;

        if ($newCurrent <= 0) {
            $rootComponent = $this->findStartRootComponent($remaining);
            $newCurrent = $rootComponent !== null ? (int) $rootComponent['id'] : null;
        }

        $this->repository->updateCurrentComponent($this->configurationId, $newCurrent);
        $this->currentComponentId = $newCurrent;
        $this->selectedPath = $remaining;
    }

    public function autoSelectSingleOptions(): void
    {
        $safetyCounter = 0;

        while ($safetyCounter < 100) {
            $availableOptions = $this->getAvailableOptions();
            if (count($availableOptions) !== 1) {
                return;
            }

            $onlyOption = $availableOptions[0];
            $optionId = (int) ($onlyOption['id'] ?? 0);
            if ($optionId <= 0) {
                return;
            }

            $this->selectComponent($optionId);
            $safetyCounter++;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSummary(): array
    {
        $selected = $this->getSelectedPath();
        $current = $this->getCurrentComponent();
        $availableOptions = $this->getAvailableOptions();
        $isComplete = $current !== null && $availableOptions === [];
        if (!$isComplete && $current === null && $selected !== []) {
            $isComplete = true;
        }

        return [
            'configuration_id' => $this->configurationId,
            'configuration_title' => $this->configurationTitle,
            'configuration_draft_number' => $this->draftNumber,
            'selected_path' => $selected,
            'grouped_selected_path' => $this->buildGroupedSelectedPath($selected),
            'current_component' => $current,
            'is_complete' => $isComplete,
        ];
    }

    public function currentComponentAllowsMultiSelect(): bool
    {
        $current = $this->getCurrentComponent();
        if ($current === null) {
            return false;
        }

        return !empty($current['allow_multi_select']);
    }

    private function ensureStartRootComponent(): void
    {
        if ($this->currentComponentId !== null) {
            return;
        }

        $path = $this->getSelectedPath();
        if (!empty($path)) {
            return;
        }

        $rootComponent = $this->findStartRootComponent($path);
        if ($rootComponent === null) {
            return;
        }

        $this->currentComponentId = (int) $rootComponent['id'];
        $this->repository->updateCurrentComponent($this->configurationId, $this->currentComponentId);
    }

    /**
     * @param array<int, array<string, mixed>> $selectedPath
     * @return ComponentRow|null
     */
    private function findStartRootComponent(array $selectedPath = []): ?array
    {
        $roots = $this->components->fetchChildren(null);
        if ($roots === []) {
            return null;
        }

        $eligibleRoots = [];
        foreach ($roots as $root) {
            if ($this->rules->allowsComponent($root, $selectedPath)) {
                $eligibleRoots[] = $root;
            }
        }

        if ($eligibleRoots === []) {
            return null;
        }

        foreach ($eligibleRoots as $root) {
            if ((int) ($root['position'] ?? -1) === 0) {
                return $root;
            }
        }

        return $eligibleRoots[0];
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

        return $this->findNextEligibleRootAfter($componentId, $this->getSelectedPath());
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

    /**
     * @param array<int, array<string, mixed>> $path
     * @return ComponentRow|null
     */
    private function findNextEligibleRootAfter(int $componentId, array $path): ?array
    {
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
                for ($nextIndex = $index + 1; $nextIndex < count($roots); $nextIndex++) {
                    $nextRoot = $roots[$nextIndex];
                    if ($this->rules->allowsComponent($nextRoot, $path)) {
                        return $nextRoot;
                    }
                }

                return null;
            }
        }

        return null;
    }

    /**
     * @return ComponentRow|null
     */
    private function findNextRootAfter(int $componentId): ?array
    {
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
            if ((int) $root['id'] !== $rootId) {
                continue;
            }

            $nextIndex = $index + 1;
            if ($nextIndex >= count($roots)) {
                return null;
            }

            return $roots[$nextIndex];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $selection
     * @param array<int, array<string, mixed>> $pathPrefix
     * @return array<int, array<string, mixed>>
     */
    private function resolveAvailableOptionsForSelectionParent(array $selection, array $pathPrefix): array
    {
        $parentComponentId = isset($selection['parent_component_id'])
            ? (int) $selection['parent_component_id']
            : null;
        if ($parentComponentId !== null && $parentComponentId <= 0) {
            $parentComponentId = null;
        }

        $children = $this->components->fetchChildren($parentComponentId);
        if ($children === []) {
            return [];
        }

        $available = [];
        foreach ($children as $child) {
            if ($this->rules->allowsComponent($child, $pathPrefix)) {
                $available[] = $child;
            }
        }

        return $available;
    }

    /**
     * @param array<string, mixed> $selection
     */
    private function selectionBelongsToMultiSelectStep(array $selection): bool
    {
        $parentComponentId = isset($selection['parent_component_id'])
            ? (int) $selection['parent_component_id']
            : 0;
        if ($parentComponentId <= 0) {
            return false;
        }

        $parentComponent = $this->components->find($parentComponentId);
        if ($parentComponent === null) {
            return false;
        }

        return !empty($parentComponent['allow_multi_select']);
    }

    /**
     * @param array<int, array<string, mixed>> $selectedPath
     * @return array<int, array<string, mixed>>
     */
    private function buildGroupedSelectedPath(array $selectedPath): array
    {
        $grouped = [];
        $multiSelectGroupIndexByParentId = [];

        foreach ($selectedPath as $selection) {
            if (!$this->selectionBelongsToMultiSelectStep($selection)) {
                $grouped[] = [
                    'type' => 'single',
                    'selection' => $selection,
                ];
                continue;
            }

            $parentComponentId = isset($selection['parent_component_id'])
                ? (int) $selection['parent_component_id']
                : 0;
            if ($parentComponentId <= 0) {
                $grouped[] = [
                    'type' => 'single',
                    'selection' => $selection,
                ];
                continue;
            }

            if (!isset($multiSelectGroupIndexByParentId[$parentComponentId])) {
                $parentComponent = $this->components->find($parentComponentId);
                $grouped[] = [
                    'type' => 'multi',
                    'parent_title' => $parentComponent['effective_title']
                        ?? $parentComponent['definition_title']
                        ?? '',
                    'options' => [],
                ];
                $multiSelectGroupIndexByParentId[$parentComponentId] = count($grouped) - 1;
            }

            $groupIndex = $multiSelectGroupIndexByParentId[$parentComponentId];
            $grouped[$groupIndex]['options'][] = $selection;
        }

        return $grouped;
    }
}
