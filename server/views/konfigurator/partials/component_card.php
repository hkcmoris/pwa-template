<?php

/** @var string $option */
$option = isset($option) ? (string) $option : '';
?>
<div class="component-card">
    <div class="options-card-inner">
        <h2 class="options-card-title">
            <?= htmlspecialchars($option) ?>
        </h2>
    </div>
</div>
