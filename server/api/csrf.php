<?php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/../lib/csrf.php';

header('Content-Type: application/json; charset=UTF-8');

$token = csrf_token();

echo json_encode(['token' => $token]);

