<?php
// Normalize base path for view usage.
$baseCandidate = defined('BASE_PATH') ? BASE_PATH : '';
$BASE = isset($BASE) && is_string($BASE) ? $BASE : (is_string($baseCandidate) ? $baseCandidate : '');
$BASE = rtrim($BASE, '/');
// Access control: only admin or superadmin may access the editor
$__editorUser = isset($currentUser) && is_array($currentUser) ? $currentUser : app_get_current_user();
$__editorRole = isset($__editorUser['role']) ? $__editorUser['role'] : (isset($role) ? $role : 'guest');
if (!in_array($__editorRole, ['admin','superadmin'], true)) {
    if (!headers_sent()) {
        http_response_code(403);
    }
  // Keep #editor-root present so htmx subnav swaps don't remove the container
    echo '<h1>Editor</h1>';
    echo '<div id="editor-root">';
    echo '<div role="alert">';
    echo '  <h2>Přístup odepřen</h2>';
    echo '  <p>Nemáte oprávnění pro zobrazení editoru.</p>';
    echo '</div>';
    echo '</div>';
    return;
}
?>
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
  <nav class="subnav" aria-label="Editor navigace" style="display:flex;gap:.5rem;margin:.5rem 0 .75rem;flex-wrap:wrap">
    <a href="<?= htmlspecialchars($BASE) ?>/editor/definitions"
       hx-get="<?= htmlspecialchars($BASE) ?>/editor/definitions"
       hx-push-url="true"
       hx-target="#editor-root"
       hx-select="#editor-root"
       hx-swap="outerHTML"
       class="<?= $active === 'definitions' ? 'active' : '' ?>">Definice</a>

    <a href="<?= htmlspecialchars($BASE) ?>/editor/components"
       hx-get="<?= htmlspecialchars($BASE) ?>/editor/components"
       hx-push-url="true"
       hx-target="#editor-root"
       hx-select="#editor-root"
       hx-swap="outerHTML"
       class="<?= $active === 'components' ? 'active' : '' ?>">Komponenty</a>

    <a href="<?= htmlspecialchars($BASE) ?>/editor/images"
       hx-get="<?= htmlspecialchars($BASE) ?>/editor/images"
       hx-push-url="true"
       hx-target="#editor-root"
       hx-select="#editor-root"
       hx-swap="outerHTML"
       class="<?= $active === 'images' ? 'active' : '' ?>">Správce galerie</a>
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
