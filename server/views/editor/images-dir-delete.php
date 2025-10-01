<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/images.php';

$BASE = rtrim((defined('BASE_PATH') ? BASE_PATH : ''), '/');
$dir = isset($_POST['dir']) && is_string($_POST['dir']) ? img_sanitize_rel($_POST['dir']) : '';
$current = isset($_POST['current']) && is_string($_POST['current']) ? img_sanitize_rel($_POST['current']) : '';
$recursive = isset($_POST['recursive']) && ($_POST['recursive'] === '1' || $_POST['recursive'] === 'true' || $_POST['recursive'] === 'on');

if ($dir) {
    @img_delete_dir($dir, $recursive);
}

// Re-render current view
$_GET['path'] = $current;
require __DIR__ . '/partials/images-grid.php';
