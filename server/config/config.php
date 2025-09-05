<?php
$env = [];
$envFile = __DIR__.'/../.env';
if (file_exists($envFile)) {
    $env = parse_ini_file($envFile, false, INI_SCANNER_TYPED);
}

define('DB_HOST', $env['DB_HOST'] ?? getenv('DB_HOST') ?? 'localhost');
define('DB_NAME', $env['DB_NAME'] ?? getenv('DB_NAME') ?? 'app');
define('DB_USER', $env['DB_USER'] ?? getenv('DB_USER') ?? 'root');
define('DB_PASS', $env['DB_PASS'] ?? getenv('DB_PASS') ?? '');
define('JWT_SECRET', $env['JWT_SECRET'] ?? getenv('JWT_SECRET') ?? 'change_me');
define('APP_ENV', $env['APP_ENV'] ?? getenv('APP_ENV') ?? 'dev');

// Detect base path for subfolder deployments; can be overridden via env APP_BASE
$envBase = $env['APP_BASE'] ?? getenv('APP_BASE') ?? null;
if (!defined('BASE_PATH')) {
    if (is_string($envBase) && $envBase !== '') {
        $base = '/' . trim($envBase, '/');
    } else {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        // Normalize Windows-style backslashes to URL-style forward slashes
        $dir = dirname($script);
        $dir = str_replace('\\', '/', $dir);
        $dir = rtrim($dir, '/');
        $base = ($dir === '' || $dir === '/') ? '' : $dir;
    }
    define('BASE_PATH', $base);
}

// Prefer pretty URLs, but allow query-string routing fallback on constrained hosts
// APP_PRETTY_URLS can be: 1/0, true/false (string) or boolean
$pretty = $env['APP_PRETTY_URLS'] ?? getenv('APP_PRETTY_URLS') ?? '1';
if (!defined('PRETTY_URLS')) {
    $prettyNorm = is_bool($pretty) ? $pretty : (is_int($pretty) ? ($pretty === 1) : (strtolower((string)$pretty) === '1' || strtolower((string)$pretty) === 'true'));
    define('PRETTY_URLS', $prettyNorm ? true : false);
}
