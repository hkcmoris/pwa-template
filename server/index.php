<?php
// index.php
require_once __DIR__.'/config/config.php';

$route = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: 'home';
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
