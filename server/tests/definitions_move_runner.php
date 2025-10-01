<?php

declare(strict_types=1);

use App\Tests\Support\FakePDO;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/definitions.php';
require_once __DIR__ . '/Support/FakePDO.php';
require_once __DIR__ . '/Support/FakeStatement.php';

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
    'chained_reparenting_preserves_children' => [
        'rows' => [
            ['id' => 0, 'parent_id' => null, 'position' => 0, 'title' => 'Root A', 'meta' => null, 'created_at' => null, 'updated_at' => null],
            ['id' => 1, 'parent_id' => 0, 'position' => 0, 'title' => 'Child A1', 'meta' => null, 'created_at' => null, 'updated_at' => null],
            ['id' => 2, 'parent_id' => 0, 'position' => 1, 'title' => 'Child A2', 'meta' => null, 'created_at' => null, 'updated_at' => null],
            ['id' => 3, 'parent_id' => null, 'position' => 1, 'title' => 'Root B', 'meta' => null, 'created_at' => null, 'updated_at' => null],
            ['id' => 4, 'parent_id' => 3, 'position' => 0, 'title' => 'Child B1', 'meta' => null, 'created_at' => null, 'updated_at' => null],
            ['id' => 5, 'parent_id' => 3, 'position' => 1, 'title' => 'Child B2', 'meta' => null, 'created_at' => null, 'updated_at' => null],
            ['id' => 6, 'parent_id' => 3, 'position' => 2, 'title' => 'Child B3', 'meta' => null, 'created_at' => null, 'updated_at' => null],
            ['id' => 7, 'parent_id' => null, 'position' => 2, 'title' => 'Root C', 'meta' => null, 'created_at' => null, 'updated_at' => null],
            ['id' => 8, 'parent_id' => 7, 'position' => 0, 'title' => 'Child C1', 'meta' => null, 'created_at' => null, 'updated_at' => null],
            ['id' => 9, 'parent_id' => null, 'position' => 3, 'title' => 'Root D', 'meta' => null, 'created_at' => null, 'updated_at' => null],
            ['id' => 10, 'parent_id' => null, 'position' => 4, 'title' => 'Root E', 'meta' => null, 'created_at' => null, 'updated_at' => null],
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
$moves = [];
if (isset($scenarioData['moves']) && is_array($scenarioData['moves'])) {
    $moves = $scenarioData['moves'];
} elseif (isset($scenarioData['move'])) {
    $moves = [$scenarioData['move']];
}

$status = 'ok';
$error = null;
$snapshots = [];
try {
    foreach ($moves as $move) {
        if (!is_array($move)) {
            continue;
        }
        $id = isset($move['id']) ? (int) $move['id'] : 0;
        $parent = null;
        if (array_key_exists('parent', $move) && $move['parent'] !== null) {
            $parent = (int) $move['parent'];
        }
        $position = isset($move['position']) ? (int) $move['position'] : 0;
        definitions_move($pdo, $id, $parent, $position);
        if (isset($move['label']) && $move['label'] !== '') {
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
