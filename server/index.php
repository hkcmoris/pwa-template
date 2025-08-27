<?php
// index.php
require_once __DIR__.'/config/config.php';

$route = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: 'home';
$titleMap = ['home'=>'PWA Template','login'=>'Login','register'=>'Register','users'=>'Users','about'=>'About'];
$title = $titleMap[$route] ?? ucfirst($route);

$viewPath = __DIR__ . "/views/{$route}.php";
$view = is_file($viewPath) ? $route : null;

require __DIR__ . '/views/layout.php';
