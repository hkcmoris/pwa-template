<?php
require_once __DIR__ . '/../../../lib/images.php';

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');
    header('Vary: HX-Request, HX-Boosted, X-Requested-With, Cookie');
}

$BASE = $BASE ?? (defined('BASE_PATH') ? rtrim(BASE_PATH, '/') : '');
$path = isset($_GET['path']) && is_string($_GET['path']) ? img_sanitize_rel($_GET['path']) : '';
[$dir, $path] = img_resolve($path);
$list = img_list($path, img_root_url($BASE));

$beforeSwapHandler = 'htmx:beforeSwap: if (event.detail.xhr && event.detail.xhr.status'
    . ' && event.detail.xhr.status >= 400) { event.detail.shouldSwap = false; }';

// Compute parent path
$parentRel = '';
if ($path !== '') {
    $parentRel = (strpos($path, '/') !== false) ? dirname($path) : '';
}
?>

<div
    id="image-grid"
    class="grid"
    data-current-path="<?= htmlspecialchars($path) ?>"
    hx-on="<?= htmlspecialchars($beforeSwapHandler, ENT_QUOTES) ?>"
>
  <?php if ($path !== '') : ?>
  <div
      class="tile folder"
      tabindex="0"
      data-up="1"
      data-folder-rel="<?= htmlspecialchars($parentRel) ?>"
      hx-get="<?= htmlspecialchars($BASE) ?>/editor/images-grid?path=<?= rawurlencode($parentRel) ?>"
      hx-target="#image-grid"
      hx-select="#image-grid"
      hx-swap="outerHTML"
      hx-trigger="dblclick"
  >
    <div class="thumb" aria-hidden="true">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48" fill="currentColor">
        <path d="M10 4H4c-1.1 0-2 .9-2 2v2h20V8c0-1.1-.9-2-2-2h-8l-2-2zM2 10v8c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2v-8H2z" />
      </svg>
    </div>
    <div class="label">↥ Nahoru</div>
  </div>
  <?php endif; ?>

  <?php foreach ($list['dirs'] as $d) : ?>
  <div
      class="tile folder"
      tabindex="0"
      data-folder-rel="<?= htmlspecialchars($d['rel']) ?>"
      hx-get="<?= htmlspecialchars($BASE) ?>/editor/images-grid?path=<?= rawurlencode($d['rel']) ?>"
      hx-target="#image-grid"
      hx-select="#image-grid"
      hx-swap="outerHTML"
      hx-trigger="dblclick"
  >
    <div class="thumb" aria-hidden="true">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48" fill="currentColor">
        <path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z" />
      </svg>
    </div>
    <div class="label" title="<?= htmlspecialchars($d['name']) ?>">
        <?= htmlspecialchars($d['name']) ?>
    </div>
  </div>
  <?php endforeach; ?>

  <?php foreach ($list['images'] as $img) : ?>
  <div
      class="tile image"
      tabindex="0"
      data-image-rel="<?= htmlspecialchars($img['rel']) ?>"
      data-image-url="<?= htmlspecialchars($img['url']) ?>"
  >
    <div class="thumb">
      <img
        loading="lazy"
        decoding="async"
        src="<?= htmlspecialchars($img['thumbUrl']) ?>"
        alt="<?= htmlspecialchars($img['name']) ?>"
      >
    </div>
    <div class="label" title="<?= htmlspecialchars($img['name']) ?>">
        <?= htmlspecialchars($img['name']) ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
