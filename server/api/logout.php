<?php
require_once __DIR__.'/cors.php';
require_once __DIR__.'/../lib/logger.php';

header('Content-Type: application/json');

setcookie('token', '', [
    'expires' => time() - 3600,
    'path' => '/',
]);

log_message('User logged out');

echo json_encode(['success' => true]);
