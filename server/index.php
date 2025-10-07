<?php

// index.php
require_once __DIR__ . '/bootstrap.php';
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
$current = app_get_current_user();
$role    = $current['role'] ?? 'guest';
$requiresAdmin = (strpos($route, 'editor/') === 0);

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
    'POST editor/components-create'              => __DIR__ . '/controllers/editor/components-create.php',
    'POST editor/components-delete'              => __DIR__ . '/controllers/editor/components-delete.php',
    'POST editor/components-update'              => __DIR__ . '/controllers/editor/components-update.php',

    'POST editor/definitions-create'             => __DIR__ . '/controllers/editor/definitions-create.php',
    'POST editor/definitions-delete'             => __DIR__ . '/controllers/editor/definitions-delete.php',
    'POST editor/definitions-move'               => __DIR__ . '/controllers/editor/definitions-move.php',
    'POST editor/definitions-rename'             => __DIR__ . '/controllers/editor/definitions-rename.php',

    'POST editor/images-delete'                  => __DIR__ . '/controllers/editor/images-delete.php',
    'POST editor/images-dir-delete'              => __DIR__ . '/controllers/editor/images-dir-delete.php',
    'POST editor/images-dir-move'                => __DIR__ . '/controllers/editor/images-dir-move.php',
    'POST editor/images-dir-rename'              => __DIR__ . '/controllers/editor/images-dir-rename.php',
    'POST editor/images-mkdir'                   => __DIR__ . '/controllers/editor/images-mkdir.php',
    'POST editor/images-move'                    => __DIR__ . '/controllers/editor/images-move.php',
    'POST editor/images-rename'                  => __DIR__ . '/controllers/editor/images-rename.php',
    'POST editor/images-upload'                  => __DIR__ . '/controllers/editor/images-upload.php',
];

$key = $method . ' ' . $route;
if (isset($controllerRoutes[$key])) {
    // Optional: enforce HTMX only
    if (!$isHx) { http_response_code(400); exit; }
    require $controllerRoutes[$key];
    exit;
}

// --- View resolution ---
$viewPath = __DIR__ . "/views/{$route}.php";
if (is_file($viewPath)) {
    // Apply view-level gates (GET requests)
    if (
        ($requiresAdmin && !in_array($role, ['admin','superadmin'], true)) ||
        ($route === 'konfigurator' && $role === 'guest') ||
        ($route === 'users' && !in_array($role, ['admin','superadmin'], true))
    ) {
        http_response_code(403);
    } else {
        http_response_code(200);
    }
    $view = $route;
} else {
    http_response_code(404);
    $view = '404';
}

$titleMap = [
  'home' => 'HAGEMANN konfigurátor',
  'login' => 'Přihlášení',
  'register' => 'Registrace',
  'users' => 'Uživatelé',
  'editor' => 'Editor',
  'editor/definitions' => 'Editor - Definice',
  'editor/components' => 'Editor - Komponenty',
  'editor/images' => 'Editor - Obrázky',
  'about' => 'O aplikaci',
  'demo' => 'Demo',
  'konfigurator' => 'Konfigurátor',
  '404' => 'Stránka nenalezena'
];
$title = $titleMap[$view] ?? ucfirst($view);
// Basic per-route meta descriptions (fallbacks)
$descMap = [
  'home' => 'HAGEMANN konfigurátor - rychlá PWA s renderováním na serveru v PHP.',
  'login' => 'Přihlášení do aplikace.',
  'register' => 'Registrace nového uživatele.',
  'users' => 'Správa uživatelů (pouze pro administrátory).',
  'editor' => 'Editor konfigurátoru (pouze pro administrátory).',
  'editor/definitions' => 'Editor - definice konfigurátoru (pouze pro administrátory).',
  'editor/components' => 'Editor - komponenty konfigurátoru (pouze pro administrátory).',
  'editor/images' => 'Editor - správa obrázků (pouze pro administrátory).',
  'about' => 'Informace o aplikaci a jejích možnostech.',
  'demo' => 'Ukázková stránka aplikace.',
  'konfigurator' => 'Konfigurátor produktu s postupným výběrem.',
  '404' => 'Požadovaná stránka nebyla nalezena.'
];
$description = $descMap[$view] ?? 'HAGEMANN konfigurátor - rychlá PWA s PHP SSR.';
require __DIR__ . '/views/layout.php';
