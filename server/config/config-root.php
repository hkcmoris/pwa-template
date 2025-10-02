<?php

require_once __DIR__ . '/env-util.php';

$env = [];
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $env = parse_ini_file($envFile, false, INI_SCANNER_TYPED);
}

define('DB_A_USER', config_resolve_env($env, 'DB_A_USER', 'root'));

define('DB_A_PASS', config_resolve_env($env, 'DB_A_PASS', ''));
