<?php

use Images\Repository;

require_once __DIR__ . '/../../../bootstrap.php';

$repository = new Repository();
$current = isset($_POST['current']) && is_string($_POST['current'])
    ? $repository->sanitizeRelative($_POST['current'])
    : '';
$name = isset($_POST['name']) && is_string($_POST['name'])
    ? (string) $_POST['name']
    : '';

if ($name !== '') {
    $repository->createDir($current, $name);
}

// Re-render current view
$_GET['path'] = $current;
require __DIR__ . '/../../../views/editor/partials/images-grid.php';
