<?php

/** @var array<string, mixed> $option */
$option = $option ?? [];
$optionId = isset($option['id']) ? (int) $option['id'] : 0;
$optionTitle = (string) ($option['effective_title'] ?? $option['definition_title'] ?? '');
$definitionTitle = (string) ($option['definition_title'] ?? '');
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
