<?php

$env = [];
$envFile = __DIR__ . '/../.env';
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
    $prettyNorm = is_bool($pretty)
        ? $pretty
        : (is_int($pretty)
            ? ($pretty === 1)
            : (strtolower((string)$pretty) === '1' || strtolower((string)$pretty) === 'true')
        );
    define('PRETTY_URLS', $prettyNorm ? true : false);
}

// Toggle service worker registration (defaults to on)
$swFlag = $env['SW'] ?? getenv('SW') ?? 'on';
if (!defined('SW_ENABLED')) {
    if (is_bool($swFlag)) {
        $swEnabled = $swFlag;
    } elseif (is_int($swFlag)) {
        $swEnabled = ($swFlag === 1);
    } else {
        $swValue = strtolower((string) $swFlag);
        $swEnabled = !in_array($swValue, ['0','false','off','no'], true);
    }
    define('SW_ENABLED', $swEnabled ? true : false);
}
if (!defined('APP_VERSION')) {
    $version = $env['APP_VERSION'] ?? getenv('APP_VERSION') ?? null;
    if (!is_string($version) || $version === '') {
        $packagePath = __DIR__ . '/../../package.json';
        if (is_file($packagePath)) {
            $packageJson = file_get_contents($packagePath);
            if ($packageJson !== false) {
                $packageData = json_decode($packageJson, true);
                if (is_array($packageData) && isset($packageData['version'])) {
                    $version = (string) $packageData['version'];
                }
            }
        }
    }
    if (!is_string($version) || $version === '') {
        $version = 'dev';
    }
    define('APP_VERSION', $version);
}
