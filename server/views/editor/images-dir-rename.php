<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/images.php';

$BASE = rtrim((defined('BASE_PATH') ? BASE_PATH : ''), '/');
$dir = isset($_POST['dir']) && is_string($_POST['dir']) ? img_sanitize_rel($_POST['dir']) : '';
$new = isset($_POST['newName']) && is_string($_POST['newName']) ? $_POST['newName'] : '';
$current = isset($_POST['current']) && is_string($_POST['current']) ? img_sanitize_rel($_POST['current']) : '';

if ($dir && $new) {
    @img_rename_dir($dir, $new);
}

// Re-render current view
$_GET['path'] = $current;
require __DIR__ . '/partials/images-grid.php';
