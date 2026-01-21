<?php
/** @var array<int, array<string, mixed>> $selectedPath */
$selectedPath = $selectedPath ?? [];
$hasSelections = !empty($selectedPath);
?>
<div id="breadcrumbs">
    <div class="breadcrumb-item">
        <span class="breadcrumb-item-inner">
            <span class="breadcrumb-item-title">
                <?= htmlspecialchars($hasSelections ? 'Start' : 'Výběr') ?>
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
