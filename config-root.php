<?php
$env = [];
$envFile = __DIR__.'/.env';
if (file_exists($envFile)) {
    $env = parse_ini_file($envFile, false, INI_SCANNER_TYPED);
}

define('DB_USER_A', $env['DB_USER_A'] ?? getenv('DB_USER_A') ?? 'root');
define('DB_PASS_A', $env['DB_PASS_A'] ?? getenv('DB_PASS_A') ?? '');
