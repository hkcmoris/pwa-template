<?php
// index.php
require_once __DIR__.'/config/config.php';
require_once __DIR__.'/lib/auth.php';

// Resolve route from query-string fallback or pretty URL path
$qsRoute = isset($_GET['r']) ? trim((string)$_GET['r'], '/') : '';

if (!$qsRoute || (defined('PRETTY_URLS') && PRETTY_URLS)) {
  // Use path-based routing when pretty URLs are enabled (or no r= provided)
  $uriPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
  if (defined('BASE_PATH') && BASE_PATH !== '') {
    $prefix = '#^' . preg_quote(BASE_PATH, '#') . '/?#';
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

$viewPath = __DIR__ . "/views/{$route}.php";

if (is_file($viewPath)) {
  http_response_code(200);
  $view = $route;
} else {
  http_response_code(404);
  $view = '404';
}

$current = app_get_current_user();
$role = $current['role'] ?? 'guest';
// Gate protected routes before output to avoid header warnings
if ($view === 'editor' && !in_array($role, ['admin','superadmin'], true)) {
  http_response_code(403);
}
if ($view === 'konfigurator' && $role === 'guest') {
  http_response_code(403);
}
if ($view === 'users' && !in_array($role, ['admin','superadmin'], true)) {
  http_response_code(403);
}

$titleMap = [
  'home' => 'HAGEMANN konfigurátor',
  'login' => 'Přihlášení',
  'register' => 'Registrace',
  'users' => 'Uživatelé',
  'editor' => 'Editor',
  'about' => 'O aplikaci',
  'demo' => 'Demo',
  'konfigurator' => 'Konfigurátor',
  '404' => 'Stránka nenalezena'
];
$title = $titleMap[$view] ?? ucfirst($view);

// Basic per-route meta descriptions (fallbacks)
$descMap = [
  'home' => 'HAGEMANN konfigurátor – rychlá PWA s renderováním na serveru v PHP.',
  'login' => 'Přihlášení do aplikace.',
  'register' => 'Registrace nového uživatele.',
  'users' => 'Správa uživatelů (pouze pro administrátory).',
  'editor' => 'Editor konfigurátoru (pouze pro administrátory).',
  'about' => 'Informace o aplikaci a jejích možnostech.',
  'demo' => 'Ukázková stránka aplikace.',
  'konfigurator' => 'Konfigurátor produktu s postupným výběrem.',
  '404' => 'Požadovaná stránka nebyla nalezena.'
];
$description = $descMap[$view] ?? 'HAGEMANN konfigurátor – rychlá PWA s PHP SSR.';

require __DIR__ . '/views/layout.php';
