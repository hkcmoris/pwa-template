<?php

use Images\Repository;

require_once __DIR__ . '/../../../bootstrap.php';

$repository = new Repository();
$file = isset($_POST['file']) && is_string($_POST['file']) ? $repository->sanitizeRelative($_POST['file']) : '';
$current = isset($_POST['current']) && is_string($_POST['current']) ? $repository->sanitizeRelative($_POST['current']) : '';

if ($file !== '') {
    $repository->delete($file);
}

// Re-render current view
$_GET['path'] = $current;
require __DIR__ . '/../../../views/editor/partials/images-grid.php';
