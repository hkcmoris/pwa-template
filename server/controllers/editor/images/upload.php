<?php

use Images\Repository;

require_once __DIR__ . '/../../../bootstrap.php';

csrf_require_valid($_POST, 'html');

$repository = new Repository();
$pathParam = $_POST['path'] ?? $_GET['path'] ?? '';
$path = is_string($pathParam) ? $repository->sanitizeRelative($pathParam) : '';
[$dir, $path] = $repository->resolve($path);
$repository->ensureDir($dir);

$errors = [];
if (!empty($_FILES['images']) && is_array($_FILES['images']['name'])) {
    $names = $_FILES['images']['name'];
    $tmps  = $_FILES['images']['tmp_name'];
    $errs  = $_FILES['images']['error'];
    $count = count($names);
    for ($i = 0; $i < $count; $i++) {
        if (($errs[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = $names[$i] . ': nahrání se nezdařilo';
            continue;
        }
        $tmp = $tmps[$i];
        $safe = $repository->safeName($names[$i], $dir);
        $dest = $dir . '/' . $safe;
        $ok = $repository->convertToWebp($tmp, $dest);
        if ($ok) {
          // Generate a small preview thumbnail (e.g., 96x96)
            $repository->generateThumb($dest, 96);
        }
        if (!$ok) {
            $msg = $repository->getLastError();
            if ($msg === '') {
                $msg = 'konverze se nezdařila';
            }
            $errors[] = $names[$i] . ': ' . $msg;
        }
    }
}

// Out-of-band update for error box if any
if (!empty($errors)) {
    echo '<div id="upload-errors" hx-swap-oob="true" class="upload-errors" role="alert">'
     . '<strong>Chyba nahrávání:</strong><ul>'
     . implode('', array_map(fn($e)=>'<li>' . htmlspecialchars($e) . '</li>', $errors))
     . '</ul></div>';
} else {
  // Clear errors if previously shown
    echo '<div id="upload-errors" hx-swap-oob="true" class="upload-errors hidden"></div>';
}

// Render the grid after upload at the same path
$_GET['path'] = $path;
require __DIR__ . '/../../../views/editor/partials/images-grid.php';
