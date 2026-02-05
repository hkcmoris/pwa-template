<?php

require_once __DIR__ . '/../../bootstrap.php';

csrf_require_valid($_POST, 'text');

$user = app_get_current_user();
$role = $user['role'] ?? 'guest';
if ($role !== 'superadmin') {
    http_response_code(403);
    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=UTF-8');
    }
    echo 'Nemáte oprávnění exportovat databázi.';
    exit;
}

$definitionsSelected = isset($_POST['definitions']);
$componentsSelected = isset($_POST['components']);
$usersSelected = isset($_POST['users']);

if ($componentsSelected) {
    $definitionsSelected = true;
}

$tableGroups = [
    'definitions' => ['definitions', 'definition_components'],
    'components' => ['components', 'prices'],
    'users' => ['users'],
];

$selectedTables = [];
if ($definitionsSelected) {
    $selectedTables = array_merge($selectedTables, $tableGroups['definitions']);
}
if ($componentsSelected) {
    $selectedTables = array_merge($selectedTables, $tableGroups['components']);
}
if ($usersSelected) {
    $selectedTables = array_merge($selectedTables, $tableGroups['users']);
}

$selectedTables = array_values(array_unique($selectedTables));

if (empty($selectedTables)) {
    http_response_code(422);
    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=UTF-8');
    }
    echo 'Vyberte alespoň jednu skupinu dat k exportu.';
    exit;
}

$buildInsertStatements = static function (
    PDO $pdo,
    string $table,
    array $rows,
    int $chunkSize = 200
): array {
    if (empty($rows)) {
        return [];
    }
    $columns = array_keys($rows[0]);
    $columnList = '`' . implode('`, `', $columns) . '`';
    $statements = [];
    $chunks = array_chunk($rows, $chunkSize);
    foreach ($chunks as $chunk) {
        $values = [];
        foreach ($chunk as $row) {
            $items = [];
            foreach ($columns as $column) {
                $value = $row[$column] ?? null;
                if ($value === null) {
                    $items[] = 'NULL';
                } elseif (is_bool($value)) {
                    $items[] = $value ? '1' : '0';
                } elseif (is_int($value) || is_float($value)) {
                    $items[] = (string) $value;
                } else {
                    $items[] = $pdo->quote((string) $value);
                }
            }
            $values[] = '(' . implode(', ', $items) . ')';
        }
        $statements[] =
            'INSERT INTO `' .
            $table .
            '` (' .
            $columnList .
            ') VALUES ' .
            implode(', ', $values);
    }
    return $statements;
};

try {
    $pdo = get_db_connection();
    $insertOrder = [
        'definitions',
        'definition_components',
        'components',
        'prices',
        'users',
    ];
    $truncateOrder = array_reverse($insertOrder);
    $statements = [];
    $statements[] = 'SET FOREIGN_KEY_CHECKS=0';

    foreach ($truncateOrder as $table) {
        if (in_array($table, $selectedTables, true)) {
            $statements[] = 'TRUNCATE TABLE `' . $table . '`';
        }
    }

    foreach ($insertOrder as $table) {
        if (!in_array($table, $selectedTables, true)) {
            continue;
        }
        $rows = $pdo->query('SELECT * FROM `' . $table . '`')->fetchAll();
        if (empty($rows)) {
            continue;
        }
        $statements = array_merge(
            $statements,
            $buildInsertStatements($pdo, $table, $rows)
        );
    }

    $statements[] = 'SET FOREIGN_KEY_CHECKS=1';

    $timestamp = gmdate('Y-m-d\TH:i:s\Z');
    $filename = 'hagemann-export-' . gmdate('Ymd-His') . '.sql';
    $headerLines = [
        '-- HAGEMANN APP EXPORT v1',
        '-- Generated: ' . $timestamp,
        '-- Tables: ' . implode(', ', $selectedTables),
        '',
    ];
    $payload = implode("\n", $headerLines);
    foreach ($statements as $statement) {
        $payload .= "-- HAGEMANN-STATEMENT\n" . $statement . ";\n\n";
    }

    if (!headers_sent()) {
        header('Content-Type: application/sql; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
    }
    echo $payload;
} catch (Throwable $e) {
    http_response_code(500);
    log_message('Admin export failed: ' . $e->getMessage(), 'ERROR');
    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=UTF-8');
    }
    echo 'Export selhal: ' . $e->getMessage();
}
