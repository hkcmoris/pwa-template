<?php

use Images\Repository;

require_once __DIR__ . '/../../../bootstrap.php';

$repository = new Repository();
$dir = isset($_POST['dir']) && is_string($_POST['dir'])
    ? $repository->sanitizeRelative($_POST['dir'])
    : '';
$current = isset($_POST['current']) && is_string($_POST['current'])
    ? $repository->sanitizeRelative($_POST['current'])
    : '';
$recursive = isset($_POST['recursive'])
    && in_array($_POST['recursive'], ['1', 'true', 'on'], true);

if ($dir !== '') {
    $repository->deleteDir($dir, $recursive);
}

// Re-render current view
$_GET['path'] = $current;
require __DIR__ . '/../../../views/editor/partials/images-grid.php';
