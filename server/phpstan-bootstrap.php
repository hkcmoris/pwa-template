<?php

if (!defined('APP_ENV')) {
    define('APP_ENV', 'phpstan');
}
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '0.0.0');
}
if (!defined('BASE_PATH')) {
    define('BASE_PATH', 'phpstan');
}
if (!defined('PRETTY_URLS')) {
    define('PRETTY_URLS', true);
}

if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'app');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', '');
}
if (!defined('DB_A_USER')) {
    define('DB_A_USER', DB_USER);
}
if (!defined('DB_A_PASS')) {
    define('DB_A_PASS', DB_PASS);
}
if (!defined('JWT_SECRET')) {
    define('JWT_SECRET', 'phpstan_fallback_secret');
}
