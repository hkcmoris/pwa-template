<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../lib/auth.php';
// Resolve current user for SSR gating and header state
$currentUser = app_get_current_user();
$role = $currentUser['role'] ?? 'guest';
$username = $username ?? ($currentUser['email'] ?? 'Návštěvník');
$title  = $title  ?? 'HAGEMANN konfigurátor';
$route  = $route  ?? 'home';
$theme  = $_COOKIE['theme'] ?? 'light';
if ($theme !== 'dark' && $theme !== 'light') $theme = 'light';

// Normalized base path for subfolder deployments ('' or '/subdir')
$BASE = rtrim((defined('BASE_PATH') ? BASE_PATH : ''), '/');

function vite_asset(string $entry) {
  static $m = null;
  // Vite manifest location under outDir
  $path = __DIR__ . '/../public/assets/.vite/manifest.json';
  if ($m === null && is_file($path)) $m = json_decode(file_get_contents($path), true);
  return $m[$entry] ?? null;
}
?>
<!doctype html>
  <html lang="cs" data-theme="<?= htmlspecialchars($theme) ?>" data-base="<?= htmlspecialchars($BASE) ?>" data-pretty="<?= (defined('PRETTY_URLS') && PRETTY_URLS) ? '1' : '0' ?>">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars($title) ?></title>
    <meta name="description" content="<?= htmlspecialchars($description ?? 'HAGEMANN konfigurátor – rychlá PWA s PHP SSR.') ?>" />
    <style>
      :root{--bg:#fff;--fg:#111;--primary:#3b82f6}
      *,*::before,*::after{box-sizing:border-box}
      [data-theme='dark']{--bg:#212529;--fg:#f5f5f5}
      body{margin:0;background:var(--bg);color:var(--fg);font-family:system-ui,sans-serif;line-height:1.5}
      a{color:var(--primary);text-decoration:none}
      a:hover{text-decoration:underline}
      /* Navbar link styling */
      header nav a{ 
        font-family:"Montserrat",system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,"Noto Sans","Helvetica Neue",Arial,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol",sans-serif;
        font-size:13px;
        letter-spacing:.02em;
        font-weight:700;
        color:#6b7280; /* gray-500 on light */
        text-decoration:none;
      }
      /* Editor subnav links (match header nav style) */
      .subnav a{
        font-family:"Montserrat",system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,"Noto Sans","Helvetica Neue",Arial,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol",sans-serif;
        font-size:13px;
        letter-spacing:.02em;
        font-weight:700;
        color:#6b7280;
        text-decoration:none;
      }
      .subnav a:hover{ text-decoration:none; color:#111; }
      [data-theme='dark'] .subnav a:not(.active){ color:#cbd5e1; }
      [data-theme='dark'] .subnav a:not(.active):hover{ color:#fff; }
      header nav a:hover{ text-decoration:none; color:#111; }
      [data-theme='dark'] header nav a:not(.active){ color:#cbd5e1; /* slate-300 on dark */ }
      [data-theme='dark'] header nav a:not(.active):hover{ color:#fff; }
      nav a.active{font-weight:900;color:var(--primary)}
      nav a.active:hover{color:var(--primary)}
      header{display:flex;align-items:center;gap:.75rem;justify-content:flex-start;padding:.5rem 1rem;background:var(--bg);border-bottom:1px solid var(--fg);position:fixed;top:0;left:0;right:0;box-shadow:0 2px 4px rgb(0 0 0 / .05)}
      .logo{font-weight:bold;line-height:0}
      .logo img,.logo svg{display:block}
      nav{display:flex;gap:.5rem;align-items:center;flex:1;min-width:0}
      .nav-links{display:flex;gap:.5rem;align-items:center;flex-wrap:nowrap}
      .nav-actions{display:flex;gap:.5rem;align-items:center;margin-left:auto}
      #menu-toggle{display:none;background:none;border:1px solid var(--fg);color:var(--fg);font-size:1.5rem;border-radius:.25rem;margin-left:auto}
      @media (max-width:600px){
        nav{display:none;flex-direction:column;position:absolute;top:100%;left:0;right:0;background:var(--bg);border-top:1px solid var(--fg);padding:.5rem 1rem;gap:.25rem}
        .nav-links{flex-direction:column;align-items:stretch;gap:.25rem}
        .nav-actions{flex-direction:column;align-items:stretch;gap:.25rem;margin-left:0}
        nav.open{display:flex}
        #menu-toggle{display:block}
      }
      button{cursor:pointer;background:var(--primary);color:#fff;border:none;border-radius:.25rem;padding:.5rem .75rem;transition:background .2s}
      button:hover{background:#2563eb}
      table{width:100%;border-collapse:collapse}
      th{text-align:left}
      main{padding:1rem;padding-top:3.5rem;min-height:100dvb;max-width:800px;margin:0 auto}
      .hidden{display:none}
      .auth-form{display:flex;flex-direction:column;align-items:center;gap:0.5rem;max-width:300px;margin:0 auto}
      .auth-form__field{display:flex;flex-direction:column;width:100%}
      .auth-form__input{width:100%;padding:.5rem;border:1px solid var(--fg);border-radius:.25rem}
      .auth-form button{width:100%;font-size:1.1rem;margin-top:1rem}
    </style>
    <?php if (APP_ENV !== 'dev'):
      $main = vite_asset('src/main.ts');
      $fontsCss = vite_asset('src/styles/fonts.css');
      if ($main && !empty($main['css'])):
        foreach ($main['css'] as $css): ?>
          <link rel="stylesheet" href="<?= htmlspecialchars($BASE) ?>/public/assets/<?= htmlspecialchars($css) ?>"><?php
        endforeach;
      endif;
      if ($fontsCss && !empty($fontsCss['file'])): ?>
        <link rel="preload" as="style" href="<?= htmlspecialchars($BASE) ?>/public/assets/<?= htmlspecialchars($fontsCss['file']) ?>" onload="this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="<?= htmlspecialchars($BASE) ?>/public/assets/<?= htmlspecialchars($fontsCss['file']) ?>"></noscript>
      <?php endif;
    endif; ?>
  </head>
    <body>
    <header>
      <div class="logo">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 130 29.8" width="130" height="29.8" role="img" aria-hidden="true">
          <path fill="currentColor" d="M117,20.6c0,0.9-0.2,1.5-0.5,2c-0.7,0.8-1.8,0.9-2.6,0.2c-0.1-0.1-0.2-0.1-0.2-0.2c-0.3-0.4-0.5-1.1-0.5-2V9
            c0-0.8,0.2-1.5,0.4-1.9c0.3-0.4,0.8-0.7,1.3-0.6c0.4,0,0.7,0.1,1.1,0.3c0.4,0.3,0.7,0.6,1,0.9l7,9.2V9.1c0-0.8,0.1-1.5,0.4-1.9
            c0.6-0.7,1.7-0.8,2.5-0.2c0.1,0.1,0.1,0.1,0.2,0.2c0.3,0.4,0.4,1.1,0.4,1.9V21c0,0.8-0.1,1.4-0.4,1.8s-0.8,0.6-1.3,0.6
            c-0.4,0-0.8-0.1-1.1-0.3c-0.4-0.3-0.7-0.6-1-1l-6.8-8.9v7.6 M99.9,20.6c0,0.9-0.2,1.6-0.5,2.1c-0.7,0.8-1.9,0.9-2.7,0.2
            c-0.1-0.1-0.2-0.1-0.2-0.2c-0.3-0.5-0.5-1.1-0.5-2.1V9.1c0-0.8,0.2-1.5,0.4-1.9c0.3-0.4,0.8-0.6,1.3-0.6c0.4,0,0.7,0.1,1,0.3
            c0.4,0.3,0.7,0.6,1,0.9l6.9,9V9.2c0-0.9,0.2-1.6,0.5-2.1c0.7-0.9,2.1-0.9,2.8-0.1l0.1,0.1c0.3,0.5,0.4,1.1,0.4,2V21
            c0,0.8-0.1,1.4-0.4,1.7c-0.3,0.4-0.8,0.6-1.3,0.6c-0.4,0-0.8-0.1-1.1-0.3c-0.4-0.3-0.7-0.6-1-1l-6.8-8.8L99.9,20.6L99.9,20.6z
            M85.5,16.6h3.9l-1.9-6L85.5,16.6z M84.5,19.9l-0.6,1.6c-0.1,0.5-0.4,0.9-0.7,1.2c-0.3,0.3-0.7,0.4-1.1,0.4c-0.5,0-0.9-0.2-1.3-0.5
            c-0.3-0.3-0.5-0.8-0.5-1.3c0-0.2,0-0.4,0.1-0.6c0-0.2,0.1-0.3,0.1-0.5l4.3-11.5C85,8.2,85.4,7.6,85.9,7c0.5-0.4,1.1-0.6,1.7-0.6
            s1.2,0.2,1.6,0.6c0.5,0.4,0.9,1,1.1,1.6l4.1,11.7c0.1,0.2,0.1,0.4,0.2,0.7c0.1,0.2,0.1,0.3,0.1,0.5c0,0.5-0.2,0.9-0.5,1.2
            c-0.4,0.3-0.8,0.5-1.3,0.5c-0.4,0-0.9-0.2-1.2-0.4c-0.4-0.4-0.6-0.9-0.7-1.4l-0.5-1.5C90.5,19.9,84.5,19.9,84.5,19.9z M61,20.2
            l2-11.1c0.2-0.9,0.4-1.6,0.8-2s1-0.6,1.7-0.6c0.6,0,1.2,0.2,1.7,0.5c0.5,0.4,0.8,1,0.9,1.6l2.5,9.7l2.3-9.8c0.1-0.6,0.4-1.2,0.9-1.6
            s1.1-0.6,1.6-0.5c0.8,0,1.4,0.2,1.8,0.6s0.7,1.1,0.9,2.1l1.8,11.2c0,0.2,0.1,0.3,0.1,0.5s0,0.3,0,0.4c0,0.5-0.2,1-0.5,1.4
            c-0.4,0.4-0.9,0.5-1.4,0.5s-0.9-0.2-1.2-0.5c-0.4-0.5-0.6-1-0.6-1.6l-1.2-9.7c-0.9,3.9-1.6,6.6-2,8.2s-0.8,2.6-1,3
            c-0.1,0.2-0.3,0.4-0.6,0.5c-0.3,0.1-0.6,0.2-1,0.2c-0.5,0-1-0.1-1.4-0.4c-0.4-0.4-0.6-0.9-0.7-1.4l-2.4-10l-1.3,9.5
            c-0.1,0.9-0.3,1.5-0.6,1.8c-0.3,0.4-0.8,0.6-1.3,0.6s-1-0.2-1.3-0.5c-0.4-0.4-0.5-0.9-0.5-1.4c0-0.2,0-0.3,0-0.5
            C60.9,20.7,61,20.5,61,20.2L61,20.2z M57.2,6.6c0.9,0,1.6,0.2,2.1,0.5S60,7.9,60,8.5c0,0.5-0.2,1-0.7,1.3s-1.1,0.4-2.1,0.4H54v3.1
            h2.6c0.9,0,1.5,0.2,2,0.4c0.4,0.3,0.7,0.8,0.6,1.3c0,0.5-0.2,1-0.6,1.3c-0.4,0.3-1.1,0.4-2,0.4H54v3.1h3.3c0.9,0,1.6,0.1,2.1,0.4
            s0.7,0.8,0.7,1.3s-0.2,1-0.7,1.3S58.2,23,57.3,23h-4.5c-1,0-1.7-0.2-2.1-0.5c-0.4-0.3-0.6-0.9-0.6-1.8V9.2c0-1,0.2-1.7,0.6-2.1
            c0.4-0.4,1.1-0.6,2.1-0.6L57.2,6.6z M44.2,16.6h-1.3c-0.9,0-1.5-0.1-1.9-0.4c-0.4-0.3-0.6-0.8-0.6-1.3s0.2-1,0.6-1.3
            c0.4-0.3,1-0.4,1.9-0.4h2.7c0.9,0,1.5,0.2,1.9,0.6c0.4,0.4,0.6,1,0.6,1.8c0,2.2-0.7,4-2,5.4s-3.1,2.1-5.3,2.1s-4-0.8-5.4-2.3
            s-2.1-3.5-2.1-6s0.7-4.5,2.2-6.1c1.4-1.5,3.4-2.3,5.8-2.3c1.6,0,3,0.3,4.1,1c1,0.8,1.6,1.5,1.6,2.4c0,0.4-0.1,0.8-0.4,1.2
            c-0.3,0.3-0.6,0.5-1.1,0.5c-0.3,0-0.9-0.2-1.8-0.8c-0.8-0.5-1.7-0.7-2.6-0.8c-1.1,0-2.2,0.4-2.9,1.3c-0.7,0.9-1.1,2-1.1,3.5
            s0.3,2.7,1.1,3.6c0.7,0.9,1.7,1.4,2.9,1.3c0.8,0,1.7-0.3,2.3-0.9C43.9,18.3,44.3,17.4,44.2,16.6L44.2,16.6z M23.1,16.6H27l-1.9-6
            L23.1,16.6z M22.1,19.9l-0.6,1.6c-0.1,0.5-0.4,0.9-0.7,1.2c-0.3,0.3-0.7,0.4-1.1,0.4c-0.5,0-0.9-0.2-1.3-0.5
            c-0.3-0.3-0.5-0.8-0.5-1.3c0-0.2,0-0.4,0.1-0.6c0-0.2,0.1-0.3,0.1-0.5l4.2-11.5C22.6,8.2,23,7.6,23.5,7c0.5-0.4,1.1-0.6,1.7-0.6
            s1.2,0.2,1.6,0.6c0.5,0.4,0.9,1,1.1,1.6L32,20.3c0.1,0.3,0.2,0.5,0.2,0.7s0.1,0.3,0.1,0.5c0,0.5-0.2,0.9-0.5,1.2
            c-0.4,0.3-0.8,0.5-1.3,0.5c-0.4,0-0.9-0.2-1.2-0.4c-0.4-0.4-0.6-0.9-0.7-1.4l-0.5-1.5L22.1,19.9z M5.9,16.6v4.1
            c0,0.9-0.1,1.5-0.4,1.9s-0.8,0.7-1.3,0.6c-0.5,0.1-1-0.2-1.4-0.6c-0.3-0.4-0.4-1.1-0.4-1.9V9.4c0-0.9,0.1-1.5,0.4-2
            C3,7.1,3.5,6.8,4.1,6.9c0.5,0,1,0.2,1.3,0.7c0.3,0.4,0.4,1.1,0.4,2v3.8h6.7v-4c0-0.9,0.2-1.5,0.5-2c0.7-0.8,2-0.9,2.8-0.1l0.1,0.1
            c0.3,0.4,0.4,1.1,0.4,1.9v11.2c0,0.9-0.2,1.5-0.5,1.9c-0.7,0.8-1.9,0.9-2.7,0.2c-0.1-0.1-0.2-0.1-0.2-0.2c-0.3-0.4-0.5-1.1-0.5-1.9
            v-4.1H5.9V16.6z"
          />
        </svg>
      </div>
      <button id="menu-toggle" aria-label="Menu">☰</button>
        <nav id="nav-menu">
          <div class="nav-links">
          <a id="home-link" href="<?= htmlspecialchars($BASE) ?>/" hx-get="<?= htmlspecialchars($BASE) ?>/" hx-push-url="true" hx-target="#content" hx-select="#content" hx-swap="outerHTML">Domů</a>
          <a id="configurator-link" href="<?= htmlspecialchars($BASE) ?>/konfigurator" hx-get="<?= htmlspecialchars($BASE) ?>/konfigurator" hx-push-url="true" hx-target="#content" hx-select="#content" hx-swap="outerHTML" class="hidden">Konfigurátor</a>
          <a id="users-link" href="<?= htmlspecialchars($BASE) ?>/users" hx-get="<?= htmlspecialchars($BASE) ?>/users" hx-push-url="true" hx-target="#content" hx-select="#content" hx-swap="outerHTML" class="hidden">Uživatelé</a>
          <?php if (in_array($role, ['admin','superadmin'], true)): ?>
          <a id="editor-link" href="<?= htmlspecialchars($BASE) ?>/editor" hx-get="<?= htmlspecialchars($BASE) ?>/editor" hx-push-url="true" hx-target="#content" hx-select="#content" hx-swap="outerHTML">Editor</a>
          <?php endif; ?>
          <a id="about-link" href="<?= htmlspecialchars($BASE) ?>/about" hx-get="<?= htmlspecialchars($BASE) ?>/about" hx-push-url="true" hx-target="#content" hx-select="#content" hx-swap="outerHTML">O aplikaci</a>
          <a id="demo-link" href="<?= htmlspecialchars($BASE) ?>/demo" hx-get="<?= htmlspecialchars($BASE) ?>/demo" hx-push-url="true" hx-target="#content" hx-select="#content" hx-swap="outerHTML">Demo</a>
          </div>
          <div class="nav-actions">
            <span id="username-right"><?= htmlspecialchars($username ?? 'N�v�t�vn�k') ?></span>
          <a id="login-link" href="<?= htmlspecialchars($BASE) ?>/login" hx-get="<?= htmlspecialchars($BASE) ?>/login" hx-push-url="true" hx-target="#content" hx-select="#content" hx-swap="outerHTML">Přihlásit se</a>
          <a id="register-link" href="<?= htmlspecialchars($BASE) ?>/register" hx-get="<?= htmlspecialchars($BASE) ?>/register" hx-push-url="true" hx-target="#content" hx-select="#content" hx-swap="outerHTML">Registrovat se</a>
          <button id="logout-btn" class="hidden">Odhlásit se</button>
          <button id="theme-toggle">Přepnout motiv</button>
          </div>
        </nav>
      </header>
      <main id="content">
      <?php
        if (!empty($view) && is_file(__DIR__ . "/{$view}.php")) {
          require __DIR__ . "/{$view}.php";
        } else {
          ?><h1>PWA Template</h1><?php
        }
      ?>
    </main>

      <script src="https://unpkg.com/htmx.org@1.9.10" defer></script>
      <?php if (APP_ENV === 'dev'): ?>
        <script type="module" src="http://localhost:5173/@vite/client"></script>
        <script type="module" src="http://localhost:5173/src/main.ts"></script>
        <link rel="preload" as="style" href="http://localhost:5173/src/styles/fonts.css" onload="this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="http://localhost:5173/src/styles/fonts.css"></noscript>
      <?php else: ?>
        <?php if (!empty($main['file'])): ?>
          <script type="module" src="<?= htmlspecialchars($BASE) ?>/public/assets/<?= htmlspecialchars($main['file']) ?>"></script>
        <?php endif; ?>
      <?php endif; ?>

    <script>
      requestIdleCallback?.(()=>navigator.serviceWorker?.register('<?= htmlspecialchars($BASE) ?>/sw.js', { scope: '<?= htmlspecialchars($BASE) ?>/' }));
    </script>
    <?php if (defined('PRETTY_URLS') && !PRETTY_URLS): ?>
    <script>
      // Fallback: rewrite in-app links to query-string routing when pretty URLs are blocked by parent .htaccess
      (function(){
        const base = document.documentElement.getAttribute('data-base') || '';
        function toQuery(u){
          try{
            const url = new URL(u, location.origin);
            let path = url.pathname;
            if (base && path.startsWith(base)) path = path.slice(base.length);
            path = path.replace(/^\/+/, '');
            if (!path) return base + '/';
            const qs = base + '/?r=' + encodeURIComponent(path);
            return qs + (url.search ? (qs.includes('?')? '&' : '?') + url.search.replace(/^\?/, '') : '');
          }catch(e){ return u; }
        }
        document.querySelectorAll('a[href]').forEach(a=>{
          const href = a.getAttribute('href');
          if (!href || /^https?:|^mailto:|^#/.test(href)) return;
          a.setAttribute('href', toQuery(href));
        });
        document.querySelectorAll('[hx-get]').forEach(el=>{
          const val = el.getAttribute('hx-get');
          if (val) el.setAttribute('hx-get', toQuery(val));
        });
      })();
    </script>
    <?php endif; ?>
  </body>
</html>





