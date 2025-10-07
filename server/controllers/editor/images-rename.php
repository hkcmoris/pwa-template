<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/images.php';

$BASE = rtrim((defined('BASE_PATH') ? BASE_PATH : ''), '/');
$file = isset($_POST['file']) && is_string($_POST['file']) ? img_sanitize_rel($_POST['file']) : '';
$new  = isset($_POST['newName']) && is_string($_POST['newName']) ? $_POST['newName'] : '';
$current = isset($_POST['current']) && is_string($_POST['current']) ? img_sanitize_rel($_POST['current']) : '';

if ($file && $new) {
    @img_rename($file, $new);
}

// Use provided current path or derive from file
if ($current === '' && $file !== '') {
    $current = dirname($file);
    if ($current === '.') {
        $current = '';
    }
}
$_GET['path'] = $current;
require __DIR__ . '/../../views/editor/partials/images-grid.php';
