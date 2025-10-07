<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/images.php';

$BASE = rtrim((defined('BASE_PATH') ? BASE_PATH : ''), '/');
$file = isset($_POST['file']) && is_string($_POST['file']) ? img_sanitize_rel($_POST['file']) : '';
$current = isset($_POST['current']) && is_string($_POST['current']) ? img_sanitize_rel($_POST['current']) : '';

if ($file) {
    @img_delete($file);
}

// Re-render current view
$_GET['path'] = $current;
require __DIR__ . '/../../views/editor/partials/images-grid.php';
