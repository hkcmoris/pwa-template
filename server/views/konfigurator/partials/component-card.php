<?php

/** @var array<string, mixed> $option */
/** @var array<string, mixed>|null $summary */
$option = $option ?? [];
$optionId = isset($option['id']) ? (int) $option['id'] : 0;
$optionTitle = (string) ($option['effective_title'] ?? $option['definition_title'] ?? '');
$optionImage = $option['image'];
$optionImages = isset($option['images']) && is_array($option['images'])
    ? array_values(array_filter($option['images'], static function ($image): bool {
        return is_string($image) && trim($image) !== '';
    }))
    : [];
$optionColor = $option['color'];
$definitionTitle = (string) ($option['definition_title'] ?? '');
$optionDescription = trim((string) ($option['description'] ?? ''));
$latestPrice = isset($option['latest_price']) && is_array($option['latest_price'])
    ? $option['latest_price']
    : null;
$priceAmount = $latestPrice !== null ? trim((string) ($latestPrice['amount'] ?? '')) : '';
$priceCurrency = $latestPrice !== null ? strtoupper(trim((string) ($latestPrice['currency'] ?? 'CZK'))) : 'CZK';
$priceLabel = '';
if ($priceAmount !== '' && is_numeric($priceAmount)) {
    $normalised = number_format((float) $priceAmount, 2, ',', ' ');
    $normalised = preg_replace('/,00$/', '', $normalised);
    $priceLabel = trim((string) $normalised . ' ' . $priceCurrency);
}
$optionProperties = isset($option['properties']) && is_array($option['properties'])
    ? $option['properties']
    : [];
$baseCandidate = defined('BASE_PATH') ? (string) BASE_PATH : '';
$BASE = isset($BASE) && $BASE !== '' ? (string) $BASE : $baseCandidate;
$BASE = rtrim($BASE, '/');
$configurationId = isset($summary['configuration_id']) ? (int) $summary['configuration_id'] : 0;
if ($optionImage !== null && !in_array($optionImage, $optionImages, true)) {
    array_unshift($optionImages, (string) $optionImage);
}
$hasMultipleImages = count($optionImages) > 1;
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
        <?php if (!empty($optionImages)) : ?>
            <div class="options-card-media">
                <?php foreach ($optionImages as $index => $imageUrl) : ?>
                    <img
                        class="options-card-image<?= $index === 0 ? ' is-active' : '' ?>"
                        src="<?= htmlspecialchars((string) $imageUrl) ?>"
                        alt="<?= htmlspecialchars($optionTitle) ?>"
                        width="100%"
                        loading="lazy"
                        decoding="async"
                        data-fallback-src="<?= htmlspecialchars($BASE) ?>/public/assets/images/missing-image.svg"
                        data-option-image
                        <?= $index === 0 ? '' : 'hidden' ?>
                    >
                <?php endforeach; ?>
                <?php if ($hasMultipleImages) : ?>
                    <button
                        class="options-card-image-arrow options-card-image-arrow--left"
                        type="button"
                        aria-label="Předchozí obrázek"
                        data-option-image-nav="prev"
                    >
                        <svg
                            class="options-card-image-arrow-icon"
                            viewBox="0 0 16 16"
                            aria-hidden="true"
                            focusable="false"
                        >
                            <path d="M10.25 3.25L5.5 8l4.75 4.75" />
                        </svg>
                    </button>
                    <button
                        class="options-card-image-arrow options-card-image-arrow--right"
                        type="button"
                        aria-label="Další obrázek"
                        data-option-image-nav="next"
                    >
                        <svg
                            class="options-card-image-arrow-icon"
                            viewBox="0 0 16 16"
                            aria-hidden="true"
                            focusable="false"
                        >
                            <path d="M5.75 3.25L10.5 8l-4.75 4.75" />
                        </svg>
                    </button>
                <?php endif; ?>
                <button
                    class="options-card-image-open"
                    type="button"
                    aria-label="Zobrazit obrázek ve větší velikosti"
                    data-option-image-open
                ></button>
            </div>
        <?php elseif (isset($optionColor)) : ?>
            <div class="options-card-color" style="--color:<?= htmlspecialchars((string) $optionColor) ?>"></div>
        <?php endif; ?>
        <h2 class="options-card-title">
            <?= htmlspecialchars($optionTitle) ?>
        </h2>
        <?php if ($definitionTitle !== '' && $definitionTitle !== $optionTitle) : ?>
            <p class="options-card-subtitle">
                <?= htmlspecialchars($definitionTitle) ?>
            </p>
        <?php endif; ?>
        <?php if ($optionDescription !== '') : ?>
            <p class="options-card-description">
                <?= htmlspecialchars($optionDescription) ?>
            </p>
        <?php endif; ?>
        <?php if ($priceLabel !== '') : ?>
            <p class="options-card-price">
                <?= htmlspecialchars($priceLabel) ?>
            </p>
        <?php endif; ?>
        <?php if (!empty($optionProperties)) : ?>
            <table class="options-card-properties">
                <?php foreach ($optionProperties as $property) : ?>
                <tr>
                    <?php
                    if (!is_array($property)) {
                        continue;
                    }
                    $name = isset($property['name']) ? trim((string) $property['name']) : '';
                    $value = isset($property['value']) ? trim((string) $property['value']) : '';
                    $unit = isset($property['unit']) ? trim((string) $property['unit']) : '';
                    ?>
                    <td><?= htmlspecialchars($name) ?></td>
                    <td><?= htmlspecialchars($value) ?></td>
                    <td><?= htmlspecialchars($unit) ?></td>
                </tr>
                <?php endforeach; ?>
                </table>
        <?php endif; ?>
        <button type="submit" class="options-card-action">Vybrat</button>
    </form>
</div>
