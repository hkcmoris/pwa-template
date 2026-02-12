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
        $prerequisites = $this->extractPrerequisites($component['dependency_tree'] ?? null);
        $requiredComponentIds = $prerequisites['component_ids'];

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

        if ($prerequisites['operator'] === 'or') {
            foreach ($requiredComponentIds as $requiredId) {
                if (isset($selectedComponentIds[$requiredId])) {
                    return true;
                }
            }

            return false;
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
     * @return array{operator: string, component_ids: array<int, int>}
     */
    private function extractPrerequisites($dependencyTree): array
    {
        if (!is_array($dependencyTree)) {
            return [
                'operator' => 'and',
                'component_ids' => [],
            ];
        }

        $operator = 'and';
        $rules = $dependencyTree;

        if (isset($dependencyTree['rules']) && is_array($dependencyTree['rules'])) {
            $rules = $dependencyTree['rules'];
            $operatorRaw = isset($dependencyTree['operator']) ? $dependencyTree['operator'] : '';
            $operator = $operatorRaw === 'or' ? 'or' : 'and';
        }

        $required = [];

        foreach ($rules as $entry) {
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

        return [
            'operator' => $operator,
            'component_ids' => $required,
        ];
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
