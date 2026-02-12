<?php
/** @var array<int, array<string, mixed>> $selectedPath */
/** @var array<string, mixed>|null $summary */
$selectedPath = $selectedPath ?? [];
$summary = $summary ?? [];
$BASE = isset($BASE) ? (string) $BASE : '';
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
        <?php
        $selectionId = isset($crumb['id']) ? (int) $crumb['id'] : 0;
        $crumbTitle = (string) ($crumb['effective_title'] ?? $crumb['definition_title'] ?? '');
        ?>
        <div class="breadcrumb-item">
            <form
                method="post"
                hx-post="<?= htmlspecialchars($BASE) ?>/configurator/wizard/goto-step"
                hx-target="#konfigurator-wizard"
                hx-swap="outerHTML"
            >
                <input type="hidden" name="draft_id" value="<?= $configurationId ?>">
                <input type="hidden" name="selection_id" value="<?= $selectionId ?>">
                <button type="submit" class="breadcrumb-item-inner breadcrumb-item-button">
                    <span class="breadcrumb-item-title">
                        <?= htmlspecialchars($crumbTitle) ?>
                    </span>
                </button>
            </form>
        </div>
    <?php endforeach; ?>
</div>
