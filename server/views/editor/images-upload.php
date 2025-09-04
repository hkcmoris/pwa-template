<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/images.php';

$BASE = rtrim((defined('BASE_PATH') ? BASE_PATH : ''), '/');
$path = isset($_GET['path']) && is_string($_GET['path']) ? img_sanitize_rel($_GET['path']) : '';
[$dir, $path] = img_resolve($path);
img_ensure_dir($dir);

$errors = [];
if (!empty($_FILES['images']) && is_array($_FILES['images']['name'])) {
  $names = $_FILES['images']['name'];
  $tmps  = $_FILES['images']['tmp_name'];
  $errs  = $_FILES['images']['error'];
  $count = count($names);
  for ($i=0; $i<$count; $i++) {
    if (($errs[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      $errors[] = $names[$i] . ': upload failed';
      continue;
    }
    $tmp = $tmps[$i];
    $safe = img_safe_name($names[$i], $dir);
    $dest = $dir . '/' . $safe;
    $ok = img_convert_to_webp($tmp, $dest);
    if (!$ok) {
      $msg = img_last_error();
      if ($msg === '') $msg = 'conversion failed';
      $errors[] = $names[$i] . ': ' . $msg;
    }
  }
}

// Out-of-band update for error box if any
if (!empty($errors)) {
  echo '<div id="upload-errors" hx-swap-oob="true" class="upload-errors" role="alert">'
     . '<strong>Chyba nahrávání:</strong><ul>'
     . implode('', array_map(fn($e)=>'<li>'.htmlspecialchars($e).'</li>', $errors))
     . '</ul></div>';
} else {
  // Clear errors if previously shown
  echo '<div id="upload-errors" hx-swap-oob="true" class="upload-errors hidden"></div>';
}

// Render the grid after upload
require __DIR__ . '/partials/images-grid.php';
