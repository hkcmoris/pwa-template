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
        return true;
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
