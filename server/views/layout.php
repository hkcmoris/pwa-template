<?php
require_once __DIR__.'/../config/config.php';
$title  = $title  ?? 'PWA Template';
$route  = $route  ?? 'home';
$theme  = $_COOKIE['theme'] ?? 'light';

function vite_asset(string $entry) {
  static $m = null;
  $path = __DIR__ . '/../public/assets/manifest.json'; // adjust if needed
  if ($m === null && is_file($path)) $m = json_decode(file_get_contents($path), true);
  return $m[$entry] ?? null;
}
?>
<!doctype html>
  <html lang="en" data-theme="<?= htmlspecialchars($theme) ?>">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars($title) ?></title>
    <style>
      :root{--bg:#fff;--fg:#111}
      [data-theme='dark']{--bg:#111;--fg:#f5f5f5}
      body{margin:0;background:var(--bg);color:var(--fg);font-family:system-ui,sans-serif}
      header{display:flex;align-items:center;justify-content:space-between;padding:.5rem 1rem;background:var(--bg);border-bottom:1px solid var(--fg);position:fixed;width:calc(100dvw - 2rem);}
      .logo{font-weight:bold}
      nav{display:flex;gap:.5rem}
      #menu-toggle{display:none;background:none;border:none;font-size:1.5rem}
      @media (max-width:600px){
        nav{display:none;flex-direction:column;position:absolute;top:100%;left:0;right:0;background:var(--bg);border-top:1px solid var(--fg)}
        nav.open{display:flex}
        #menu-toggle{display:block}
      }
      button{cursor:pointer;background:none;border:1px solid var(--fg);color:var(--fg);padding:.25rem .5rem}
      main{padding:1rem;padding-top:2.5rem;min-height:calc(100dvb - 3.5rem);}
      .hidden{display:none}
      .auth-form{display:flex;flex-direction:column;align-items:center;gap:0.5rem;max-width:300px;margin:0 auto;}
      .auth-form__field{display:flex;flex-direction:column;width:100%;}
      .auth-form__input{width: 100%;}
    </style>
    <?php if (!APP_ENV === 'dev'):
      $main = vite_asset('src/main.ts');
      if ($main && !empty($main['css'])):
        foreach ($main['css'] as $css): ?>
          <link rel="stylesheet" href="/assets/<?= htmlspecialchars($css) ?>"><?php
        endforeach;
      endif;
    endif; ?>
  </head>
    <body>
    <header>
      <div class="logo">Logo</div>
      <button id="menu-toggle" aria-label="Menu">☰</button>
        <nav id="nav-menu">
          <span id="username"><?= htmlspecialchars($username ?? 'Guest') ?></span>
          <a id="login-link" href="/login" hx-get="/login" hx-push-url="true" hx-target="#content" hx-select="#content" hx-swap="outerHTML">Login</a>
          <a id="register-link" href="/register" hx-get="/register" hx-push-url="true" hx-target="#content" hx-select="#content" hx-swap="outerHTML">Register</a>
          <a id="users-link" href="/users" hx-get="/users" hx-push-url="true" hx-target="#content" hx-select="#content" hx-swap="outerHTML" class="hidden">Users</a>
          <a id="about-link" href="/about" hx-get="/about" hx-push-url="true" hx-target="#content" hx-select="#content" hx-swap="outerHTML" class="hidden">About</a>
          <button id="logout-btn" class="hidden">Logout</button>
          <button id="theme-toggle">Toggle Theme</button>
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

      <script src="https://unpkg.com/htmx.org@1.9.10"></script>
      <?php if (APP_ENV === 'dev'): ?>
        <script type="module" src="http://localhost:5173/@vite/client"></script>
        <script type="module" src="http://localhost:5173/src/main.ts"></script>
      <?php else: ?>
        <?php if (!empty($main['file'])): ?>
          <script type="module" src="/assets/<?= htmlspecialchars($main['file']) ?>"></script>
        <?php endif; ?>
      <?php endif; ?>

    <script>
      requestIdleCallback?.(()=>navigator.serviceWorker?.register('/sw.js'));
    </script>
  </body>
</html>
