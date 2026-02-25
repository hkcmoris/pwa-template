<?php

/** @var Configuration\ConfigurationWizard $wizard */

$selectedPath = $wizard->getSelectedPath();
$breadcrumbPath = $wizard->getBreadcrumbPath();
$currentComponent = $wizard->getCurrentComponent();
$availableOptions = $wizard->getAvailableOptions();
$summary = $wizard->buildSummary();
?>
<div id="konfigurator-wizard">
    <?php
    $breadcrumbs = __DIR__ . '/breadcrumbs.php';
    if (is_file($breadcrumbs)) {
        require $breadcrumbs;
    } else {
        echo '<p>Navigační panel nebyl nalezen.</p>';
    }

    $options = __DIR__ . '/component-options.php';
    if (is_file($options)) {
        require $options;
    } else {
        echo '<p>Panel s volbami nebyl nalezen.</p>';
    }
    ?>
</div>
