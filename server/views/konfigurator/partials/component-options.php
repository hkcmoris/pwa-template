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
?>
<div id="component-options">
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
                $componentCard = __DIR__ . '/component-card.php';
                if (is_file($componentCard)) {
                    require $componentCard;
                } else {
                    echo '<p>Karta komponenty nebyla nalezena.</p>';
                }
            endforeach; ?>
        </div>
    <?php else : ?>
        <div class="component-options-summary">
            <h3>Shrnutí</h3>
            <?php if (!empty($summary['selected_path'])) : ?>
                <ul>
                    <?php foreach ($summary['selected_path'] as $selection) : ?>
                        <li>
                            <?= htmlspecialchars(
                                (string) ($selection['effective_title'] ?? $selection['definition_title'] ?? '')
                            ) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p>Zatím nebyly vybrány žádné položky.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
