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
        $groups = $prerequisites['groups'];

        if ($groups === []) {
            return true;
        }

        $selectedComponentIds = $this->extractSelectedComponentIds($selectedPath);

        $groupResults = [];

        foreach ($groups as $group) {
            $groupRequiredIds = $group['component_ids'];
            $groupOperator = $group['operator'] === 'or' ? 'or' : 'and';

            if ($groupRequiredIds === []) {
                $groupResults[] = true;
                continue;
            }

            if ($groupOperator === 'or') {
                $passesGroup = false;
                foreach ($groupRequiredIds as $requiredId) {
                    if (isset($selectedComponentIds[(int) $requiredId])) {
                        $passesGroup = true;
                        break;
                    }
                }
                $groupResults[] = $passesGroup;
                continue;
            }

            $passesGroup = true;
            foreach ($groupRequiredIds as $requiredId) {
                if (!isset($selectedComponentIds[(int) $requiredId])) {
                    $passesGroup = false;
                    break;
                }
            }
            $groupResults[] = $passesGroup;
        }

        if ($prerequisites['operator'] === 'or') {
            foreach ($groupResults as $result) {
                if ($result) {
                    return true;
                }
            }

            return false;
        }

        foreach ($groupResults as $result) {
            if (!$result) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param mixed $dependencyTree
     * @return array{
     *  operator: string,
     *  groups: array<int, array{operator: string, component_ids: array<int, int>}>,
     *  forbidden_component_ids: array<int, int>
     * }
     */
    private function extractPrerequisites($dependencyTree): array
    {
        if (is_string($dependencyTree)) {
            $decoded = json_decode($dependencyTree, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $dependencyTree = $decoded;
            }
        }

        if (!is_array($dependencyTree)) {
            return [
                'operator' => 'and',
                'groups' => [],
                'forbidden_component_ids' => [],
            ];
        }

        $operatorRaw = isset($dependencyTree['operator']) ? $dependencyTree['operator'] : '';
        $operator = $operatorRaw === 'or' ? 'or' : 'and';

        $rulesRaw = $dependencyTree;
        if (isset($dependencyTree['rules'])) {
            $rulesRaw = $dependencyTree['rules'];
        }

        if (is_string($rulesRaw)) {
            $decodedRules = json_decode($rulesRaw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedRules)) {
                $rulesRaw = $decodedRules;
            }
        }

        $normaliseIds = static function ($entries): array {
            if (!is_array($entries)) {
                return [];
            }

            $required = [];
            foreach ($entries as $entry) {
                if (!is_array($entry) || !isset($entry['component_id'])) {
                    continue;
                }

                $componentId = (int) $entry['component_id'];
                if ($componentId <= 0 || in_array($componentId, $required, true)) {
                    continue;
                }

                $required[] = $componentId;
            }

            return $required;
        };

        $groups = [];

        if (is_array($rulesRaw)) {
            $hasNestedGroups = false;
            foreach ($rulesRaw as $entry) {
                if (is_array($entry) && isset($entry['rules']) && is_array($entry['rules'])) {
                    $hasNestedGroups = true;
                    break;
                }
            }

            if ($hasNestedGroups) {
                foreach ($rulesRaw as $entry) {
                    if (!is_array($entry) || !isset($entry['rules']) || !is_array($entry['rules'])) {
                        continue;
                    }

                    $componentIds = $normaliseIds($entry['rules']);
                    if ($componentIds === []) {
                        continue;
                    }

                    $groupOperatorRaw = isset($entry['operator']) ? $entry['operator'] : '';
                    $groupOperator = $groupOperatorRaw === 'or' ? 'or' : 'and';
                    $groups[] = [
                        'operator' => $groupOperator,
                        'component_ids' => $componentIds,
                    ];
                }
            } else {
                $componentIds = $normaliseIds($rulesRaw);
                if ($componentIds !== []) {
                    $groups[] = [
                        'operator' => $operator,
                        'component_ids' => $componentIds,
                    ];
                }
            }
        }

        $forbidden = [];
        if (isset($dependencyTree['forbidden_component_ids']) && is_array($dependencyTree['forbidden_component_ids'])) {
            foreach ($dependencyTree['forbidden_component_ids'] as $entry) {
                $componentId = (int) $entry;
                if ($componentId <= 0 || in_array($componentId, $forbidden, true)) {
                    continue;
                }
                $forbidden[] = $componentId;
            }
        }

        return [
            'operator' => $operator,
            'groups' => $groups,
            'forbidden_component_ids' => $forbidden,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $selectedPath
     * @return array<int, bool>
     */
    private function extractSelectedComponentIds(array $selectedPath): array
    {
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

        return $selectedComponentIds;
    }

    /**
     * @param array<string, mixed> $component
     * @param array<int, array<string, mixed>> $selectedPath
     */
    private function passesForbiddenRules(array $component, array $selectedPath): bool
    {
        $prerequisites = $this->extractPrerequisites($component['dependency_tree'] ?? null);
        $forbidden = $prerequisites['forbidden_component_ids'];

        if ($forbidden === []) {
            return true;
        }

        $selectedComponentIds = $this->extractSelectedComponentIds($selectedPath);

        foreach ($forbidden as $forbiddenId) {
            if (isset($selectedComponentIds[$forbiddenId])) {
                return false;
            }
        }

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
