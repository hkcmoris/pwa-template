<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/Administration/Repository.php';

use Administration\Repository as AdministrationRepository;

// Resolve current user for SSR gating and header state
$currentUser = app_get_current_user();
$role = $currentUser['role'] ?? 'guest';
$email = $currentUser['email'] ?? null;
$isAuthenticated = is_string($email) && $email !== '';
$username = isset($username) && is_string($username) && $username !== ''
    ? $username
    : (is_string($email) && $email !== '' ? $email : 'Návštěvník');
$title = isset($title) && is_string($title) && $title !== '' ? $title : 'HAGEMANN konfigurátor';
$route = isset($route) && is_string($route) && $route !== '' ? $route : 'home';
$theme  = $_COOKIE['theme'] ?? 'light';
if ($theme !== 'dark' && $theme !== 'light') {
    $theme = 'light';
}
$logoRepository = new AdministrationRepository();
$logoSettings = $logoRepository->readLogoSettings();
$logoW = $logoSettings['width'];
$logoH = $logoSettings['height'];
$logoUrl = $logoSettings['path'];
if (!empty($logoSettings['updated_at'])) {
    $logoUrl .= '?v=' . rawurlencode((string)$logoSettings['updated_at']);
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
$viewPath = isset($viewPath) && is_string($viewPath) && $viewPath !== ''
    ? $viewPath
    : __DIR__ . '/404.php';
$resolvedViewStyles = [];
foreach ($viewStyles as $styleId => $entry) {
    $href = vite_asset_href($entry, $isDevEnv, $BASE);
    if ($href !== null) {
        $resolvedViewStyles[(string) $styleId] = $href;
    }
}
$cspNonce = isset($GLOBALS['csp_nonce']) && is_string($GLOBALS['csp_nonce'])
    ? $GLOBALS['csp_nonce']
    : '';
$cspNonceAttr = $cspNonce !== ''
    ? ' nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') . '"'
    : '';
?>
<!doctype html>
<html
  lang="cs"
  data-theme="<?= htmlspecialchars($theme) ?>"
  data-base="<?= htmlspecialchars($BASE) ?>"
  data-pretty="<?= $prettyUrlsEnabled ? '1' : '0' ?>"
  data-csrf="<?= htmlspecialchars($csrfToken) ?>"
  data-authenticated="<?= $isAuthenticated ? '1' : '0' ?>"
  data-auth-email="<?= htmlspecialchars((string) ($email ?? ''), ENT_QUOTES, 'UTF-8') ?>"
  data-auth-role="<?= htmlspecialchars((string) $role, ENT_QUOTES, 'UTF-8') ?>"
  data-csp-nonce="<?= htmlspecialchars((string)($GLOBALS['csp_nonce'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
>
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($title) ?></title>
  <link rel="icon" href="<?= htmlspecialchars($BASE) ?>/favicon.ico">
  <meta
    name="description"
    content="<?= htmlspecialchars($description ?? 'HAGEMANN konfigurátor') ?>"
  />
  <?php
    $htmxConfig = [
      'includeIndicatorStyles' => false,
      'inlineStyleNonce' => $cspNonce,
    ];
    echo '<meta name="htmx-config" content="' .
      htmlspecialchars(json_encode($htmxConfig, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') .
    '">';
    ?>
  <?php if ($isDevEnv && $cspNonce !== '') : ?>
  <meta property="csp-nonce" content="<?= htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') ?>">
  <?php endif; ?>
  <?php if ($csrfToken !== '') : ?>
  <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
  <?php endif; ?>
  <link rel="manifest" href="<?= htmlspecialchars($BASE) ?>/public/manifest.webmanifest">
  <style<?= $cspNonceAttr ?>>
    :root {
      --bg: #fff;
      --fg: #111;
      --primary: #2563eb;
      --primary-contrast: #fff;
      --primary-hover: color-mix(in srgb, var(--primary) 85%, black);
      --danger: #dc2626;
      --success: #339230;
      --fg-muted: #4b5563;
      --home-bg: none;
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

    header#main-header {
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

    body[data-route="konfigurator"] main {
      max-width: initial;
    }
  </style>
  <?php
      $css_link_async = static function (string $href, ?string $id = null): void {
          $idAttr = $id ? ' id="' . htmlspecialchars($id, ENT_QUOTES) . '"' : '';
          $safeHref = htmlspecialchars($href, ENT_QUOTES);
          echo '<link rel="preload" as="style" href="' . $safeHref . '">' . "\n";
          echo '<link rel="stylesheet"' . $idAttr .
            ' href="' . $safeHref . '" media="print" data-async-style="1">' . "\n";
          echo '<noscript><link rel="stylesheet"' . $idAttr . ' href="' . $safeHref . '"></noscript>' . "\n";
      };

        if (!$isDevEnv) :
            $assetBase = rtrim((string)$BASE, '/') . '/public/assets/';
            $main = vite_asset('src/main.ts');
            $layoutCss = vite_asset('src/styles/layout.css');
            $fontsCss = vite_asset('src/styles/fonts.css');
            // <link rel="stylesheet" href="http://localhost:5173/src/styles/fonts.css">
            if ($layoutCss && !empty($layoutCss['file'])) : ?>
                <?php
                $css_link_async($assetBase . $layoutCss['file']);
                ?>
                <?php
            endif;
            if ($main && !empty($main['css'])) :
                foreach ($main['css'] as $css) : ?>
                    <?php
                    $css_link_async($assetBase . $css);
                    ?>
                    <?php
                endforeach;
            endif;
            if ($fontsCss && !empty($fontsCss['file'])) : ?>
          <link
            rel="stylesheet"
            href="<?= htmlspecialchars($BASE) ?>/public/assets/<?= htmlspecialchars($fontsCss['file']) ?>"
          >
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

  <script<?= $cspNonceAttr ?>>
    document.querySelectorAll('link[data-async-style="1"]').forEach((link) => {
      link.addEventListener('load', () => {
        link.media = 'all';
      }, { once: true });
    });
  </script>

</head>
  <body data-route="<?= htmlspecialchars($view ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <header id="main-header">
      <div class="logo">
        <img
          src="<?= htmlspecialchars($BASE) ?>/<?= $logoUrl ?>"
          alt="Logo"
          width="<?= (int)$logoW ?>"
          height="<?= (int)$logoH ?>"
          decoding="async"
          data-app-logo
        >
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
            hx-swap="innerHTML"
          >
            Domů
          </a>
          <?php
            $__editor_allowed = in_array($role, ['admin', 'superadmin'], true);
            ?>
          <a
            id="configurator-link"
            href="<?= htmlspecialchars($BASE) ?>/konfigurator-manager"
            hx-get="<?= htmlspecialchars($BASE) ?>/konfigurator-manager"
            hx-push-url="true"
            hx-target="#content"
            hx-swap="innerHTML"
            class="hidden"
          >
            Konfigurátor
          </a>
          <a
            id="admin-link"
            href="<?= htmlspecialchars($BASE) ?>/admin"
            hx-get="<?= htmlspecialchars($BASE) ?>/admin"
            hx-push-url="true"
            hx-target="#content"
            hx-swap="innerHTML"
            <?= $__editor_allowed ? '' : ' class="hidden"' ?>
          >
            Administrace
          </a>
          <a
            id="users-link"
            href="<?= htmlspecialchars($BASE) ?>/users"
            hx-get="<?= htmlspecialchars($BASE) ?>/users"
            hx-push-url="true"
            hx-target="#content"
            hx-swap="innerHTML"
            <?= $__editor_allowed ? '' : ' class="hidden"' ?>
          >
            Uživatelé
          </a>
          <a
            id="editor-link"
            data-active-root="editor"
            href="<?= htmlspecialchars($BASE) ?>/editor/definitions"
            hx-get="<?= htmlspecialchars($BASE) ?>/editor/definitions"
            hx-push-url="true"
            hx-target="#content"
            hx-swap="innerHTML"
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
            hx-swap="innerHTML"
          >
            O aplikaci
          </a>
        </div>
        <div class="nav-actions">
          <div class="nav-actions-icon" id="nav-actions-icon">
            <svg
              width="24px"
              height="24px"
              viewBox="0 0 24 24"
            >
              <g
                id="out"
                stroke="none"
                stroke-width="1"
                fill="none"
                fill-rule="evenodd"
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
              class="<?= $isAuthenticated ? 'hidden' : '' ?>"
              href="<?= htmlspecialchars($BASE) ?>/login"
              hx-get="<?= htmlspecialchars($BASE) ?>/login"
              hx-push-url="true"
              hx-target="#content"
              hx-swap="innerHTML"
            >
              Přihlásit se
            </a>
            <a
              id="register-link"
              class="<?= $isAuthenticated ? 'hidden' : '' ?>"
              href="<?= htmlspecialchars($BASE) ?>/register"
              hx-get="<?= htmlspecialchars($BASE) ?>/register"
              hx-push-url="true"
              hx-target="#content"
              hx-swap="innerHTML"
            >
              Registrovat se
            </a>
            <button id="logout-btn" class="<?= $isAuthenticated ? '' : 'hidden' ?>">Odhlásit se</button>
            <button id="theme-toggle">Přepnout motiv</button>
          </div>
        </div>
      </nav>
      </header>
      <main id="content">
        <?php require $viewPath; ?>
      </main>
      <div class="app-version-badge" role="note">
        <span class="app-version-dot" aria-hidden="true"></span>
        <span class="app-version-text">
          Verze <strong>v<?= htmlspecialchars(APP_VERSION, ENT_QUOTES, 'UTF-8') ?></strong>
        </span>
      </div>
      <script<?= $cspNonceAttr ?>>
        window.htmx = window.htmx || {};
        window.htmx.config = window.htmx.config || {};
        window.htmx.config.includeIndicatorStyles = false;
        window.htmx.config.inlineStyleNonce = "<?= htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') ?>";
      </script>
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
      $swKillPaths = [$swBasePath . '/sw.js'];
      $swKillJson = json_encode($swKillPaths, JSON_UNESCAPED_SLASHES);
    ?>
    <?php
      $lhci = strtolower(trim((string) getenv('LHCI'))) === '1';
    ?>
    <?php if (!$lhci && (!defined('SW_ENABLED') || SW_ENABLED)) : ?>
    <script<?= $cspNonceAttr ?>>
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
    <?php elseif (!$lhci) : ?>
    <script<?= $cspNonceAttr ?>>
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

    <script<?= $cspNonceAttr ?>>
      document.querySelectorAll('img[data-fallback-src]').forEach((image) => {
        image.addEventListener('error', () => {
          const fallbackSrc = image.getAttribute('data-fallback-src');

          if (!fallbackSrc || image.getAttribute('src') === fallbackSrc) {
            return;
          }

          image.setAttribute('src', fallbackSrc);
        }, { once: true });
      });
    </script>

    <?php if (!$prettyUrlsEnabled) : ?>
    <script<?= $cspNonceAttr ?>>
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
