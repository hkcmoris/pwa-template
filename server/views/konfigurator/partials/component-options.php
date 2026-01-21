<?php
// Breadcrumbs partial for Konfigurátor
$options = [
    '1',
    '2',
    '3',
];
?>
<div id="component-options">
    <?php foreach ($options as $option) :
        $componentCard = __DIR__ . '/component-card.php';
        if (is_file($componentCard)) {
            require $componentCard;
        } else {
            echo '<p>Karta komponenty nebyla nalezena.</p>';
        }
    endforeach; ?>
</div>
