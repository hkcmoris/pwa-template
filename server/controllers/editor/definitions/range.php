<?php

use Definitions\Repository;
use RuntimeException;
use Throwable;

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../../../views/editor/definitions-response.php';

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
    header('Vary: HX-Request, HX-Boosted, X-Requested-With, Cookie');
}

$user = app_get_current_user();
$role = $user['role'] ?? 'guest';

if (!in_array($role, ['admin', 'superadmin'], true)) {
    http_response_code(403);
    echo '<div id="definitions-list"></div>';
    echo '<div id="definition-form-errors"'
        . ' hx-swap-oob="true"'
        . ' class="form-feedback form-feedback--error"'
        . '>'
        . 'Nemáte oprávnění spravovat definice.'
        . '</div>';
    return;
}

$pdo = get_db_connection();
$repository = new Repository($pdo);

$idParam = $_POST['id'] ?? '';
$mode = $_POST['mode'] ?? 'set';

if (!preg_match('/^\d+$/', (string) $idParam)) {
    http_response_code(422);
    definitions_render_fragments($pdo, [
        'message' => 'Neplatné ID definice.',
        'message_type' => 'error',
    ]);
    return;
}

$id = (int) $idParam;

if ($mode === 'clear') {
    try {
        $repository->updateValueRange($id, null, null);
        definitions_render_fragments($pdo, [
            'message' => 'Rozsah byl odstraněn.',
            'message_type' => 'success',
        ]);
    } catch (RuntimeException $e) {
        http_response_code(422);
        definitions_render_fragments($pdo, [
            'message' => $e->getMessage(),
            'message_type' => 'error',
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        definitions_render_fragments($pdo, [
            'message' => 'Uložení rozsahu se nezdařilo.',
            'message_type' => 'error',
        ]);
    }

    return;
}

$valueMin = $_POST['value_min'] ?? null;
$valueMax = $_POST['value_max'] ?? null;

$normalise = static function ($value): ?string {
    if ($value === null) {
        return null;
    }

    if (is_string($value)) {
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return null;
};

$rawMin = $normalise($valueMin);
$rawMax = $normalise($valueMax);

$parseInteger = static function (?string $value, string $label): array {
    if ($value === null) {
        return [null, null];
    }

    if (preg_match('/^-?\d+$/', $value) !== 1) {
        return [null, "Pole {$label} musí být celé číslo."];
    }

    return [(int) $value, null];
};

[$min, $minError] = $parseInteger($rawMin, 'minimální hodnota');
if ($minError !== null) {
    http_response_code(422);
    definitions_render_fragments($pdo, [
        'message' => $minError,
        'message_type' => 'error',
    ]);
    return;
}

[$max, $maxError] = $parseInteger($rawMax, 'maximální hodnota');
if ($maxError !== null) {
    http_response_code(422);
    definitions_render_fragments($pdo, [
        'message' => $maxError,
        'message_type' => 'error',
    ]);
    return;
}

if ($min === null && $max === null) {
    http_response_code(422);
    definitions_render_fragments($pdo, [
        'message' => 'Zadejte alespoň jednu hranici rozsahu.',
        'message_type' => 'error',
    ]);
    return;
}

if ($min !== null && $max !== null && $min > $max) {
    http_response_code(422);
    definitions_render_fragments($pdo, [
        'message' => 'Minimální hodnota nemůže být větší než maximální.',
        'message_type' => 'error',
    ]);
    return;
}

try {
    $repository->updateValueRange($id, $min, $max);
    definitions_render_fragments($pdo, [
        'message' => 'Rozsah byl uložen.',
        'message_type' => 'success',
    ]);
} catch (RuntimeException $e) {
    http_response_code(422);
    definitions_render_fragments($pdo, [
        'message' => $e->getMessage(),
        'message_type' => 'error',
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    definitions_render_fragments($pdo, [
        'message' => 'Uložení rozsahu se nezdařilo.',
        'message_type' => 'error',
    ]);
}
