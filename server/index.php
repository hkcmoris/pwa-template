<?php
// index.php
require_once __DIR__.'/config/config.php';
require_once __DIR__.'/lib/http.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$hasAuthCookie = !empty($_COOKIE['token'] ?? '') || !empty($_COOKIE['refresh_token'] ?? '');
$hasAuthHeader = !empty(trim($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
$isHtmx = isset($_SERVER['HTTP_HX_REQUEST']);

if ($method !== 'GET' || $hasAuthCookie || $hasAuthHeader || $isHtmx) {
  disable_response_cache();
} else {
  enable_micro_cache();
}

// Normalize request path and strip BASE_PATH when deployed in subfolder
$uriPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
if (defined('BASE_PATH') && BASE_PATH !== '') {
  $prefix = '#^' . preg_quote(BASE_PATH, '#') . '/?#';
  $uriPath = preg_replace($prefix, '/', $uriPath, 1);
}
$route = trim($uriPath, '/') ?: 'home';
$viewPath = __DIR__ . "/views/{$route}.php";

if (is_file($viewPath)) {
  $view = $route;
} else {
  http_response_code(404);
  $view = '404';
}

$titleMap = [
  'home' => 'PWA Template',
  'login' => 'Login',
  'register' => 'Register',
  'users' => 'Users',
  'about' => 'About',
  'demo' => 'Demo',
  '404' => 'Not Found'
];
$title = $titleMap[$view] ?? ucfirst($view);

require __DIR__ . '/views/layout.php';
