<?php

declare(strict_types=1);

// One-off helper to promote a specific user account to superadmin.
// Usage: php server/scripts/grant_superadmin.php [email]

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script may only be run from the CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../bootstrap.php';

$email = $argv[1] ?? 'moravec@devground.cz';
$email = filter_var($email, FILTER_VALIDATE_EMAIL);
if ($email === false) {
    fwrite(STDERR, "Provide a valid email address.\n");
    exit(1);
}

try {
    $db = get_db_connection();
} catch (Throwable $e) {
    fwrite(STDERR, 'Failed to connect to database: ' . $e->getMessage() . "\n");
    exit(1);
}

try {
    $stmt = $db->prepare('UPDATE users SET role = :role WHERE email = :email');
    $stmt->execute([
        ':email' => $email,
        ':role' => 'superadmin',
    ]);

    if ($stmt->rowCount() === 0) {
        fwrite(STDERR, "No user found for {$email}.\n");
        exit(1);
    }

    log_message("Promoted {$email} to superadmin", 'INFO');
    fwrite(STDOUT, "User {$email} is now superadmin.\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Failed to update role: ' . $e->getMessage() . "\n");
    exit(1);
}
