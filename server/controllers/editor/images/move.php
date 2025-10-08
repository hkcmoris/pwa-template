<?php

use Images\Repository;

require_once __DIR__ . '/../../../bootstrap.php';

csrf_require_valid($_POST, 'html');

$repository = new Repository();
$file = isset($_POST['file']) && is_string($_POST['file'])
    ? $repository->sanitizeRelative($_POST['file'])
    : '';
$to = isset($_POST['to']) && is_string($_POST['to'])
    ? $repository->sanitizeRelative($_POST['to'])
    : '';
$current = isset($_POST['current']) && is_string($_POST['current'])
    ? $repository->sanitizeRelative($_POST['current'])
    : '';

if ($file !== '' && $to !== '') {
    $repository->move($file, $to);
}

// Re-render current view
$_GET['path'] = $current;
require __DIR__ . '/../../../views/editor/partials/images-grid.php';
