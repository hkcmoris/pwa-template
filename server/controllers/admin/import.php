<?php

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../config/config-root.php';
require_once __DIR__ . '/../../lib/db-root.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
}

csrf_require_valid($_POST, 'json');

$user = app_get_current_user();
$role = $user['role'] ?? 'guest';
if ($role !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['error' => 'Nemáte oprávnění importovat databázi.']);
    exit;
}

if (!isset($_FILES['sql_file'])) {
    http_response_code(422);
    echo json_encode(['error' => 'Vyberte SQL soubor k importu.']);
    exit;
}

$upload = $_FILES['sql_file'];
if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(422);
    echo json_encode(['error' => 'Soubor se nepodařilo nahrát.']);
    exit;
}

$tmpName = $upload['tmp_name'] ?? '';
if ($tmpName === '' || !is_uploaded_file($tmpName)) {
    http_response_code(422);
    echo json_encode(['error' => 'Nahraný soubor není dostupný.']);
    exit;
}

$fileName = (string) ($upload['name'] ?? '');
if ($fileName !== '' && strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== 'sql') {
    http_response_code(422);
    echo json_encode(['error' => 'Import vyžaduje soubor ve formátu .sql.']);
    exit;
}

$rawSql = file_get_contents($tmpName);
if ($rawSql === false || trim($rawSql) === '') {
    http_response_code(422);
    echo json_encode(['error' => 'SQL soubor je prázdný nebo nečitelný.']);
    exit;
}

if (strpos($rawSql, '-- HAGEMANN APP EXPORT v1') === false) {
    http_response_code(422);
    echo json_encode(['error' => 'Soubor není exportem této aplikace.']);
    exit;
}

$definitionsSelected = isset($_POST['definitions']);
$componentsSelected = isset($_POST['components']);
$pricesSelected = isset($_POST['prices']);
$usersSelected = isset($_POST['users']);

if ($componentsSelected) {
    $definitionsSelected = true;
}

$tableGroups = [
    'definitions' => ['definitions'],
    'components' => ['components'],
    'prices' => ['prices'],
    'users' => ['users'],
];

$selectedTables = [];
if ($definitionsSelected) {
    $selectedTables = array_merge($selectedTables, $tableGroups['definitions']);
}
if ($componentsSelected) {
    $selectedTables = array_merge($selectedTables, $tableGroups['components']);
}
if ($pricesSelected) {
    $selectedTables = array_merge($selectedTables, $tableGroups['prices']);
}
if ($usersSelected) {
    $selectedTables = array_merge($selectedTables, $tableGroups['users']);
}
if (empty($selectedTables)) {
    http_response_code(422);
    echo json_encode(['error' => 'Vyberte alespoň jednu skupinu dat k importu.']);
    exit;
}

$selectedTables = array_values(array_unique($selectedTables));
$allowedTables = ['definitions', 'components', 'prices', 'users'];

$statementChunks = preg_split(
    '/^-- HAGEMANN-STATEMENT\\s*$/m',
    $rawSql
);
if (!is_array($statementChunks)) {
    http_response_code(422);
    echo json_encode(['error' => 'SQL soubor je poškozený.']);
    exit;
}

$statements = [];
$seenTables = [];
$selectedSeenTables = [];

foreach ($statementChunks as $chunk) {
    $clean = preg_replace('/^--.*$/m', '', $chunk);
    $clean = trim((string) $clean);
    if ($clean === '') {
        continue;
    }
    if (substr($clean, -1) === ';') {
        $clean = substr($clean, 0, -1);
    }

    if (preg_match('/^SET\\s+FOREIGN_KEY_CHECKS\\s*=\\s*[01]$/i', $clean)) {
        $statements[] = ['sql' => $clean, 'table' => null];
        continue;
    }

    if (preg_match('/^TRUNCATE\\s+TABLE\\s+`?([a-z0-9_]+)`?$/i', $clean, $match)) {
        $table = $match[1];
        if (!in_array($table, $allowedTables, true)) {
            http_response_code(422);
            echo json_encode(['error' => 'SQL soubor obsahuje neznámé tabulky.']);
            exit;
        }
        $seenTables[$table] = true;
        if (in_array($table, $selectedTables, true)) {
            $selectedSeenTables[$table] = true;
            $statements[] = ['sql' => $clean, 'table' => $table, 'type' => 'truncate'];
        }
        continue;
    }

    if (
        preg_match(
            '/^INSERT\\s+INTO\\s+`?([a-z0-9_]+)`?\\s*\\(/i',
            $clean,
            $match
        )
    ) {
        $table = $match[1];
        if (!in_array($table, $allowedTables, true)) {
            http_response_code(422);
            echo json_encode(['error' => 'SQL soubor obsahuje neznámé tabulky.']);
            exit;
        }
        $seenTables[$table] = true;
        if (in_array($table, $selectedTables, true)) {
            $selectedSeenTables[$table] = true;
            $statements[] = ['sql' => $clean, 'table' => $table, 'type' => 'insert'];
        }
        continue;
    }

    http_response_code(422);
    echo json_encode(['error' => 'SQL soubor obsahuje nepodporované příkazy.']);
    exit;
}

if (empty($seenTables)) {
    http_response_code(422);
    echo json_encode(['error' => 'SQL soubor neobsahuje data pro tuto aplikaci.']);
    exit;
}

$missing = array_diff($selectedTables, array_keys($selectedSeenTables));
if (!empty($missing)) {
    http_response_code(422);
    echo json_encode([
        'error' =>
            'SQL soubor neobsahuje všechna vybraná data: ' .
            implode(', ', $missing) .
            '.',
    ]);
    exit;
}

try {
    $pdo = get_db_root_connection();
    $pdo->beginTransaction();
    $executed = 0;
    foreach ($statements as $statement) {
        $sql = $statement['sql'];
        if (($statement['type'] ?? null) === 'truncate') {
            $sql = 'DELETE FROM `' . $statement['table'] . '`';
        }
        $pdo->exec($sql);
        $executed++;
    }
    $pdo->commit();
    echo json_encode([
        'message' => 'Import proběhl úspěšně. Počet příkazů: ' . $executed . '.',
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    log_message('Admin import failed: ' . $e->getMessage(), 'ERROR');
    echo json_encode(['error' => 'Import selhal: ' . $e->getMessage()]);
}
