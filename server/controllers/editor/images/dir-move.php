<?php

use Images\Repository;

require_once __DIR__ . '/../../../bootstrap.php';

$repository = new Repository();
$dir = isset($_POST['dir']) && is_string($_POST['dir']) ? $repository->sanitizeRelative($_POST['dir']) : '';
$to = isset($_POST['to']) && is_string($_POST['to']) ? $repository->sanitizeRelative($_POST['to']) : '';
$current = isset($_POST['current']) && is_string($_POST['current']) ? $repository->sanitizeRelative($_POST['current']) : '';

if ($dir !== '' && $to !== '') {
    $repository->moveDir($dir, $to);
}

// Re-render current view
$_GET['path'] = $current;
require __DIR__ . '/../../../views/editor/partials/images-grid.php';
