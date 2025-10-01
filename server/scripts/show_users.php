<?php
require_once __DIR__ . '/../bootstrap.php';
$db = get_db_connection();
$stmt = $db->query('SELECT id, email, role FROM users');
foreach ($stmt as $row) {
    echo $row['id'] . ' | ' . $row['email'] . ' | ' . $row['role'] . PHP_EOL;
}
