<?php
require_once __DIR__ . '/../lib/db.php';

$query = '';
$columns = [];
$rows = [];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $query = trim($_POST['query'] ?? '');

    if ($query === '') {
        $error = 'Please enter a SQL query.';
    } else {
        try {
            $pdo = get_db_connection();
            $keyword = strtoupper(strtok($query, " \t\n\r"));
            $resultKeywords = ['SELECT', 'SHOW', 'DESCRIBE', 'PRAGMA', 'WITH', 'EXPLAIN'];

            if (in_array($keyword, $resultKeywords, true)) {
                $statement = $pdo->query($query);
                $columnCount = $statement->columnCount();
                for ($i = 0; $i < $columnCount; $i += 1) {
                    $meta = $statement->getColumnMeta($i);
                    $columns[] = $meta['name'] ?? "column_{$i}";
                }
                $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
                $message = sprintf('Query returned %d row(s).', count($rows));
            } else {
                $affected = $pdo->exec($query);
                $message = sprintf('Statement executed. %d row(s) affected.', (int) $affected);
            }
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}
?>

<section>
    <h1>Admin SQL Console</h1>
    <form method="post">
        <label for="sql-query">SQL Query</label>
        <textarea
            id="sql-query"
            name="query"
            rows="6"
            class="auth-form__input"
        ><?= htmlspecialchars($query) ?></textarea>
        <button type="submit">Run Query</button>
    </form>

    <?php if ($error !== ''): ?>
        <p role="alert"><?= htmlspecialchars($error) ?></p>
    <?php elseif ($message !== ''): ?>
        <p><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <?php if (!empty($columns)): ?>
        <table>
            <thead>
                <tr>
                    <?php foreach ($columns as $column): ?>
                        <th><?= htmlspecialchars($column) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($rows)): ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($columns as $column): ?>
                                <td><?= htmlspecialchars((string) ($row[$column] ?? '')) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?= count($columns) ?>">No rows returned.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
