<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/images.php';

$BASE = rtrim((defined('BASE_PATH') ? BASE_PATH : ''), '/');
$current = isset($_POST['current']) && is_string($_POST['current']) ? img_sanitize_rel($_POST['current']) : '';
$name    = isset($_POST['name']) && is_string($_POST['name']) ? $_POST['name'] : '';

if ($name !== '') {
  @img_create_dir($current, $name);
}

// Re-render current view
$_GET['path'] = $current;
require __DIR__ . '/partials/images-grid.php';

