<?php

require_once __DIR__ . '/env-util.php';

$env = [];
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $env = parse_ini_file($envFile, false, INI_SCANNER_TYPED);
}

define('DB_HOST', config_resolve_env($env, 'DB_HOST', 'localhost'));
define('DB_NAME', config_resolve_env($env, 'DB_NAME', 'app'));
define('DB_USER', config_resolve_env($env, 'DB_USER', 'root'));
define('DB_PASS', config_resolve_env($env, 'DB_PASS', ''));
define('JWT_SECRET', config_resolve_env($env, 'JWT_SECRET', 'change_me'));
define('APP_ENV', config_resolve_env($env, 'APP_ENV', 'dev'));

// Detect base path for subfolder deployments; can be overridden via env APP_BASE
$envBase = config_resolve_env($env, 'APP_BASE');
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
$pretty = config_resolve_env($env, 'APP_PRETTY_URLS', '1');
if (!defined('PRETTY_URLS')) {
    $prettyNorm = is_bool($pretty)
        ? $pretty
        : (is_int($pretty)
            ? ($pretty === 1)
            : (strtolower((string) $pretty) === '1' || strtolower((string) $pretty) === 'true')
        );
    define('PRETTY_URLS', $prettyNorm ? true : false);
}

// Toggle service worker registration (defaults to on)
$swFlag = config_resolve_env($env, 'SW', 'on');
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
    $version = config_resolve_env($env, 'APP_VERSION');
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
