<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
// Resolve current user for SSR gating and header state
$currentUser = app_get_current_user();
$role = $currentUser['role'] ?? 'guest';
$email = $currentUser['email'] ?? null;
$username = isset($username) && is_string($username) && $username !== ''
    ? $username
    : (is_string($email) && $email !== '' ? $email : 'Návštěvník');
$title = isset($title) && is_string($title) && $title !== '' ? $title : 'HAGEMANN konfigurátor';
$route = isset($route) && is_string($route) && $route !== '' ? $route : 'home';
$theme  = $_COOKIE['theme'] ?? 'light';
if ($theme !== 'dark' && $theme !== 'light') {
    $theme = 'light';
}

$csrfToken = csrf_token_if_active();

// Normalized base path for subfolder deployments ('' or '/subdir')
$BASE = rtrim((defined('BASE_PATH') ? (string) BASE_PATH : ''), '/');
$prettyUrlsEnabled = defined('PRETTY_URLS') ? (bool) PRETTY_URLS : false;

$appEnvValue = getenv('APP_ENV');
if (!is_string($appEnvValue) || $appEnvValue === '') {
    $appEnvValue = defined('APP_ENV') ? (string) APP_ENV : 'dev';
}
$isDevEnv = ($appEnvValue === 'dev');

$view = isset($view) && is_string($view) && $view !== '' ? $view : null;

/** @var array<string, mixed>|null $main */
$main = isset($main) && is_array($main) ? $main : null;
/** @var array<string, mixed>|null $fontsCss */
$fontsCss = isset($fontsCss) && is_array($fontsCss) ? $fontsCss : null;
/** @var array<string, string> $viewStyles */
$viewStyles = isset($viewStyles) && is_array($viewStyles) ? $viewStyles : [];
$resolvedViewStyles = [];
foreach ($viewStyles as $styleId => $entry) {
    $href = vite_asset_href($entry, $isDevEnv, $BASE);
    if ($href !== null) {
        $resolvedViewStyles[(string) $styleId] = $href;
    }
}
?>
<!doctype html>
  <html
    lang="cs"
    data-theme="<?= htmlspecialchars($theme) ?>"
    data-base="<?= htmlspecialchars($BASE) ?>"
    data-pretty="<?= $prettyUrlsEnabled ? '1' : '0' ?>"
    data-csrf="<?= htmlspecialchars($csrfToken) ?>"
  >
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars($title) ?></title>
    <meta
      name="description"
      content="<?= htmlspecialchars($description ?? 'HAGEMANN konfigurátor - rychlá PWA s PHP SSR.') ?>"
    />
    <?php if ($csrfToken !== '') : ?>
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    <?php endif; ?>
    <link rel="manifest" href="<?= htmlspecialchars($BASE) ?>/public/manifest.webmanifest">
    <style>
      :root {
        --bg: #fff;
        --fg: #111;
        --primary: #2563eb;
        --primary-contrast: #fff;
        --primary-hover: color-mix(in srgb, var(--primary) 85%, black);
        --fg-muted: #4b5563;
        --home-bg-light: none;
        --home-bg-dark: none;
      }

      *,
      *::before,
      *::after {
        box-sizing: border-box;
      }

      [data-theme='dark'] {
        --bg: #212529;
        --fg: #f5f5f5;
        --primary: #60a5fa;
        --primary-contrast: #0f172a;
        --primary-hover: color-mix(in srgb, var(--primary) 70%, white);
        --fg-muted: #cbd5e1;
      }

      body {
        margin: 0;
        background: var(--bg);
        color: var(--fg);
        font-family: system-ui, sans-serif;
        line-height: 1.5;
      }

      a {
        color: var(--primary);
        text-decoration: none;
      }

      a:hover {
        text-decoration: underline;
      }

      header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        justify-content: flex-start;
        padding: 0.5rem 1rem;
        background: var(--bg);
        border-bottom: 1px solid var(--fg);
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 999;
      }

      .logo {
        font-weight: bold;
        line-height: 0;
      }

      .logo img,
      .logo svg {
        display: block;
      }

      nav {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        flex: 1;
        min-width: 0;
      }

      .nav-links {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        flex-wrap: nowrap;
      }

      .nav-actions {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        margin-left: auto;
      }

      main {
        padding: 1rem;
        padding-top: 3.5rem;
        min-height: 100dvb;
        max-width: 1000px;
        margin: 0 auto;
      }
    </style>
    <?php if (!$isDevEnv) :
        $main = vite_asset('src/main.ts');
        $layoutCss = vite_asset('src/styles/layout.css');
        $fontsCss = vite_asset('src/styles/fonts.css');
        if ($layoutCss && !empty($layoutCss['file'])) : ?>
      <link
        rel="stylesheet"
        href="<?= htmlspecialchars($BASE) ?>/public/assets/<?= htmlspecialchars($layoutCss['file']) ?>"
      >
            <?php
        endif;
        if ($main && !empty($main['css'])) :
            foreach ($main['css'] as $css) : ?>
      <link
        rel="stylesheet"
        href="<?= htmlspecialchars($BASE) ?>/public/assets/<?= htmlspecialchars($css) ?>"
      >
                <?php
            endforeach;
        endif;
        if ($fontsCss && !empty($fontsCss['file'])) : ?>
      <link
        rel="preload"
        as="style"
        href="<?= htmlspecialchars($BASE) ?>/public/assets/<?= htmlspecialchars($fontsCss['file']) ?>"
        onload="this.rel='stylesheet'"
      >
      <noscript>
        <link
          rel="stylesheet"
          href="<?= htmlspecialchars($BASE) ?>/public/assets/<?= htmlspecialchars($fontsCss['file']) ?>"
        >
      </noscript>
            <?php
        endif;
        foreach ($resolvedViewStyles as $styleId => $href) : ?>
      <link
        rel="stylesheet"
        id="<?= htmlspecialchars($styleId) ?>"
        href="<?= htmlspecialchars($href) ?>"
      >
        <?php endforeach;
    endif; ?>
  </head>
    <body data-route="<?= htmlspecialchars($view ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <header id="main-header">
      <div class="logo">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 130 29.8"
          width="130"
          height="29.8"
          role="img"
          aria-hidden="true"
        >
          <path
            fill="currentColor"
            d="
              M117,20.6 c0,0.9-0.2,1.5-0.5,2 c-0.7,0.8-1.8,0.9-2.6,0.2
              c-0.1-0.1-0.2-0.1-0.2-0.2 c-0.3-0.4-0.5-1.1-0.5-2 V9 c0-0.8,0.2-1.5,0.4-1.9
              c0.3-0.4,0.8-0.7,1.3-0.6 c0.4,0,0.7,0.1,1.1,0.3 c0.4,0.3,0.7,0.6,1,0.9 l7,9.2
              V9.1 c0-0.8,0.1-1.5,0.4-1.9 c0.6-0.7,1.7-0.8,2.5-0.2 c0.1,0.1,0.1,0.1,0.2,0.2
              c0.3,0.4,0.4,1.1,0.4,1.9 V21 c0,0.8-0.1,1.4-0.4,1.8 s-0.8,0.6-1.3,0.6
              c-0.4,0-0.8-0.1-1.1-0.3 c-0.4-0.3-0.7-0.6-1-1 l-6.8-8.9 v7.6 M99.9,20.6
              c0,0.9-0.2,1.6-0.5,2.1 c-0.7,0.8-1.9,0.9-2.7,0.2 c-0.1-0.1-0.2-0.1-0.2-0.2
              c-0.3-0.5-0.5-1.1-0.5-2.1 V9.1 c0-0.8,0.2-1.5,0.4-1.9 c0.3-0.4,0.8-0.6,1.3-0.6
              c0.4,0,0.7,0.1,1,0.3 c0.4,0.3,0.7,0.6,1,0.9 l6.9,9 V9.2 c0-0.9,0.2-1.6,0.5-2.1
              c0.7-0.9,2.1-0.9,2.8-0.1 l0.1,0.1 c0.3,0.5,0.4,1.1,0.4,2 V21
              c0,0.8-0.1,1.4-0.4,1.7 c-0.3,0.4-0.8,0.6-1.3,0.6 c-0.4,0-0.8-0.1-1.1-0.3
              c-0.4-0.3-0.7-0.6-1-1 l-6.8-8.8 L99.9,20.6 L99.9,20.6 z M85.5,16.6 h3.9 l-1.9-6
              L85.5,16.6 z M84.5,19.9 l-0.6,1.6 c-0.1,0.5-0.4,0.9-0.7,1.2
              c-0.3,0.3-0.7,0.4-1.1,0.4 c-0.5,0-0.9-0.2-1.3-0.5 c-0.3-0.3-0.5-0.8-0.5-1.3
              c0-0.2,0-0.4,0.1-0.6 c0-0.2,0.1-0.3,0.1-0.5 l4.3-11.5 C85,8.2,85.4,7.6,85.9,7
              c0.5-0.4,1.1-0.6,1.7-0.6 s1.2,0.2,1.6,0.6 c0.5,0.4,0.9,1,1.1,1.6 l4.1,11.7
              c0.1,0.2,0.1,0.4,0.2,0.7 c0.1,0.2,0.1,0.3,0.1,0.5 c0,0.5-0.2,0.9-0.5,1.2
              c-0.4,0.3-0.8,0.5-1.3,0.5 c-0.4,0-0.9-0.2-1.2-0.4 c-0.4-0.4-0.6-0.9-0.7-1.4
              l-0.5-1.5 C90.5,19.9,84.5,19.9,84.5,19.9 z M61,20.2 l2-11.1
              c0.2-0.9,0.4-1.6,0.8-2 s1-0.6,1.7-0.6 c0.6,0,1.2,0.2,1.7,0.5
              c0.5,0.4,0.8,1,0.9,1.6 l2.5,9.7 l2.3-9.8 c0.1-0.6,0.4-1.2,0.9-1.6
              s1.1-0.6,1.6-0.5 c0.8,0,1.4,0.2,1.8,0.6 s0.7,1.1,0.9,2.1 l1.8,11.2
              c0,0.2,0.1,0.3,0.1,0.5 s0,0.3,0,0.4 c0,0.5-0.2,1-0.5,1.4
              c-0.4,0.4-0.9,0.5-1.4,0.5 s-0.9-0.2-1.2-0.5 c-0.4-0.5-0.6-1-0.6-1.6 l-1.2-9.7
              c-0.9,3.9-1.6,6.6-2,8.2 s-0.8,2.6-1,3 c-0.1,0.2-0.3,0.4-0.6,0.5
              c-0.3,0.1-0.6,0.2-1,0.2 c-0.5,0-1-0.1-1.4-0.4 c-0.4-0.4-0.6-0.9-0.7-1.4 l-2.4-10
              l-1.3,9.5 c-0.1,0.9-0.3,1.5-0.6,1.8 c-0.3,0.4-0.8,0.6-1.3,0.6 s-1-0.2-1.3-0.5
              c-0.4-0.4-0.5-0.9-0.5-1.4 c0-0.2,0-0.3,0-0.5 C60.9,20.7,61,20.5,61,20.2 L61,20.2
              z M57.2,6.6 c0.9,0,1.6,0.2,2.1,0.5 S60,7.9,60,8.5 c0,0.5-0.2,1-0.7,1.3
              s-1.1,0.4-2.1,0.4 H54 v3.1 h2.6 c0.9,0,1.5,0.2,2,0.4 c0.4,0.3,0.7,0.8,0.6,1.3
              c0,0.5-0.2,1-0.6,1.3 c-0.4,0.3-1.1,0.4-2,0.4 H54 v3.1 h3.3
              c0.9,0,1.6,0.1,2.1,0.4 s0.7,0.8,0.7,1.3 s-0.2,1-0.7,1.3 S58.2,23,57.3,23 h-4.5
              c-1,0-1.7-0.2-2.1-0.5 c-0.4-0.3-0.6-0.9-0.6-1.8 V9.2 c0-1,0.2-1.7,0.6-2.1
              c0.4-0.4,1.1-0.6,2.1-0.6 L57.2,6.6 z M44.2,16.6 h-1.3 c-0.9,0-1.5-0.1-1.9-0.4
              c-0.4-0.3-0.6-0.8-0.6-1.3 s0.2-1,0.6-1.3 c0.4-0.3,1-0.4,1.9-0.4 h2.7
              c0.9,0,1.5,0.2,1.9,0.6 c0.4,0.4,0.6,1,0.6,1.8 c0,2.2-0.7,4-2,5.4
              s-3.1,2.1-5.3,2.1 s-4-0.8-5.4-2.3 s-2.1-3.5-2.1-6 s0.7-4.5,2.2-6.1
              c1.4-1.5,3.4-2.3,5.8-2.3 c1.6,0,3,0.3,4.1,1 c1,0.8,1.6,1.5,1.6,2.4
              c0,0.4-0.1,0.8-0.4,1.2 c-0.3,0.3-0.6,0.5-1.1,0.5 c-0.3,0-0.9-0.2-1.8-0.8
              c-0.8-0.5-1.7-0.7-2.6-0.8 c-1.1,0-2.2,0.4-2.9,1.3 c-0.7,0.9-1.1,2-1.1,3.5
              s0.3,2.7,1.1,3.6 c0.7,0.9,1.7,1.4,2.9,1.3 c0.8,0,1.7-0.3,2.3-0.9
              C43.9,18.3,44.3,17.4,44.2,16.6 L44.2,16.6 z M23.1,16.6 H27 l-1.9-6 L23.1,16.6 z
              M22.1,19.9 l-0.6,1.6 c-0.1,0.5-0.4,0.9-0.7,1.2 c-0.3,0.3-0.7,0.4-1.1,0.4
              c-0.5,0-0.9-0.2-1.3-0.5 c-0.3-0.3-0.5-0.8-0.5-1.3 c0-0.2,0-0.4,0.1-0.6
              c0-0.2,0.1-0.3,0.1-0.5 l4.2-11.5 C22.6,8.2,23,7.6,23.5,7
              c0.5-0.4,1.1-0.6,1.7-0.6 s1.2,0.2,1.6,0.6 c0.5,0.4,0.9,1,1.1,1.6 L32,20.3
              c0.1,0.3,0.2,0.5,0.2,0.7 s0.1,0.3,0.1,0.5 c0,0.5-0.2,0.9-0.5,1.2
              c-0.4,0.3-0.8,0.5-1.3,0.5 c-0.4,0-0.9-0.2-1.2-0.4 c-0.4-0.4-0.6-0.9-0.7-1.4
              l-0.5-1.5 L22.1,19.9 z M5.9,16.6 v4.1 c0,0.9-0.1,1.5-0.4,1.9 s-0.8,0.7-1.3,0.6
              c-0.5,0.1-1-0.2-1.4-0.6 c-0.3-0.4-0.4-1.1-0.4-1.9 V9.4 c0-0.9,0.1-1.5,0.4-2
              C3,7.1,3.5,6.8,4.1,6.9 c0.5,0,1,0.2,1.3,0.7 c0.3,0.4,0.4,1.1,0.4,2 v3.8 h6.7 v-4
              c0-0.9,0.2-1.5,0.5-2 c0.7-0.8,2-0.9,2.8-0.1 l0.1,0.1 c0.3,0.4,0.4,1.1,0.4,1.9
              v11.2 c0,0.9-0.2,1.5-0.5,1.9 c-0.7,0.8-1.9,0.9-2.7,0.2 c-0.1-0.1-0.2-0.1-0.2-0.2
              c-0.3-0.4-0.5-1.1-0.5-1.9 v-4.1 H5.9 V16.6 z
            "
          />
        </svg>
      </div>
      <button id="menu-toggle" aria-label="Menu">☰</button>
      <nav id="nav-menu">
        <div class="nav-links">
          <a
            id="home-link"
            href="<?= htmlspecialchars($BASE) ?>/"
            hx-get="<?= htmlspecialchars($BASE) ?>/"
            hx-push-url="true"
            hx-target="#content"
            hx-select="#content"
            hx-swap="outerHTML"
          >
            Domů
          </a>
          <a
            id="configurator-link"
            href="<?= htmlspecialchars($BASE) ?>/konfigurator"
            hx-get="<?= htmlspecialchars($BASE) ?>/konfigurator"
            hx-push-url="true"
            hx-target="#content"
            hx-select="#content"
            hx-swap="outerHTML"
            class="hidden"
          >
            Konfigurátor
          </a>
          <a
            id="users-link"
            href="<?= htmlspecialchars($BASE) ?>/users"
            hx-get="<?= htmlspecialchars($BASE) ?>/users"
            hx-push-url="true"
            hx-target="#content"
            hx-select="#content"
            hx-swap="outerHTML"
            class="hidden"
          >
            Uživatelé
          </a>
          <?php
            $__editor_allowed = in_array($role, ['admin', 'superadmin'], true);
            ?>
          <a
            id="editor-link"
            data-active-root="editor"
            href="<?= htmlspecialchars($BASE) ?>/editor/definitions"
            hx-get="<?= htmlspecialchars($BASE) ?>/editor/definitions"
            hx-push-url="true"
            hx-target="#content"
            hx-select="#content"
            hx-swap="outerHTML"
            <?= $__editor_allowed ? '' : ' class="hidden"' ?>
          >
            Editor
          </a>
          <a
            id="about-link"
            href="<?= htmlspecialchars($BASE) ?>/about"
            hx-get="<?= htmlspecialchars($BASE) ?>/about"
            hx-push-url="true"
            hx-target="#content"
            hx-select="#content"
            hx-swap="outerHTML"
          >
            O aplikaci
          </a>
          <a
            id="demo-link"
            href="<?= htmlspecialchars($BASE) ?>/demo"
            hx-get="<?= htmlspecialchars($BASE) ?>/demo"
            hx-push-url="true"
            hx-target="#content"
            hx-select="#content"
            hx-swap="outerHTML"
          >
            Demo
          </a>
        </div>
        <div class="nav-actions">
          <div class="nav-actions-icon" id="nav-actions-icon">
            <svg
              width="24px"
              height="24px"
              viewBox="0 0 24 24"
              version="1.1"
              xmlns="http://www.w3.org/2000/svg"
              xmlns:xlink="http://www.w3.org/1999/xlink"
              xmlns:sketch="http://www.bohemiancoding.com/sketch/ns"
            >
              <g
                id="out"
                stroke="none"
                stroke-width="1"
                fill="none"
                fill-rule="evenodd"
                sketch:type="MSPage"
              >
                <path
                  d="M18.1125649,13.0304195 C18.1454626,12.7672379 18.1701359,12.5040563 18.1701359,12.2244258
                  C18.1701359,11.9447953 18.1454626,11.6816137 18.1125649,11.4184321 L19.8479188,10.0614018
                  C20.0041828,9.93803541 20.045305,9.71597592 19.9466119,9.53503855 L18.3017267,6.68938723
                  C18.2030336,6.50844986 17.9809741,6.44265446 17.8000367,6.50844986 L15.7521547,7.33089244
                  C15.3244846,7.00191541 14.8639167,6.73050936 14.3622268,6.52489871 L14.0496986,4.34542588
                  C14.0250253,4.14803966 13.8523124,4 13.6467017,4 L10.3569314,4 C10.1513208,4 9.97860782,4.14803966
                  9.95393455,4.34542588 L9.64140637,6.52489871 C9.13971639,6.73050936 8.67914855,7.01013984
                  8.25147841,7.33089244 L6.20359639,6.50844986 C6.0144346,6.43443003 5.80059953,6.50844986
                  5.70190642,6.68938723 L4.05702126,9.53503855 C3.95010373,9.71597592 3.99945028,9.93803541
                  4.15571437,10.0614018 L5.89106821,11.4184321 C5.85817051,11.6816137 5.83349723,11.9530197
                  5.83349723,12.2244258 C5.83349723,12.4958318 5.85817051,12.7672379 5.89106821,13.0304195
                  L4.15571437,14.3874498 C3.99945028,14.5108161 3.95832815,14.7328756 4.05702126,14.913813
                  L5.70190642,17.7594643 C5.80059953,17.9404017 6.02265902,18.0061971 6.20359639,17.9404017
                  L8.25147841,17.1179591 C8.67914855,17.4469361 9.13971639,17.7183422 9.64140637,17.9239528
                  L9.95393455,20.1034257 C9.97860782,20.3008119 10.1513208,20.4488516 10.3569314,20.4488516
                  L13.6467017,20.4488516 C13.8523124,20.4488516 14.0250253,20.3008119 14.0496986,20.1034257
                  L14.3622268,17.9239528 C14.8639167,17.7183422 15.3244846,17.4387117 15.7521547,17.1179591
                  L17.8000367,17.9404017 C17.9891985,18.0144215 18.2030336,17.9404017 18.3017267,17.7594643
                  L19.9466119,14.913813 C20.045305,14.7328756 20.0041828,14.5108161 19.8479188,14.3874498
                  L18.1125649,13.0304195 L18.1125649,13.0304195 L18.1125649,13.0304195 Z
                  M12.0018166,15.1029748 C10.4145024,15.1029748 9.12326754,13.81174 9.12326754,12.2244258
                  C9.12326754,10.6371116 10.4145024,9.34587676 12.0018166,9.34587676 C13.5891307,9.34587676
                  14.8803656,10.6371116 14.8803656,12.2244258 C14.8803656,13.81174 13.5891307,15.1029748
                  12.0018166,15.1029748 L12.0018166,15.1029748 L12.0018166,15.1029748 Z"
                  id="path"
                  fill="#000000"
                  sketch:type="MSShapeGroup"
                >
                </path>
              </g>
            </svg>
          </div>
          <div class="nav-actions-panel hidden" id="nav-actions-panel">
            <span id="username-right">
              <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>
            </span>
            <a
              id="login-link"
              href="<?= htmlspecialchars($BASE) ?>/login"
              hx-get="<?= htmlspecialchars($BASE) ?>/login"
              hx-push-url="true"
              hx-target="#content"
              hx-select="#content"
              hx-swap="outerHTML"
            >
              Přihlásit se
            </a>
            <a
              id="register-link"
              href="<?= htmlspecialchars($BASE) ?>/register"
              hx-get="<?= htmlspecialchars($BASE) ?>/register"
              hx-push-url="true"
              hx-target="#content"
              hx-select="#content"
              hx-swap="outerHTML"
            >
              Registrovat se
            </a>
            <button id="logout-btn" class="hidden">Odhlásit se</button>
            <button id="theme-toggle">Přepnout motiv</button>
          </div>
        </div>
      </nav>
      </header>
      <main id="content">
      <?php
        if ($view !== null && is_file(__DIR__ . "/{$view}.php")) {
            require __DIR__ . "/{$view}.php";
        } else {
            ?><h1>PWA Template</h1><?php
        }
        ?>
    </main>
    <div class="app-version-badge" role="note">
      <span class="app-version-dot" aria-hidden="true"></span>
      <span class="app-version-text">
        Verze <strong>v<?= htmlspecialchars(APP_VERSION, ENT_QUOTES, 'UTF-8') ?></strong>
      </span>
    </div>
      <script src="<?= htmlspecialchars($BASE) ?>/public/vendor/htmx-2.0.7.min.js" defer></script>
      <?php if ($isDevEnv) : ?>
        <script
          type="module"
          src="http://localhost:5173/@vite/client"
        ></script>
        <script
          type="module"
          src="http://localhost:5173/src/main.ts"
        ></script>
        <link
          rel="stylesheet"
          href="http://localhost:5173/src/styles/layout.css"
        >
        <link
          rel="preload"
          as="style"
          href="http://localhost:5173/src/styles/fonts.css"
          onload="this.rel='stylesheet'"
        >
        <noscript>
          <link
            rel="stylesheet"
            href="http://localhost:5173/src/styles/fonts.css"
          >
        </noscript>
            <?php foreach ($resolvedViewStyles as $styleId => $href) : ?>
        <link
          rel="stylesheet"
          id="<?= htmlspecialchars($styleId) ?>"
          href="<?= htmlspecialchars($href) ?>"
        >
            <?php endforeach; ?>
      <?php else : ?>
          <?php if (!empty($main['file'])) : ?>
          <script
            type="module"
            src="<?= htmlspecialchars($BASE) ?>/public/assets/<?= htmlspecialchars($main['file']) ?>"
          ></script>
          <?php endif; ?>
      <?php endif; ?>

    <?php
      // Resolve service worker asset and scope based on build hash
      $swBase = $BASE;
      $swBasePath = ($swBase === '') ? '' : $swBase;
      $swScopePath = ($swBasePath === '') ? '/' : $swBasePath . '/';
      $swPublicPath = $swBasePath . '/sw.js';
      $swScopeJson = json_encode($swScopePath, JSON_UNESCAPED_SLASHES);
      $swPathJson = json_encode($swPublicPath, JSON_UNESCAPED_SLASHES);
      $swKillPaths = [$swBasePath . '/sw.js', $swBasePath . '/sw-'];
      $swKillJson = json_encode($swKillPaths, JSON_UNESCAPED_SLASHES);
    ?>
    <?php if (!defined('SW_ENABLED') || SW_ENABLED) : ?>
    <script>
      if ('serviceWorker' in navigator) {
        const registerSw = () => {
          navigator.serviceWorker
            .register(<?= $swPathJson ?>, { scope: <?= $swScopeJson ?> })
            .catch(() => {});
        };

        const idle = window.requestIdleCallback || ((cb) => setTimeout(cb, 0));
        idle(registerSw);
      }
    </script>
    <?php else : ?>
    <script>
      if ('serviceWorker' in navigator) {
        const baseScope = <?= $swScopeJson ?>;
        const killUrls = <?= $swKillJson ?>;
        const idle = window.requestIdleCallback || ((cb) => setTimeout(cb, 0));

        idle(async () => {
          const scopeHref = new URL(baseScope, location.origin).href;
          try {
            const unregisterTargets = [];

            if (navigator.serviceWorker.getRegistrations) {
              const registrations = await navigator.serviceWorker.getRegistrations();
              for (const registration of registrations) {
                if (registration.scope && registration.scope.startsWith(scopeHref)) {
                  unregisterTargets.push(registration);
                }
              }
            } else if (navigator.serviceWorker.getRegistration) {
              const registration = await navigator.serviceWorker.getRegistration(baseScope);
              if (registration) {
                unregisterTargets.push(registration);
              }
            }

            await Promise.all(
              unregisterTargets.map((registration) => registration.unregister())
            );
          } catch (err) {
            console.warn('Service worker disable: unregister failed', err);
          }

          if ('caches' in window) {
            try {
              const cacheNames = await caches.keys();
              await Promise.all(cacheNames.map((cacheName) => caches.delete(cacheName)));
            } catch (err) {
              console.warn('Service worker disable: cache cleanup failed', err);
            }
          }

          for (const url of killUrls) {
            try {
              await fetch(url, { cache: 'reload' });
            } catch (_) {
              // no-op
            }
          }
        });
      }
    </script>
    <?php endif; ?>
    <?php if (!$prettyUrlsEnabled) : ?>
    <script>
      // Fallback: rewrite in-app links to query-string routing when pretty URLs are blocked by parent .htaccess
      (function () {
        const base = document.documentElement.getAttribute('data-base') || '';

        const toQuery = (input) => {
          try {
            const url = new URL(input, location.origin);
            let path = url.pathname;

            if (base && path.startsWith(base)) {
              path = path.slice(base.length);
            }

            path = path.replace(/^\/+/, '');

            if (!path) {
              return `${base}/`;
            }

            const target = `${base}/?r=${encodeURIComponent(path)}`;
            const suffix = url.search
              ? `${target.includes('?') ? '&' : '?'}${url.search.replace(/^\?/, '')}`
              : '';

            return `${target}${suffix}`;
          } catch (error) {
            return input;
          }
        };

        document.querySelectorAll('a[href]').forEach((anchor) => {
          const href = anchor.getAttribute('href');

          if (!href || /^(?:https?:|mailto:|#)/.test(href)) {
            return;
          }

          anchor.setAttribute('href', toQuery(href));
        });

        document.querySelectorAll('[hx-get]').forEach((element) => {
          const value = element.getAttribute('hx-get');

          if (value) {
            element.setAttribute('hx-get', toQuery(value));
          }
        });
      })();
    </script>
    <?php endif; ?>
  </body>
</html>
