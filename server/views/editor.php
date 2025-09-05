<?php
// Determine active subpage: definitions (default), components, images
$active = isset($editorActive) && is_string($editorActive)
  ? $editorActive
  : ((isset($route) && is_string($route) && strpos($route, 'editor/') === 0)
      ? explode('/', $route, 2)[1]
      : 'definitions');
if (!in_array($active, ['definitions','components','images'], true)) {
  $active = 'definitions';
}
?>

<h1>Editor</h1>
<div id="editor-root">
  <nav class="subnav" aria-label="Editor navigace" style="display:flex;gap:.5rem;margin:.5rem 0 .75rem 0;flex-wrap:wrap">
    <a href="<?= htmlspecialchars($BASE) ?>/editor/definitions"
       hx-get="<?= htmlspecialchars($BASE) ?>/editor/definitions"
       hx-push-url="true"
       hx-target="#editor-root"
       hx-select="#editor-root"
       hx-swap="outerHTML"
       class="<?= $active==='definitions' ? 'active' : '' ?>">Definice</a>

    <a href="<?= htmlspecialchars($BASE) ?>/editor/components"
       hx-get="<?= htmlspecialchars($BASE) ?>/editor/components"
       hx-push-url="true"
       hx-target="#editor-root"
       hx-select="#editor-root"
       hx-swap="outerHTML"
       class="<?= $active==='components' ? 'active' : '' ?>">Komponenty</a>

    <a href="<?= htmlspecialchars($BASE) ?>/editor/images"
       hx-get="<?= htmlspecialchars($BASE) ?>/editor/images"
       hx-push-url="true"
       hx-target="#editor-root"
       hx-select="#editor-root"
       hx-swap="outerHTML"
       class="<?= $active==='images' ? 'active' : '' ?>">Správce galerie</a>
  </nav>

  <section id="editor-content">
  <?php
    $partial = __DIR__ . '/editor/partials/' . $active . '.php';
    if (is_file($partial)) {
      require $partial;
    } else {
      echo '<p>Obsah nelze načíst.</p>';
    }
  ?>
  </section>
</div>
