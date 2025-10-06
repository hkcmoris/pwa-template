<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
use App\Tests\Support\FakePDO;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/Support/FakePDO.php';
require_once __DIR__ . '/Support/FakeStatement.php';

$input = trim(stream_get_contents(STDIN));
$data = $input !== '' ? json_decode($input, true) : null;
if (!is_array($data) || !isset($data['scenario'])) {
    fwrite(STDERR, "Missing scenario input\n");
    exit(1);
}

$scenario = (string) $data['scenario'];

/**
 * Build a canonical row definition used across scenarios.
 *
 * @return array<string,mixed>
 */
$makeRow = static function (int $id, ?int $parentId, int $position, string $title): array {
    return [
        'id' => $id,
        'parent_id' => $parentId,
        'position' => $position,
        'title' => $title,
        'meta' => null,
        'created_at' => null,
        'updated_at' => null,
    ];
};

/**
 * @var array<string, array{
 *     rows: list<array<string, mixed>>,
 *     move?: array{id:int,parent:int|null,position:int,label?:non-empty-string},
 *     moves?: list<array{id:int,parent:int|null,position:int,label?:non-empty-string}>
 * }>
 */
$scenarios = [
    'move_to_new_parent' => [
        'rows' => [
            $makeRow(1, null, 0, 'Root'),
            $makeRow(2, 1, 0, 'Alpha'),
            $makeRow(3, 1, 1, 'Beta'),
            $makeRow(4, null, 1, 'Extra'),
            $makeRow(5, 4, 0, 'Child'),
        ],
        'move' => ['id' => 3, 'parent' => 4, 'position' => 1],
    ],
    'move_within_parent_down' => [
        'rows' => [
            $makeRow(10, null, 0, 'Root'),
            $makeRow(11, 10, 0, 'One'),
            $makeRow(12, 10, 1, 'Two'),
            $makeRow(13, 10, 2, 'Three'),
        ],
        'move' => ['id' => 11, 'parent' => 10, 'position' => 2],
    ],
    'move_to_root' => [
        'rows' => [
            $makeRow(20, null, 0, 'Root'),
            $makeRow(21, 20, 0, 'Child A'),
            $makeRow(22, 20, 1, 'Child B'),
            $makeRow(23, null, 1, 'Sibling Root'),
        ],
        'move' => ['id' => 22, 'parent' => null, 'position' => 5],
    ],
    'no_op_same_slot' => [
        'rows' => [
            $makeRow(30, null, 0, 'Root'),
            $makeRow(31, 30, 0, 'Left'),
            $makeRow(32, 30, 1, 'Right'),
        ],
        'move' => ['id' => 31, 'parent' => 30, 'position' => 0],
    ],
    'chained_reparenting_preserves_children' => [
        'rows' => [
            $makeRow(0, null, 0, 'Root A'),
            $makeRow(1, 0, 0, 'Child A1'),
            $makeRow(2, 0, 1, 'Child A2'),
            $makeRow(3, null, 1, 'Root B'),
            $makeRow(4, 3, 0, 'Child B1'),
            $makeRow(5, 3, 1, 'Child B2'),
            $makeRow(6, 3, 2, 'Child B3'),
            $makeRow(7, null, 2, 'Root C'),
            $makeRow(8, 7, 0, 'Child C1'),
            $makeRow(9, null, 3, 'Root D'),
            $makeRow(10, null, 4, 'Root E'),
        ],
        'moves' => [
            ['id' => 10, 'parent' => 9, 'position' => 0, 'label' => 'after_first'],
            ['id' => 9, 'parent' => 7, 'position' => 0, 'label' => 'after_second'],
            ['id' => 10, 'parent' => 3, 'position' => 2, 'label' => 'final'],
        ],
    ],
];
if (!isset($scenarios[$scenario])) {
    fwrite(STDERR, "Unknown scenario: {$scenario}\n");
    exit(1);
}

$scenarioData = $scenarios[$scenario];
$pdo = new FakePDO($scenarioData['rows']);
/**
 * @param array<int,array<string,mixed>> $rows
 * @return array<int,array<string,mixed>>
 */
$sortRows = static function (array $rows): array {
    $normalized = array_values($rows);
    usort($normalized, static function (array $a, array $b): int {
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
    return $normalized;
};
/**
 * @var list<array{id:int,parent:int|null,position:int,label?:non-empty-string}> $moves
 */
$moves = [];
if (isset($scenarioData['moves'])) {
    $moves = $scenarioData['moves'];
} elseif (isset($scenarioData['move'])) {
    $moves = [$scenarioData['move']];
}

$status = 'ok';
$error = null;
$snapshots = [];
try {
    foreach ($moves as $move) {
        $id = (int) $move['id'];
        $parentValue = $move['parent'] ?? null;
        $parent = $parentValue === null ? null : (int) $parentValue;
        $position = (int) $move['position'];
        definitions_move($pdo, $id, $parent, $position);
        if (isset($move['label'])) {
            $snapshots[(string) $move['label']] = $sortRows($pdo->rows);
        }
    }
} catch (Throwable $e) {
    $status = 'error';
    $error = $e->getMessage();
}

$rows = $sortRows($pdo->rows);
$output = [
    'status' => $status,
    'error' => $error,
    'rows' => $rows,
    'operations' => $pdo->operations,
    'executions' => $pdo->executions,
];
if ($snapshots !== []) {
    $output['snapshots'] = $snapshots;
}

echo json_encode($output, JSON_PRETTY_PRINT);
