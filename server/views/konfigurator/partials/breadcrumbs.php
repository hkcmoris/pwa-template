<?php
/** @var array<int, array<string, mixed>> $selectedPath */
/** @var array<string, mixed>|null $summary */
$selectedPath = $selectedPath ?? [];
$summary = $summary ?? [];
$configurationId = isset($summary['configuration_id']) ? (int) $summary['configuration_id'] : 0;
$hasSelections = !empty($selectedPath);
?>
<div id="breadcrumbs">
    <div class="breadcrumb-item">
        <span class="breadcrumb-item-inner">
            <span class="breadcrumb-item-title">
                <?= htmlspecialchars($hasSelections ? 'Start' : 'Výběr') ?>
                <?php if ($configurationId > 0) : ?>
                    <small>(Draft #<?= htmlspecialchars((string) $configurationId) ?>)</small>
                <?php endif; ?>
            </span>
        </span>
    </div>
    <?php foreach ($selectedPath as $crumb) : ?>
        <div class="breadcrumb-item">
            <span class="breadcrumb-item-inner">
                <span class="breadcrumb-item-title">
                    <?= htmlspecialchars((string) ($crumb['effective_title'] ?? $crumb['definition_title'] ?? '')) ?>
                </span>
            </span>
        </div>
    <?php endforeach; ?>
</div>
