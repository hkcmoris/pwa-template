<?php
// Breadcrumbs partial for Konfigurátor
$breadcrumbs = [
    '1',
    '2',
    '3',
];
?>
<div id="breadcrumbs">
    <?php foreach ($breadcrumbs as $crumb) : ?>
    <div class="breadcrumb-item">
        <span class="breadcrumb-item-inner">
            <span class="breadcrumb-item-title">
                <?= htmlspecialchars($crumb) ?>
            </span>
        </span>
    </div>
    <?php endforeach; ?>
</div>
