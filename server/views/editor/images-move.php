<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/images.php';

$BASE = rtrim((defined('BASE_PATH') ? BASE_PATH : ''), '/');
$file = isset($_POST['file']) && is_string($_POST['file']) ? img_sanitize_rel($_POST['file']) : '';
$to   = isset($_POST['to']) && is_string($_POST['to']) ? img_sanitize_rel($_POST['to']) : '';
$current = isset($_POST['current']) && is_string($_POST['current']) ? img_sanitize_rel($_POST['current']) : '';

if ($file && is_string($to)) {
  @img_move($file, $to);
}

// Re-render current view
$_GET['path'] = $current;
require __DIR__ . '/partials/images-grid.php';
