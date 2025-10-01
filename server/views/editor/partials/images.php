<?php
require_once __DIR__ . '/../../../lib/images.php';

$currentPath = isset($_GET['path']) && is_string($_GET['path']) ? img_sanitize_rel($_GET['path']) : '';
[$curDir, $currentPath] = img_resolve($currentPath);
?>

<h2>Správce galerie</h2>

<div id="images-root" data-island="images"
     data-current-path="<?= htmlspecialchars($currentPath) ?>">
  <?php if (!function_exists('imagewebp') && !class_exists('Imagick')) : ?>
    <div class="upload-errors" role="alert">
      Poznámka: Konverze do WebP není dostupná (chybí PHP GD s WebP nebo Imagick). Nahrávání selže, dokud ji nepovolíte.
    </div>
  <?php endif; ?>
  <form id="upload-form" class="upload" enctype="multipart/form-data"
        hx-post="<?= htmlspecialchars($BASE) ?>/editor/images-upload"
        hx-vals='js:{ path: document.getElementById("images-root")?.dataset.currentPath || "" }'
        hx-target="#image-grid"
        hx-select="#image-grid"
        hx-swap="outerHTML">
    <label>
      <span>Nahrát obrázky</span>
      <input type="file" name="images[]" accept="image/*" multiple required>
    </label>
    <button type="submit">Nahrát a převést do WebP</button>
      <button type="button" id="new-folder-btn">Nová složka</button>
  </form>

  <div id="upload-errors" class="upload-errors hidden" role="status" aria-live="polite"></div>

  <div id="image-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:.5rem"
       hx-get="<?= htmlspecialchars($BASE) ?>/editor/images-grid?path=<?= rawurlencode($currentPath) ?>"
       hx-select="#image-grid"
       hx-trigger="load"
       hx-swap="outerHTML"
       hx-on="htmx:beforeSwap: if (event.detail.xhr && event.detail.xhr.status && event.detail.xhr.status >= 400) { event.detail.shouldSwap = false; }">
    Načítám
  </div>
  <!-- Context menu (custom) -->
  <div id="img-context-menu" class="hidden" role="menu" aria-hidden="true"></div>
  <!-- Modal container for image preview -->
  <div id="img-modal" class="hidden" aria-hidden="true"></div>
</div>

<style>
  /* Minimal critical styles for layout; island loads detailed CSS lazily */
  #images-root .upload{display:flex;gap:.5rem;align-items:center;margin:.5rem 0;flex-wrap:wrap}
  #images-root .upload input[type="file"]{border:1px solid var(--fg);padding:.25rem;border-radius:.25rem;background:transparent;color:inherit}
  #image-grid{min-height:120px}
  .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:.5rem}
  .tile{border:1px solid var(--fg);border-radius:.25rem;padding:.25rem;display:flex;flex-direction:column;gap:.25rem;cursor:default;background:transparent}
  .tile:hover{outline:2px solid var(--primary);outline-offset:0}
  .tile .thumb{display:flex;align-items:center;justify-content:center;aspect-ratio:1/1;background:rgb(0 0 0 / 4%);overflow:hidden}
  .tile .thumb img{max-width:100%;max-height:100%;display:block}
  .tile .thumb svg{display:block;width:48px;height:48px;max-width:100%;max-height:100%}
  .tile .label{font-size:.85rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .tile.folder .thumb svg{color:var(--primary)}
  .droptarget{outline:2px dashed var(--primary) !important}
  .hidden{display:none}
  .upload-errors{margin:.25rem 0;padding:.5rem;border:1px solid #dc2626;color:#dc2626;border-radius:.25rem;background:rgb(220 38 38 / 8%)}
</style>



