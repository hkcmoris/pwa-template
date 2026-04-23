<?php
/** @var array<int, array<string, mixed>> $selectedPath */
/** @var array<string, mixed>|null $currentComponent */
/** @var array<int, array<string, mixed>>|null $availableOptions */
/** @var array<string, mixed>|null $summary */
/** @var string|null $wizardError */
$selectedPath = $selectedPath ?? [];
$currentComponent = $currentComponent ?? null;
$availableOptions = $availableOptions ?? [];
$summary = $summary ?? [];
$wizardError = $wizardError ?? null;
$baseCandidate = defined('BASE_PATH') ? (string) BASE_PATH : '';
$BASE = isset($BASE) && $BASE !== '' ? (string) $BASE : $baseCandidate;
$BASE = rtrim($BASE, '/');
$stepTitle = $currentComponent['effective_title'] ?? $currentComponent['definition_title'] ?? 'Začněte výběrem';
$hasSelections = !empty($selectedPath);
$configurationId = isset($summary['configuration_id']) ? (int) $summary['configuration_id'] : 0;
$isComplete = !empty($summary['is_complete']);
$allowsMultiSelect = !empty($currentComponent['allow_multi_select']);
$multiSelectFormId = 'wizard-multi-select-form-' . $configurationId;
$groupedSelectedPath = isset($summary['grouped_selected_path']) && is_array($summary['grouped_selected_path'])
    ? $summary['grouped_selected_path']
    : [];
?>
<div id="component-options" data-island="konfigurator-option-cards">
    <div class="component-options-header">
        <h2 class="component-options-title">
            <?= htmlspecialchars((string) $stepTitle) ?>
        </h2>
        <?php if ($hasSelections) : ?>
            <form
                method="post"
                hx-post="<?= htmlspecialchars($BASE) ?>/configurator/wizard/back"
                hx-target="#konfigurator-wizard"
                hx-swap="outerHTML"
            >
                <input type="hidden" name="draft_id" value="<?= $configurationId ?>">
                <button class="component-options-back" type="submit">Zpět</button>
            </form>
        <?php endif; ?>
    </div>
    <?php if ($wizardError) : ?>
        <div class="component-options-error" role="alert">
            <?= htmlspecialchars($wizardError) ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($availableOptions)) : ?>
        <div class="component-options-grid">
            <?php foreach ($availableOptions as $option) :
                $selectionMode = $allowsMultiSelect ? 'multiple' : 'single';
                $componentCard = __DIR__ . '/component-card.php';
                if (is_file($componentCard)) {
                    require $componentCard;
                } else {
                    echo '<p>Karta komponenty nebyla nalezena.</p>';
                }
            endforeach; ?>
        </div>
        <?php if ($allowsMultiSelect) : ?>
            <form
                id="<?= htmlspecialchars($multiSelectFormId) ?>"
                method="post"
                hx-post="<?= htmlspecialchars($BASE) ?>/configurator/wizard/select-multiple"
                hx-target="#konfigurator-wizard"
                hx-swap="outerHTML"
                class="component-options-multi-select-form"
            >
                <input type="hidden" name="draft_id" value="<?= $configurationId ?>">
                <button type="submit" class="component-options-finish">Vybrat označené možnosti</button>
            </form>
        <?php endif; ?>
    <?php else : ?>
        <div class="component-options-summary">
            <h3>Shrnutí</h3>
            <?php if (!empty($groupedSelectedPath)) : ?>
                <ul>
                    <?php foreach ($groupedSelectedPath as $group) : ?>
                        <?php
                        $groupType = isset($group['type']) ? (string) $group['type'] : '';
                        ?>
                        <?php if ($groupType === 'multi') : ?>
                            <li>
                                <?= htmlspecialchars((string) ($group['parent_title'] ?? '')) ?>
                                <?php
                                $options = isset($group['options']) && is_array($group['options']) ? $group['options'] : [];
                                ?>
                                <?php if (!empty($options)) : ?>
                                    <ul>
                                        <?php foreach ($options as $option) : ?>
                                            <li>
                                                <?= htmlspecialchars(
                                                    (string) ($option['effective_title'] ?? $option['definition_title'] ?? '')
                                                ) ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </li>
                        <?php else : ?>
                            <?php
                            $selection = isset($group['selection']) && is_array($group['selection'])
                                ? $group['selection']
                                : [];
                            ?>
                            <li>
                                <?= htmlspecialchars(
                                    (string) ($selection['effective_title'] ?? $selection['definition_title'] ?? '')
                                ) ?>
                                <?php
                                $selectionProperties = isset($selection['properties']) && is_array($selection['properties'])
                                    ? $selection['properties']
                                    : [];
                                ?>
                                <?php if (!empty($selectionProperties)) : ?>
                                    <ul class="component-options-summary-properties">
                                        <?php foreach ($selectionProperties as $property) : ?>
                                            <?php
                                            if (!is_array($property)) {
                                                continue;
                                            }
                                            $name = isset($property['name']) ? trim((string) $property['name']) : '';
                                            $value = isset($property['value']) ? trim((string) $property['value']) : '';
                                            $unit = isset($property['unit']) ? trim((string) $property['unit']) : '';
                                            $label = trim($name . ' ' . $value . ' ' . $unit);
                                            if ($label === '') {
                                                continue;
                                            }
                                            ?>
                                            <li><?= htmlspecialchars($label) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p>Zatím nebyly vybrány žádné položky.</p>
            <?php endif; ?>
            <?php if ($isComplete && $configurationId > 0) : ?>
                <button
                    type="button"
                    class="component-options-finish"
                    data-wizard-finish
                    data-draft-id="<?= $configurationId ?>"
                >
                    Dokončit konfiguraci
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <div class="component-options-finish-modal hidden" aria-hidden="true"></div>
</div>
