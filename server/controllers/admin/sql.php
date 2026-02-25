<?php

require_once __DIR__ . '/../../bootstrap.php';

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
    header('Vary: HX-Request, HX-Boosted, X-Requested-With, Cookie');
}

$user = app_get_current_user();
$role = $user['role'] ?? 'guest';
if ($role !== 'superadmin') {
    http_response_code(403);
    echo '<div id="admin-messages" class="admin-feedback admin-feedback--error" hx-swap-oob="true">';
    echo 'Nemáte oprávnění spouštět SQL dotazy.';
    echo '</div>';
    echo '<div id="admin-results"></div>';
    return;
}

$sql = isset($_POST['sql_query']) ? trim((string) $_POST['sql_query']) : '';
if ($sql === '') {
    http_response_code(422);
    echo '<div id="admin-messages" class="admin-feedback admin-feedback--error" hx-swap-oob="true">';
    echo 'Vyplňte SQL dotaz.';
    echo '</div>';
    echo '<div id="admin-results"></div>';
    return;
}

try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $columnCount = $stmt->columnCount();
    $rows = [];
    if ($columnCount > 0) {
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    echo '<div id="admin-messages" class="admin-feedback admin-feedback--success" hx-swap-oob="true">';
    if ($columnCount > 0) {
        $count = count($rows);
        echo 'Dotaz proběhl úspěšně. Počet řádků: ' . $count . '.';
    } else {
        $affected = $stmt->rowCount();
        echo 'Dotaz proběhl úspěšně. Ovlivněné řádky: ' . $affected . '.';
    }
    echo '</div>';

    echo '<div id="admin-results">';
    if ($columnCount > 0) {
        if (empty($rows)) {
            echo '<p>Dotaz nevrátil žádná data.</p>';
        } else {
            $columns = array_keys($rows[0]);
            echo '<table>';
            echo '<thead><tr>';
            foreach ($columns as $column) {
                echo '<th>' . htmlspecialchars($column, ENT_QUOTES, 'UTF-8') . '</th>';
            }
            echo '</tr></thead>';
            echo '<tbody>';
            foreach ($rows as $row) {
                echo '<tr>';
                foreach ($columns as $column) {
                    $value = $row[$column];
                    $display = $value === null ? 'NULL' : (string) $value;
                    echo '<td>' . htmlspecialchars($display, ENT_QUOTES, 'UTF-8') . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
        }
    }
    echo '</div>';
} catch (Throwable $e) {
    http_response_code(500);
    log_message('Admin SQL query failed: ' . $e->getMessage(), 'ERROR');
    echo '<div id="admin-messages" class="admin-feedback admin-feedback--error" hx-swap-oob="true">';
    echo 'Dotaz selhal: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    echo '</div>';
    echo '<div id="admin-results"></div>';
}
