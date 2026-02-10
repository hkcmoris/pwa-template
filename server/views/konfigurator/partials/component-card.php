<?php

/** @var array<string, mixed> $option */
/** @var array<string, mixed>|null $summary */
$option = $option ?? [];
log_message(json_encode($option, JSON_PRETTY_PRINT), 'DEBUG');
$optionId = isset($option['id']) ? (int) $option['id'] : 0;
$optionTitle = (string) ($option['effective_title'] ?? $option['definition_title'] ?? '');
$optionImage = $option['image'];
$definitionTitle = (string) ($option['definition_title'] ?? '');
$baseCandidate = defined('BASE_PATH') ? (string) BASE_PATH : '';
$BASE = isset($BASE) && $BASE !== '' ? (string) $BASE : $baseCandidate;
$BASE = rtrim($BASE, '/');
$configurationId = isset($summary['configuration_id']) ? (int) $summary['configuration_id'] : 0;
?>
<div class="component-card">
    <form
        method="post"
        class="options-card-inner"
        hx-post="<?= htmlspecialchars($BASE) ?>/configurator/wizard/select"
        hx-target="#konfigurator-wizard"
        hx-swap="outerHTML"
    >
        <input type="hidden" name="component_id" value="<?= $optionId ?>">
        <input type="hidden" name="draft_id" value="<?= $configurationId ?>">
        <?php if (isset($optionImage)) : ?>
            <img
                src="<?=  $optionImage ?>" 
                width="100%"
                loading="lazy"
                decoding="async"
                onerror="this.onerror = null; this.src = '/public/assets/images/missing-image.svg';"
            >
        <?php endif; ?>
        <h2 class="options-card-title">
            <?= htmlspecialchars($optionTitle) ?>
        </h2>
        <?php if ($definitionTitle !== '' && $definitionTitle !== $optionTitle) : ?>
            <p class="options-card-subtitle">
                <?= htmlspecialchars($definitionTitle) ?>
            </p>
        <?php endif; ?>
        <button type="submit" class="options-card-action">Vybrat</button>
    </form>
</div>
