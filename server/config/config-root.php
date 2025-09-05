<?php
$env = [];
$envFile = __DIR__.'/../.env';
if (file_exists($envFile)) {
    $env = parse_ini_file($envFile, false, INI_SCANNER_TYPED);
}

define('DB_A_USER', $env['DB_A_USER'] ?? getenv('DB_A_USER') ?? 'root');
define('DB_A_PASS', $env['DB_A_PASS'] ?? getenv('DB_A_PASS') ?? '');
