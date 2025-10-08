<?php

use Images\Repository;

require_once __DIR__ . '/../../../bootstrap.php';

$repository = new Repository();
$file = isset($_POST['file']) && is_string($_POST['file']) ? $repository->sanitizeRelative($_POST['file']) : '';
$new = isset($_POST['newName']) && is_string($_POST['newName']) ? (string) $_POST['newName'] : '';
$current = isset($_POST['current']) && is_string($_POST['current']) ? $repository->sanitizeRelative($_POST['current']) : '';

if ($file !== '' && $new !== '') {
    $repository->rename($file, $new);
}

// Use provided current path or derive from file
if ($current === '' && $file !== '') {
    $current = dirname($file);
    if ($current === '.') {
        $current = '';
    }
}
$_GET['path'] = $current;
require __DIR__ . '/../../../views/editor/partials/images-grid.php';
