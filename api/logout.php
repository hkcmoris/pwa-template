<?php
header('Content-Type: application/json');

setcookie('token', '', [
    'expires' => time() - 3600,
    'path' => '/',
]);

echo json_encode(['success' => true]);
