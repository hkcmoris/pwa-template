<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/definitions.php';

class FakePDO extends PDO
{
    /** @var array<int,array<string,mixed>> */
    public array $rows = [];

    /** @var array<int,array{type:string,action:string}> */
    public array $operations = [];

    /** @var array<int,array{sql:string,params:array<string,mixed>}> */
    public array $executions = [];

    /** @param array<int,array<string,mixed>> $rows */
    public function __construct(array $rows)
    {
        $this->rows = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $normalized = $row;
            $normalized['id'] = $id;
            $normalized['position'] = (int) $row['position'];
            $normalized['parent_id'] = array_key_exists('parent_id', $row) && $row['parent_id'] !== null
                ? (int) $row['parent_id']
                : null;
            $this->rows[$id] = $normalized;
        }
    }

    public function beginTransaction(): bool
    {
        $this->operations[] = ['type' => 'transaction', 'action' => 'begin'];
        return true;
    }

    public function commit(): bool
    {
        $this->operations[] = ['type' => 'transaction', 'action' => 'commit'];
        return true;
    }

    public function rollBack(): bool
    {
        $this->operations[] = ['type' => 'transaction', 'action' => 'rollback'];
        return true;
    }

    #[\ReturnTypeWillChange]
    public function prepare(string $query, array $options = []): FakeStatement
    {
        return new FakeStatement($this, $query);
    }

    /**
     * @param string $query
     * @param array<string,mixed> $params
     * @return array<int,array<string|int,mixed>>
     */
    public function executeQuery(string $query, array $params): array
    {
        $this->executions[] = ['sql' => $query, 'params' => $params];

        $normalizedQuery = trim(preg_replace('/\s+/', ' ', $query) ?? '');

        if (str_starts_with($normalizedQuery, 'SELECT id, parent_id, title, position, meta, created_at, updated_at FROM definitions WHERE id =')) {
            $id = isset($params[':id']) ? (int) $params[':id'] : 0;
            $row = $this->rows[$id] ?? null;
            return $row ? [$row] : [];
        }

        if ($normalizedQuery === 'SELECT parent_id FROM definitions WHERE id = :id') {
            $id = isset($params[':id']) ? (int) $params[':id'] : 0;
            $row = $this->rows[$id] ?? null;
            if (!$row) {
                return [];
            }
            return [[0 => $row['parent_id']]];
        }

        if ($normalizedQuery === 'SELECT 1 FROM definitions WHERE id = :id LIMIT 1') {
            $id = isset($params[':id']) ? (int) $params[':id'] : 0;
            return isset($this->rows[$id]) ? [[0 => 1]] : [];
        }

        if ($normalizedQuery === 'SELECT COUNT(*) FROM definitions WHERE parent_id <=> :parent') {
            $parent = $params[':parent'] ?? null;
            $count = 0;
            foreach ($this->rows as $row) {
                if ($this->parentMatches($row['parent_id'], $parent)) {
                    $count++;
                }
            }
            return [[0 => $count]];
        }

        if ($normalizedQuery === 'SELECT COALESCE(MAX(position), -1) FROM definitions WHERE parent_id <=> :parent') {
            $parent = $params[':parent'] ?? null;
            $max = -1;
            foreach ($this->rows as $row) {
                if ($this->parentMatches($row['parent_id'], $parent)) {
                    $max = max($max, (int) $row['position']);
                }
            }
            return [[0 => $max]];
        }

        if ($normalizedQuery === 'SELECT id FROM definitions WHERE parent_id <=> :parent ORDER BY position FOR UPDATE') {
            $parent = $params[':parent'] ?? null;
            $ids = $this->selectIdsForParent($parent);
            return array_map(static fn(int $id): array => ['id' => $id], $ids);
        }

        if ($normalizedQuery === 'SELECT id FROM definitions WHERE parent_id <=> :parent ORDER BY position, id') {
            $parent = $params[':parent'] ?? null;
            $ids = $this->selectIdsForParent($parent);
            return array_map(static fn(int $id): array => ['id' => $id], $ids);
        }

        if ($normalizedQuery === 'UPDATE definitions SET position = :position WHERE id = :id') {
            $id = isset($params[':id']) ? (int) $params[':id'] : 0;
            if (isset($this->rows[$id])) {
                $this->rows[$id]['position'] = (int) $params[':position'];
            }
            return [];
        }

        if ($normalizedQuery === 'UPDATE definitions SET position = position - 1 WHERE parent_id <=> :parent AND id <> :id AND position > :position') {
            $parent = $params[':parent'] ?? null;
            $skipId = isset($params[':id']) ? (int) $params[':id'] : 0;
            $threshold = isset($params[':position']) ? (int) $params[':position'] : 0;
            foreach ($this->rows as $id => &$row) {
                if ($id === $skipId) {
                    continue;
                }
                if ($this->parentMatches($row['parent_id'], $parent) && (int) $row['position'] > $threshold) {
                    $row['position'] = (int) $row['position'] - 1;
                }
            }
            unset($row);
            return [];
        }

        if ($normalizedQuery === 'UPDATE definitions SET position = position + 1 WHERE parent_id <=> :parent AND position >= :position') {
            $parent = $params[':parent'] ?? null;
            $threshold = isset($params[':position']) ? (int) $params[':position'] : 0;
            foreach ($this->rows as &$row) {
                if ($this->parentMatches($row['parent_id'], $parent) && (int) $row['position'] >= $threshold) {
                    $row['position'] = (int) $row['position'] + 1;
                }
            }
            unset($row);
            return [];
        }

        if ($normalizedQuery === 'UPDATE definitions SET parent_id = :parent, position = :position WHERE id = :id') {
            $id = isset($params[':id']) ? (int) $params[':id'] : 0;
            if (isset($this->rows[$id])) {
                $this->rows[$id]['parent_id'] = $params[':parent'] === null ? null : (int) $params[':parent'];
                $this->rows[$id]['position'] = (int) $params[':position'];
            }
            return [];
        }

        if ($normalizedQuery === 'UPDATE definitions SET position = position + 1000000 WHERE parent_id <=> :parent') {
            $parent = $params[':parent'] ?? null;
            foreach ($this->rows as &$row) {
                if ($this->parentMatches($row['parent_id'], $parent)) {
                    $row['position'] = (int) $row['position'] + 1000000;
                }
            }
            unset($row);
            return [];
        }

        throw new RuntimeException('Unsupported query: ' . $query);
    }

    /**
     * @param mixed $rowParent
     * @param mixed $param
     */
    private function parentMatches($rowParent, $param): bool
    {
        if ($rowParent === null && ($param === null || $param === '')) {
            return true;
        }
        if ($rowParent === null) {
            return false;
        }
        if ($param === null || $param === '') {
            return false;
        }
        return (int) $rowParent === (int) $param;
    }

    /**
     * @param mixed $parent
     * @return array<int,int>
     */
    private function selectIdsForParent($parent): array
    {
        $result = [];
        foreach ($this->rows as $row) {
            if ($this->parentMatches($row['parent_id'], $parent)) {
                $result[] = $row;
            }
        }
        usort($result, static function (array $a, array $b): int {
            if ($a['position'] === $b['position']) {
                return $a['id'] <=> $b['id'];
            }
            return $a['position'] <=> $b['position'];
        });
        return array_map(static fn(array $row): int => (int) $row['id'], $result);
    }
}

class FakeStatement
{
    private FakePDO $pdo;

    private string $query;

    /** @var array<string,mixed> */
    private array $params = [];

    /** @var array<int,array<string|int,mixed>> */
    private array $results = [];

    private int $cursor = 0;

    public function __construct(FakePDO $pdo, string $query)
    {
        $this->pdo = $pdo;
        $this->query = $query;
    }

    public function bindValue(string $param, $value, int $type = PDO::PARAM_STR): bool
    {
        if ($type === PDO::PARAM_NULL) {
            $this->params[$param] = null;
        } else {
            $this->params[$param] = $value;
        }
        return true;
    }

    public function execute(?array $params = null): bool
    {
        if ($params !== null) {
            foreach ($params as $key => $value) {
                $this->params[$key] = $value;
            }
        }
        $this->results = $this->pdo->executeQuery($this->query, $this->params);
        $this->cursor = 0;
        return true;
    }

    /** @return array<string|int,mixed>|false */
    public function fetch()
    {
        if (!isset($this->results[$this->cursor])) {
            return false;
        }
        return $this->results[$this->cursor++];
    }

    /** @return mixed */
    public function fetchColumn(int $column = 0)
    {
        $row = $this->fetch();
        if ($row === false) {
            return false;
        }
        if (array_key_exists($column, $row)) {
            return $row[$column];
        }
        return reset($row);
    }

    /**
     * @param int $mode
     * @return array<int,mixed>
     */
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT): array
    {
        if ($mode === PDO::FETCH_COLUMN) {
            $values = [];
            foreach ($this->results as $row) {
                $values[] = reset($row);
            }
            return $values;
        }
        return $this->results;
    }

    public function closeCursor(): bool
    {
        $this->results = [];
        $this->cursor = 0;
        return true;
    }
}

$input = trim(stream_get_contents(STDIN));
$data = $input !== '' ? json_decode($input, true) : null;

if (!is_array($data) || !isset($data['scenario'])) {
    fwrite(STDERR, "Missing scenario input\n");
    exit(1);
}

$scenario = (string) $data['scenario'];

$scenarios = [
    'move_to_new_parent' => [
        'rows' => [
            ['id' => 1, 'parent_id' => null, 'position' => 0, 'title' => 'Root', 'meta' => null, 'created_at' => null, 'updated_at' => null],
            ['id' => 2, 'parent_id' => 1, 'position' => 0, 'title' => 'Alpha', 'meta' => null, 'created_at' => null, 'updated_at' => null],
            ['id' => 3, 'parent_id' => 1, 'position' => 1, 'title' => 'Beta', 'meta' => null, 'created_at' => null, 'updated_at' => null],
            ['id' => 4, 'parent_id' => null, 'position' => 1, 'title' => 'Extra', 'meta' => null, 'created_at' => null, 'updated_at' => null],
            ['id' => 5, 'parent_id' => 4, 'position' => 0, 'title' => 'Child', 'meta' => null, 'created_at' => null, 'updated_at' => null],
        ],
        'move' => ['id' => 3, 'parent' => 4, 'position' => 1],
    ],
    'move_within_parent_down' => [
        'rows' => [
            ['id' => 10, 'parent_id' => null, 'position' => 0, 'title' => 'Root', 'meta' => null, 'created_at' => null, 'updated_at' => null],
            ['id' => 11, 'parent_id' => 10, 'position' => 0, 'title' => 'One', 'meta' => null, 'created_at' => null, 'updated_at' => null],
            ['id' => 12, 'parent_id' => 10, 'position' => 1, 'title' => 'Two', 'meta' => null, 'created_at' => null, 'updated_at' => null],
            ['id' => 13, 'parent_id' => 10, 'position' => 2, 'title' => 'Three', 'meta' => null, 'created_at' => null, 'updated_at' => null],
        ],
        'move' => ['id' => 11, 'parent' => 10, 'position' => 2],
    ],
    'move_to_root' => [
        'rows' => [
            ['id' => 20, 'parent_id' => null, 'position' => 0, 'title' => 'Root', 'meta' => null, 'created_at' => null, 'updated_at' => null],
            ['id' => 21, 'parent_id' => 20, 'position' => 0, 'title' => 'Child A', 'meta' => null, 'created_at' => null, 'updated_at' => null],
            ['id' => 22, 'parent_id' => 20, 'position' => 1, 'title' => 'Child B', 'meta' => null, 'created_at' => null, 'updated_at' => null],
            ['id' => 23, 'parent_id' => null, 'position' => 1, 'title' => 'Sibling Root', 'meta' => null, 'created_at' => null, 'updated_at' => null],
        ],
        'move' => ['id' => 22, 'parent' => null, 'position' => 5],
    ],
    'no_op_same_slot' => [
        'rows' => [
            ['id' => 30, 'parent_id' => null, 'position' => 0, 'title' => 'Root', 'meta' => null, 'created_at' => null, 'updated_at' => null],
            ['id' => 31, 'parent_id' => 30, 'position' => 0, 'title' => 'Left', 'meta' => null, 'created_at' => null, 'updated_at' => null],
            ['id' => 32, 'parent_id' => 30, 'position' => 1, 'title' => 'Right', 'meta' => null, 'created_at' => null, 'updated_at' => null],
        ],
        'move' => ['id' => 31, 'parent' => 30, 'position' => 0],
    ],
];

if (!isset($scenarios[$scenario])) {
    fwrite(STDERR, "Unknown scenario: {$scenario}\n");
    exit(1);
}

$scenarioData = $scenarios[$scenario];
$pdo = new FakePDO($scenarioData['rows']);
$move = $scenarioData['move'];

$status = 'ok';
$error = null;

try {
    definitions_move($pdo, (int) $move['id'], $move['parent'] === null ? null : (int) $move['parent'], (int) $move['position']);
} catch (Throwable $e) {
    $status = 'error';
    $error = $e->getMessage();
}

$rows = array_values($pdo->rows);
usort($rows, static function (array $a, array $b): int {
    $parentA = $a['parent_id'];
    $parentB = $b['parent_id'];
    if ($parentA === $parentB) {
        if ($a['position'] === $b['position']) {
            return $a['id'] <=> $b['id'];
        }
        return $a['position'] <=> $b['position'];
    }
    if ($parentA === null) {
        return -1;
    }
    if ($parentB === null) {
        return 1;
    }
    return $parentA <=> $parentB;
});

$output = [
    'status' => $status,
    'error' => $error,
    'rows' => $rows,
    'operations' => $pdo->operations,
    'executions' => $pdo->executions,
];

echo json_encode($output, JSON_PRETTY_PRINT);
