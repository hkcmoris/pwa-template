<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/config-root.php';
require_once __DIR__.'/logger.php';

function get_db_root_connection(): PDO {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    try {
        return new PDO($dsn, DB_A_USER, DB_A_PASS, $options);
    } catch (PDOException $e) {
        log_message('DB connection error: ' . $e->getMessage(), 'ERROR');
        throw $e;
    }
}
