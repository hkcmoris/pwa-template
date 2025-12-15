<?php
// Breadcrumbs partial for Konfigurátor
$options = [
    '1',
    '2',
    '3',
];
?>
<div id="component-options">
    <?php foreach ($options as $option) : ?>
    <div class="options-card">
        <span class="options-card-inner">
            <span class="options-card-title">
                <?= htmlspecialchars($option) ?>
            </span>
        </span>
    </div>
    <?php endforeach; ?>
</div>
