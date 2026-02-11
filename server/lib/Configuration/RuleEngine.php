<?php

declare(strict_types=1);

namespace Configuration;

final class RuleEngine
{
    /**
     * @param array<string, mixed> $component
     * @param array<int, array<string, mixed>> $selectedPath
     */
    public function allowsComponent(array $component, array $selectedPath): bool
    {
        return $this->passesPrerequisites($component, $selectedPath)
            && $this->passesForbiddenRules($component, $selectedPath)
            && $this->passesDefinitionRules($component, $selectedPath);
    }

    /**
     * @param array<string, mixed> $component
     * @param array<int, array<string, mixed>> $selectedPath
     */
    private function passesPrerequisites(array $component, array $selectedPath): bool
    {
        $requiredComponentIds = $this->extractRequiredComponentIds($component['dependency_tree'] ?? null);

        if ($requiredComponentIds === []) {
            return true;
        }

        $selectedComponentIds = [];
        foreach ($selectedPath as $selection) {
            if (!isset($selection['component_id'])) {
                continue;
            }
            $componentId = (int) $selection['component_id'];
            if ($componentId > 0) {
                $selectedComponentIds[$componentId] = true;
            }
        }

        foreach ($requiredComponentIds as $requiredId) {
            if (!isset($selectedComponentIds[$requiredId])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param mixed $dependencyTree
     * @return array<int, int>
     */
    private function extractRequiredComponentIds($dependencyTree): array
    {
        if (!is_array($dependencyTree)) {
            return [];
        }

        $required = [];

        foreach ($dependencyTree as $entry) {
            if (!is_array($entry) || !isset($entry['component_id'])) {
                continue;
            }

            $componentId = (int) $entry['component_id'];

            if ($componentId <= 0) {
                continue;
            }

            if (!in_array($componentId, $required, true)) {
                $required[] = $componentId;
            }
        }

        return $required;
    }

    /**
     * @param array<string, mixed> $component
     * @param array<int, array<string, mixed>> $selectedPath
     */
    private function passesForbiddenRules(array $component, array $selectedPath): bool
    {
        return true;
    }

    /**
     * @param array<string, mixed> $component
     * @param array<int, array<string, mixed>> $selectedPath
     */
    private function passesDefinitionRules(array $component, array $selectedPath): bool
    {
        return true;
    }
}
