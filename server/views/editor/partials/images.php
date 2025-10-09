<?php

declare(strict_types=1);

use Images\Repository;

// Normalize base path for view usage.
$baseCandidate = defined('BASE_PATH') ? (string) BASE_PATH : '';
$BASE = isset($BASE) && $BASE !== '' ? (string) $BASE : $baseCandidate;
$BASE = rtrim($BASE, '/');

$repository = new Repository();
$pathInput = isset($_GET['path']) && is_string($_GET['path']) ? $_GET['path'] : '';
$sanitizedPath = $repository->sanitizeRelative((string) $pathInput);
[, $currentPath] = $repository->resolve($sanitizedPath);
$beforeSwapHandler = 'htmx:beforeSwap: if (event.detail.xhr && event.detail.xhr.status'
    . ' && event.detail.xhr.status >= 400) { event.detail.shouldSwap = false; }';
$uploadHxVals = 'js:{ path: document.getElementById("images-root")?.dataset.currentPath'
    . ' || "" }';
$initialGridUrl = htmlspecialchars(
    $BASE . '/editor/images-grid?path=' . rawurlencode($currentPath),
    ENT_QUOTES,
    'UTF-8'
);
?>

<?php
$imagesStyleEntry = 'src/styles/editor/images.css';
if (isset($_SERVER['HTTP_HX_REQUEST'])) {
    $imagesCssHref = vite_asset_href($imagesStyleEntry, $isDevEnv ?? false, $BASE);
    if ($imagesCssHref !== null) {
        ?>
<link
  rel="stylesheet"
  id="editor-partial-style"
  href="<?= htmlspecialchars($imagesCssHref, ENT_QUOTES, 'UTF-8') ?>"
  hx-swap-oob="true"
>
        <?php
    }
}
?>

<h2>Správce galerie</h2>

<div
    id="images-root"
    data-island="images"
    data-current-path="<?= htmlspecialchars($currentPath) ?>">
    <?php if (!function_exists('imagewebp') && !class_exists('Imagick')) : ?>
        <div class="upload-errors" role="alert">
            Poznámka: Konverze do WebP není dostupná (chybí PHP GD s WebP nebo Imagick).
            Nahrávání selže, dokud ji nepovolíte.
        </div>
    <?php endif; ?>
    <form
        id="upload-form"
        class="upload"
        enctype="multipart/form-data"
        hx-post="<?= htmlspecialchars($BASE) ?>/editor/images/upload"
        hx-vals="<?= htmlspecialchars($uploadHxVals, ENT_QUOTES, 'UTF-8') ?>"
        hx-target="#image-grid"
        hx-select="#image-grid"
        hx-swap="outerHTML">
        <?= csrf_field() ?>
        <label>
            <span>Nahrát obrázky</span>
            <input type="file" name="images[]" accept="image/*" multiple required>
        </label>
        <button type="submit">Nahrát a převést do WebP</button>
        <button type="button" id="new-folder-btn">Nová složka</button>
    </form>

    <div id="upload-errors" class="upload-errors hidden" role="status" aria-live="polite"></div>

    <div
        id="image-grid"
        class="grid"
        hx-get="<?= $initialGridUrl ?>"
        hx-select="#image-grid"
        hx-trigger="load"
        hx-swap="outerHTML"
        hx-on="<?= htmlspecialchars($beforeSwapHandler, ENT_QUOTES) ?>">
        Načítám
    </div>
    <!-- Context menu (custom) -->
    <div id="img-context-menu" class="hidden" role="menu" aria-hidden="true"></div>
    <!-- Modal container for image preview -->
    <div id="img-modal" class="hidden" aria-hidden="true"></div>
</div>


