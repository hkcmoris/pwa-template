<?php

// Lightweight migration runner. Intended for local development only.
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/config/config-root.php';
require_once __DIR__ . '/lib/db-root.php';

$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
$isCli = PHP_SAPI === 'cli';
$allowedHosts = ['127.0.0.1', '::1', ''];

if (!$isCli && !in_array($remoteAddr, $allowedHosts, true)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden: migration runner is only accessible from localhost.";
    exit;
}
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

try {
    $pdo = get_db_root_connection();
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Failed to connect to database: ' . $e->getMessage();
    exit;
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        filename VARCHAR(255) NOT NULL UNIQUE,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$executedStmt = $pdo->query('SELECT filename FROM schema_migrations');
$executed = array_fill_keys($executedStmt->fetchAll(PDO::FETCH_COLUMN), true);

$migrationDir = __DIR__ . '/database/migrations';
$files = glob($migrationDir . '/*.sql');
sort($files, SORT_NATURAL);

if (!$files) {
    echo "No migration files located in {$migrationDir}.";
    exit;
}

$applied = [];
$skipped = [];
$failed = [];

foreach ($files as $file) {
    $name = basename($file);
    if (isset($executed[$name])) {
        $skipped[] = $name;
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        $failed[$name] = 'Could not read file.';
        continue;
    }

    $statements = extractStatements($sql);
    if (!$statements) {
        $failed[$name] = 'No executable statements found.';
        continue;
    }

    try {
        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
        $insert = $pdo->prepare('INSERT INTO schema_migrations (filename) VALUES (:filename)');
        $insert->bindValue(':filename', $name, PDO::PARAM_STR);
        $insert->execute();
        $applied[] = $name;
        log_message('Migration applied: ' . $name, 'INFO');
    } catch (Throwable $e) {
        $failed[$name] = $e->getMessage();
        log_message('Migration failed: ' . $name . ' - ' . $e->getMessage(), 'ERROR');
    }
}

echo "Migration runner completed\n";
if ($applied) {
    echo "Applied (" . count($applied) . "):\n  - " . implode("\n  - ", $applied) . "\n";
}
if ($skipped) {
    echo "Skipped (already applied) (" . count($skipped) . "):\n  - " . implode("\n  - ", $skipped) . "\n";
}
if ($failed) {
    http_response_code(500);
    echo "Failures (" . count($failed) . "):\n";
    foreach ($failed as $fileName => $reason) {
        echo "  - {$fileName}: {$reason}\n";
    }
    exit;
}

if (!$applied) {
    echo "No new migrations to apply.";
}

exit;

function extractStatements(string $sql): array
{
    $normalized = str_replace(["\r\n", "\r"], "\n", $sql);
    $withoutBlockComments = preg_replace('#/\*.*?\*/#s', '', $normalized) ?? '';
    $withoutLineComments = preg_replace('~^\s*--.*$~m', '', $withoutBlockComments) ?? '';
    $parts = array_map('trim', explode(';', $withoutLineComments));
    $statements = [];
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        $statements[] = $part;
    }
    return $statements;
}
