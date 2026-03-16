<?php

$start = microtime(true);

// index.php
require_once __DIR__ . '/bootstrap.php';
log_message('bootstrap: ' . round((microtime(true) - $start) * 1000) . 'ms', 'INFO');

// Resolve route from query-string fallback or pretty URL path
$qsRoute = isset($_GET['r']) ? trim((string)$_GET['r'], '/') : '';

/** @var string $basePath */
$basePath = defined('BASE_PATH') ? (string) BASE_PATH : '';
if ($basePath === '/') {
    $basePath = '';
}

$prettyUrlsEnabled = defined('PRETTY_URLS') ? (bool) PRETTY_URLS : false;
if ($qsRoute === '' || $prettyUrlsEnabled) {
// Use path-based routing when pretty URLs are enabled (or no r= provided)
    $uriPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
    if ($basePath !== '') {
        $prefix = '#^' . preg_quote($basePath, '#') . '/?#';
        $uriPath = preg_replace($prefix, '/', $uriPath, 1);
    }
    $route = trim($uriPath, '/');
} else {
// Forced query routing
    $route = $qsRoute;
}

// Treat empty and direct index.php hits as home
if ($route === '' || $route === 'index.php') {
    $route = 'home';
}

// Canonicalize editor root to definitions subroute
if ($route === 'editor') {
    $target = ($basePath !== '' ? $basePath : '') . '/editor/definitions';
// If this is an htmx request, instruct client to redirect via HX-Redirect
    if (isset($_SERVER['HTTP_HX_REQUEST'])) {
        header('HX-Redirect: ' . $target);
        http_response_code(204);
        exit;
    }
    header('Location: ' . $target, true, 302);
    exit;
}

// --- Normalize route & derive flags ---
$route = trim($route, '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$isHx = isset($_SERVER['HTTP_HX_REQUEST']);

// Optional: allow only safe characters in route
if ($route !== '' && !preg_match('~^[a-z0-9/_-]+$~i', $route)) {
    http_response_code(400);
    $route = '404';
}

// --- Auth (check FIRST, before executing any controller) ---
$authStart = microtime(true);
$current = app_get_current_user();
log_message('auth: ' . round((microtime(true) - $authStart) * 1000) . 'ms', 'INFO');
$role    = $current['role'] ?? 'guest';
$requiresAdmin = (strpos($route, 'editor/') === 0);
$requiresAdminView = $requiresAdmin || $route === 'users';
$requiresAuthView = $route === 'konfigurator-manager';
$hasAdminPrivileges = in_array($role, ['admin', 'superadmin'], true);
$loginUrl = ($basePath !== '' ? $basePath : '') . '/login';
$forcedView = null;

if ($requiresAuthView && $role === 'guest') {
    if ($isHx) {
        http_response_code(401);
        header('HX-Redirect: ' . $loginUrl);
        exit;
    }
    header('Location: ' . $loginUrl, true, 302);
    exit;
}

if ($requiresAdminView && !$hasAdminPrivileges) {
    // Guests should be redirected to login, including HTMX callers via HX-Redirect
    if ($role === 'guest') {
        if ($isHx) {
            http_response_code(401);
            header('HX-Redirect: ' . $loginUrl);
            exit;
        }
        header('Location: ' . $loginUrl, true, 302);
        exit;
    }
    // Authenticated but non-admin users get a 403 page without leaking admin content
    $forcedView = '403';
}

// For controller calls (usually POST HTMX), deny early if not admin
if ($requiresAdmin && !in_array($role, ['admin','superadmin'], true) && $method !== 'GET') {
    if ($isHx) {
        http_response_code(401);
        header('HX-Redirect: ' . (($basePath !== '' ? $basePath : '') . '/login'));
        exit;
    }
    // non-HTMX POST fallback
    http_response_code(403);
    exit;
}

// --- Controller dispatch (explicit map; no path-built includes) ---
$controllerRoutes = [
    // METHOD  PATH                              => file
    'POST editor/components/create'              => __DIR__ . '/controllers/editor/components/create.php',
    'POST editor/components/delete'              => __DIR__ . '/controllers/editor/components/delete.php',
    'POST editor/components/clone'               => __DIR__ . '/controllers/editor/components/clone.php',
    'POST editor/components/move'                => __DIR__ . '/controllers/editor/components/move.php',
    'POST editor/components/update'              => __DIR__ . '/controllers/editor/components/update.php',
    'GET editor/components/page'                 => __DIR__ . '/controllers/editor/components/page.php',
    'GET editor/definitions/page'                => __DIR__ . '/controllers/editor/definitions/page.php',

    'POST editor/definitions/create'             => __DIR__ . '/controllers/editor/definitions/create.php',
    'POST editor/definitions/delete'             => __DIR__ . '/controllers/editor/definitions/delete.php',
    'POST editor/definitions/move'               => __DIR__ . '/controllers/editor/definitions/move.php',
    'POST editor/definitions/range'              => __DIR__ . '/controllers/editor/definitions/range.php',
    'POST editor/definitions/rename'             => __DIR__ . '/controllers/editor/definitions/rename.php',

    'POST editor/images/delete'                  => __DIR__ . '/controllers/editor/images/delete.php',
    'POST editor/images/dir-delete'              => __DIR__ . '/controllers/editor/images/dir-delete.php',
    'POST editor/images/dir-move'                => __DIR__ . '/controllers/editor/images/dir-move.php',
    'POST editor/images/dir-rename'              => __DIR__ . '/controllers/editor/images/dir-rename.php',
    'POST editor/images/mkdir'                   => __DIR__ . '/controllers/editor/images/mkdir.php',
    'POST editor/images/move'                    => __DIR__ . '/controllers/editor/images/move.php',
    'POST editor/images/rename'                  => __DIR__ . '/controllers/editor/images/rename.php',
    'POST editor/images/upload'                  => __DIR__ . '/controllers/editor/images/upload.php',

    'POST configurator/configuration/create'     => __DIR__ . '/controllers/configurator/configuration/create.php',
    'POST configurator/configuration/update'     => __DIR__ . '/controllers/configurator/configuration/update.php',
    'POST configurator/configuration/rename'     => __DIR__ . '/controllers/configurator/configuration/rename.php',
    'POST configurator/configuration/delete'     => __DIR__ . '/controllers/configurator/configuration/delete.php',
    'GET configurator/configuration/page'        => __DIR__ . '/controllers/configurator/configuration/page.php',
    'GET configurator/configuration/pdf'         => __DIR__ . '/controllers/configurator/configuration/pdf.php',
    'POST configurator/wizard/select'            => __DIR__ . '/controllers/configurator/wizard/select.php',
    'POST configurator/wizard/back'              => __DIR__ . '/controllers/configurator/wizard/back.php',
    'POST configurator/wizard/goto-step'         => __DIR__ . '/controllers/configurator/wizard/goto-step.php',
    'POST configurator/wizard/rename'            => __DIR__ . '/controllers/configurator/wizard/rename.php',
    'POST configurator/wizard/delete'            => __DIR__ . '/controllers/configurator/wizard/delete.php',
    'POST configurator/wizard/finish'            => __DIR__ . '/controllers/configurator/wizard/finish.php',
    'POST admin/sql'                             => __DIR__ . '/controllers/admin/sql.php',
    'POST admin/export'                          => __DIR__ . '/controllers/admin/export.php',
    'POST admin/import'                          => __DIR__ . '/controllers/admin/import.php',
    'POST admin/logo'                            => __DIR__ . '/controllers/admin/logo.php',
];

$nonHtmxControllers = [
    'POST admin/export',
    'POST admin/import',
    'POST admin/logo',
    'GET configurator/configuration/pdf',
];

$routeStart = microtime(true);
$key = $method . ' ' . $route;
if (isset($controllerRoutes[$key])) {
    // Optional: enforce HTMX only
    if (!$isHx && !in_array($key, $nonHtmxControllers, true)) {
        http_response_code(400);
        exit;
    }
    require $controllerRoutes[$key];
    log_message('route resolve: ' . round((microtime(true) - $routeStart) * 1000) . 'ms', 'INFO');
    exit;
}

// --- View resolution ---
$renderStart = microtime(true);
$viewPath = __DIR__ . "/views/{$route}.php";
if ($forcedView !== null) {
    http_response_code(403);
    $view = $forcedView;
    $viewPath = __DIR__ . "/views/{$view}.php";
} else {
    $candidate = __DIR__ . "/views/{$route}.php";
    if (is_file($candidate)) {
        // Apply view-level gates (GET requests)
        if (
            ($route === 'konfigurator' && $role === 'guest')
        ) {
            http_response_code(403);
        } else {
            http_response_code(200);
        }
        $view = $route;
        $viewPath = $candidate;
    } else {
        http_response_code(404);
        $view = '404';
        $viewPath = __DIR__ . "/views/404.php";
    }
}
log_message('render: ' . round((microtime(true) - $renderStart) * 1000) . 'ms', 'INFO');

$titleMap = [
  'home' => 'HAGEMANN konfigurátor',
  'login' => 'Přihlášení',
  'register' => 'Registrace',
  'admin' => 'Administrace',
  'users' => 'Uživatelé',
  'editor' => 'Editor',
  'editor/definitions' => 'Editor - Definice',
  'editor/components' => 'Editor - Komponenty',
  'editor/images' => 'Editor - Obrázky',
  'about' => 'O aplikaci',
  'konfigurator' => 'Konfigurátor',
  'konfigurator-manager' => 'Správa konfigurací',
  '403' => 'Přístup zamítnut',
  '404' => 'Stránka nenalezena'
];
$title = $titleMap[$view] ?? ucfirst($view);
// Basic per-route meta descriptions (fallbacks)
$descMap = [
  'home' => 'HAGEMANN konfigurátor - rychlá PWA s renderováním na serveru v PHP.',
  'login' => 'Přihlášení do aplikace.',
  'register' => 'Registrace nového uživatele.',
  'admin' => 'Administrátorské rozhraní aplikace.',
  'users' => 'Správa uživatelů (pouze pro administrátory).',
  'editor' => 'Editor konfigurátoru (pouze pro administrátory).',
  'editor/definitions' => 'Editor - definice konfigurátoru (pouze pro administrátory).',
  'editor/components' => 'Editor - komponenty konfigurátoru (pouze pro administrátory).',
  'editor/images' => 'Editor - správa obrázků (pouze pro administrátory).',
  'about' => 'Informace o aplikaci a jejích možnostech.',
  'konfigurator' => 'Konfigurátor produktu s postupným výběrem.',
  'konfigurator-manager' => 'Přehled rozpracovaných a dokončených konfigurací uživatele.',
  '403' => 'Přístup na tuto stránku vyžaduje administrátorská oprávnění.',
  '404' => 'Požadovaná stránka nebyla nalezena.'
];
$description = $descMap[$view] ?? 'HAGEMANN konfigurátor';

$viewStylesMap = [
    'admin' => ['admin' => 'src/styles/admin.css'],
    'editor/definitions' => ['editor-partial-style' => 'src/styles/editor/definitions.css'],
    'editor/components' => ['editor-partial-style' => 'src/styles/editor/components.css'],
    'editor/images' => ['editor-partial-style' => 'src/styles/editor/images.css'],
    'konfigurator' => [
        'konfigurator-breadcrumbs' => 'src/styles/konfigurator/breadcrumbs.css',
        'konfigurator-configuration-wizard' => 'src/styles/konfigurator/configuration-wizard.css',
        'konfigurator-component-options' => 'src/styles/konfigurator/component-options.css',
    ],
    'konfigurator-manager' => [
        'konfigurator-manager' => 'src/styles/konfigurator/manager.css',
    ],
];

$viewStyles = $viewStylesMap[$view] ?? [];

// For HTMX requests, return *only* the fragment view (no layout/head/style/nonces)
if ($isHx) {
    $fragmentRenderStart = microtime(true);

    ob_start();
    require $viewPath;
    $html = ob_get_clean();

    if ($html === false) {
        http_response_code(500);
        exit('Failed to render fragment.');
    }

    log_message('fragment render: ' . round((microtime(true) - $fragmentRenderStart) * 1000) . 'ms', 'INFO');
    echo $html;
    log_message('total: ' . round((microtime(true) - $start) * 1000) . 'ms', 'INFO');
    exit;
}

ob_start();
require __DIR__ . '/views/layout.php';
$html = ob_get_clean();

if ($html === false) {
    http_response_code(500);
    exit('Failed to render page.');
}

echo $html;
log_message('total: ' . round((microtime(true) - $start) * 1000) . 'ms', 'INFO');
