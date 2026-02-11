<?php
/** @var array<int, array<string, mixed>> $selectedPath */
/** @var array<string, mixed>|null $summary */
$selectedPath = $selectedPath ?? [];
$summary = $summary ?? [];
$configurationId = isset($summary['configuration_id']) ? (int) $summary['configuration_id'] : 0;
$configurationDraftNumber = isset($summary['configuration_draft_number'])
    ? (int) $summary['configuration_draft_number']
    : 0;
$configurationTitle = isset($summary['configuration_title']) && is_string($summary['configuration_title'])
    ? trim($summary['configuration_title'])
    : '';
if ($configurationTitle == '') {
    $fallbackNumber = $configurationDraftNumber > 0 ? $configurationDraftNumber : $configurationId;
    $configurationTitle = 'Návrh #' . $fallbackNumber;
}
?>
<div id="breadcrumbs">
    <div class="breadcrumb-item">
        <span class="breadcrumb-item-inner">
            <span class="breadcrumb-item-title">
                <?php if ($configurationId > 0) : ?>
                    <small><?= htmlspecialchars($configurationTitle) ?></small>
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
