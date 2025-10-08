<?php

use Images\Repository;

require_once __DIR__ . '/../../../bootstrap.php';

$repository = new Repository();
$dir = isset($_POST['dir']) && is_string($_POST['dir']) ? $repository->sanitizeRelative($_POST['dir']) : '';
$new = isset($_POST['newName']) && is_string($_POST['newName']) ? (string) $_POST['newName'] : '';
$current = isset($_POST['current']) && is_string($_POST['current']) ? $repository->sanitizeRelative($_POST['current']) : '';

if ($dir !== '' && $new !== '') {
    $repository->renameDir($dir, $new);
}

// Re-render current view
$_GET['path'] = $current;
require __DIR__ . '/../../../views/editor/partials/images-grid.php';
