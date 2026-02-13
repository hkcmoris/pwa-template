<?php
// Normalize base path for view usage.
$baseCandidate = defined('BASE_PATH') ? (string) BASE_PATH : '';
$BASE = isset($BASE) && $BASE !== '' ? (string) $BASE : $baseCandidate;
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
  <nav
    class="subnav"
    aria-label="Editor navigace"
    style="display:flex;gap:.5rem;margin:.5rem 0 .75rem;flex-wrap:wrap"
  >
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
    <svg
      width="0px"
      height="0px"
      display="none"
      style="display: none;"
      aria-hidden="true"
    >
      <symbol id="icon-trash" viewBox="0 0 512 512">
        <path
          d="M64,160,93.74,442.51A24,24,0,0,0,117.61,464H394.39a24,24,0,0,0,23.87-21.49L448,160
            M312,377.46l-56-56-56,56L174.54,352l56-56-56-56L200,214.54l56,56,56-56L337.46,240l-56,56,56,56Z"
        />
        <rect x="32" y="48" width="448" height="80" rx="12" ry="12"/>
      </symbol>
      <symbol id="icon-range" viewBox="0 0 72 72">
        <g id="line">
          <line
            x1="8.0416"
            x2="64.0416"
            y1="29"
            y2="29"
            fill="none"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-miterlimit="10"
            stroke-width="2"
          />
          <line
            x1="8.0416"
            x2="8.0416"
            y1="26"
            y2="34"
            fill="none"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-miterlimit="10"
            stroke-width="2"
          />
          <line
            x1="64.0416"
            x2="64.0416"
            y1="26"
            y2="34"
            fill="none"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-miterlimit="10"
            stroke-width="2"
          />
          <line
            x1="36.0416"
            x2="36.0416"
            y1="26"
            y2="34"
            fill="none"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-miterlimit="10"
            stroke-width="2"
          />
          <line
            x1="22.0416"
            x2="22.0416"
            y1="26"
            y2="32"
            fill="none"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-miterlimit="10"
            stroke-width="2"
          />
          <line
            x1="50.0416"
            x2="50.0416"
            y1="26"
            y2="32"
            fill="none"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-miterlimit="10"
            stroke-width="2"
          />
          <path
            fill="none"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-miterlimit="10"
            stroke-width="2"
            d="M8.0085,45.9053L8.0085,45.9053c-1.0579,0-1.9155-0.8576-1.9155-1.9155v-3.1689
              c0-1.0579,0.8576-1.9156,1.9155-1.9156l0,0
              c1.058,0,1.9156,0.8577,1.9156,1.9156v3.1689
              C9.9241,45.0477,9.0665,45.9053,8.0085,45.9053z"
          />
          <path
            fill="none"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-miterlimit="10"
            stroke-width="2"
            d="M61.8334,40.6119c0.2005-0.9798,1.0674-1.7169,2.1065-1.7169l0,0c0.5937,0,1.1313,0.2407,1.5204,0.6298
              c0.6053,0.6053,0.5494,1.6111-0.0185,2.2515l-3.6521,4.1187h4.3004"
          />
          <polyline
            fill="none"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-miterlimit="10"
            stroke-width="2"
            points="35.1209,40.4123 37.0588,38.9605
              37.0588,45.9605"
          />
        </g>
      </symbol>
      <symbol id="icon-rename" viewBox="0 0 24 24">
        <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
          <g fill="currentColor" fill-rule="nonzero">
            <path
              d="M20.0624471,8.44531708 C21.3187966,9.70246962 21.3181456,11.7400656 20.060993,12.9964151 L12.9470184,
              20.0984035 C12.6752626,20.3697014 12.338595,20.5669341 11.9690608,20.6713285 L7.35604585,
              21.9745173 C6.78489045,22.1358702 6.26149869,21.6013269 6.43485528,21.0336996 L7.82237944,
              16.4904828 C7.93021937,16.1373789 8.12329466,15.8162379 8.38457552,15.5553852 L15.5089127,
              8.44272265 C16.767237,7.18646041 18.8055552,7.18762176 20.0624471,8.44531708 Z M8.1508398,
              2.36975012 L8.20132289,2.47486675 L11.4537996,10.724 L10.2967996,11.879 L9.5557996,10 L5.4427996,
              10 L4.44747776,12.5208817 C4.30788849,12.8739875 3.9301318,13.0620782 3.57143476,12.9736808 L3.47427411,
              12.9426336 C3.1211683,12.8030443 2.93307758,12.4252876 3.02147501,12.0665906 L3.05252224,
              11.9694299 L6.80613337,2.47427411 C7.0415216,1.87883471 7.84863764,1.84414583 8.1508398,
              2.36975012 Z M7.50274363,4.79226402 L6.0357996,8.5 L8.9637996,8.5 L7.50274363,4.79226402 Z"
            />
          </g>
        </g>
      </symbol>
      <symbol id="icon-edit" viewBox="0 0 24 24">
        <path
          fill="currentColor"
          fill-rule="evenodd"
          clip-rule="evenodd"
          d="
            M20.8477 1.87868C19.6761 0.707109 17.7766 0.707105 16.605 1.87868L2.44744 16.0363
            C2.02864 16.4551 1.74317 16.9885 1.62702 17.5692L1.03995 20.5046
            C0.760062 21.904 1.9939 23.1379 3.39334 22.858L6.32868 22.2709
            C6.90945 22.1548 7.44285 21.8693 7.86165 21.4505L22.0192 7.29289
            C23.1908 6.12132 23.1908 4.22183 22.0192 3.05025L20.8477 1.87868
            ZM18.0192 3.29289C18.4098 2.90237 19.0429 2.90237 19.4335 3.29289
            L20.605 4.46447C20.9956 4.85499 20.9956 5.48815 20.605 5.87868
            L17.9334 8.55027L15.3477 5.96448L18.0192 3.29289
            ZM13.9334 7.3787L3.86165 17.4505C3.72205 17.5901 3.6269 17.7679
            3.58818 17.9615L3.00111 20.8968L5.93645 20.3097
            C6.13004 20.271 6.30784 20.1759 6.44744 20.0363L16.5192 9.96448
            L13.9334 7.3787Z
          "
        />
      </symbol>
      <symbol id="icon-clone" viewBox="0 0 24 24">
        <path
          fill="currentColor"
          d="M8 7V5a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-9a2 2 0 0 1 2-2h3Zm2-2v2h5a2 2 0 0 1 2 2v5h2V5h-9Zm5 14v-9H5v9h10Z"
        />
      </symbol>
      <symbol id="icon-add" viewBox="0 0 512 512">
        <g
          id="Page-1"
          stroke="none"
          stroke-width="1"
          fill="none"
          fill-rule="evenodd"
        >
          <g
            id="icon"
            fill="currentColor"
            transform="translate(42.666667, 128.000000)"
          >
            <path
              d="M384,192 L383.999,256 L448,256 L448,298.666667 L383.999,298.666 L384,362.666667
                L341.333333,362.666667 L341.333,298.666 L277.333333,298.666667 L277.333333,256
                L341.333,256 L341.333333,192 L384,192 Z M149.333333,0 L149.333333,149.333333
                L3.55271368e-14,149.333333 L3.55271368e-14,0 L149.333333,0 Z M106.666667,42.6666667
                L42.6666667,42.6666667 L42.6666667,106.666667 L106.666667,106.666667
                L106.666667,42.6666667 Z M213.333333,64 L426.666667,64 L426.666667,106.666667
                L213.333333,106.666667 L213.333333,64 Z"
              id="Combined-Shape"
            />
          </g>
        </g>
      </symbol>
    </svg>
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
