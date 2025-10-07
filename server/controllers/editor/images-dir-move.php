<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/images.php';

$BASE = rtrim((defined('BASE_PATH') ? BASE_PATH : ''), '/');
$dir = isset($_POST['dir']) && is_string($_POST['dir']) ? img_sanitize_rel($_POST['dir']) : '';
$to  = isset($_POST['to']) && is_string($_POST['to']) ? img_sanitize_rel($_POST['to']) : '';
$current = isset($_POST['current']) && is_string($_POST['current']) ? img_sanitize_rel($_POST['current']) : '';

if ($dir !== '' && $to !== '') {
    @img_move_dir($dir, $to);
}

// Re-render current view
$_GET['path'] = $current;
require __DIR__ . '/../../views/editor/partials/images-grid.php';
