<?php

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../lib/auth.php';
require_once __DIR__ . '/../../../lib/db.php';
require_once __DIR__ . '/../../../lib/logger.php';
require_once __DIR__ . '/../../../lib/components.php';
require_once __DIR__ . '/../../../lib/definitions.php';
require_once __DIR__ . '/../../../views/editor/components-response.php';
log_message('Components create request received', 'INFO');
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
    header('Vary: HX-Request, HX-Boosted, X-Requested-With, Cookie');
}

$user = app_get_current_user();
$role = $user['role'] ?? 'guest';
if (!in_array($role, ['admin', 'superadmin'], true)) {
    http_response_code(403);
    echo '<div id="components-list"></div>';
    echo '<div id="component-form-errors" hx-swap-oob="true" class="form-feedback form-feedback--error">' .
        'Nemáte oprávnění spravovat komponenty.' .
        '</div>';
    return;
}

$pdo = get_db_connection();
$definitionParam = $_POST['definition_id'] ?? '';
$parentParam = $_POST['parent_id'] ?? '';
$alternateTitle = isset($_POST['alternate_title']) ? trim((string) $_POST['alternate_title']) : '';
$description = isset($_POST['description']) ? trim((string) $_POST['description']) : '';
$image = isset($_POST['image']) ? trim((string) $_POST['image']) : '';
$color = isset($_POST['color']) ? trim((string) $_POST['color']) : '';
$mediaType = isset($_POST['media_type']) ? (string) $_POST['media_type'] : 'image';
$positionParam = isset($_POST['position']) ? trim((string) $_POST['position']) : '';
$priceParam = isset($_POST['price']) ? trim((string) $_POST['price']) : '';
$mediaType = $mediaType === 'color' ? 'color' : 'image';
$errors = [];
$definitionId = null;
$parentId = null;
$position = null;
$priceValue = null;
if ($definitionParam === '' || !preg_match('/^\d+$/', (string) $definitionParam)) {
    $errors[] = 'Vyberte prosím platnou definici.';
} else {
    $definitionId = (int) $definitionParam;
    if (!definitions_find($pdo, $definitionId)) {
        $errors[] = 'Zvolená definice neexistuje.';
    }
}

if ($alternateTitle !== '' && mb_strlen($alternateTitle, 'UTF-8') > 191) {
    $errors[] = 'Alternativní název může mít maximálně 191 znaků.';
}

if ($description !== '' && mb_strlen($description, 'UTF-8') > 1000) {
    $errors[] = 'Popis může mít maximálně 1000 znaků.';
}

if ($mediaType === 'image') {
    if ($image !== '' && mb_strlen($image, 'UTF-8') > 255) {
        $errors[] = 'Cesta k obrázku je příliš dlouhá (max 255 znaků).';
    }
    $color = '';
} else {
    $image = '';
    if ($color === '') {
        $errors[] = 'Zadejte barvu komponenty.';
    } elseif (!preg_match('/^#(?:[0-9A-Fa-f]{3}){1,2}$/', $color)) {
        $errors[] = 'Barva musí být ve formátu HEX (#RGB nebo #RRGGBB).';
    }
    if ($color !== '' && mb_strlen($color, 'UTF-8') > 21) {
        $errors[] = 'Hodnota barvy je příliš dlouhá.';
    }
}

if ($parentParam !== '') {
    if (!preg_match('/^\d+$/', (string) $parentParam)) {
        $errors[] = 'Vybraný rodič není platný.';
    } else {
        $parentId = (int) $parentParam;
        if ($parentId <= 0 || !components_parent_exists($pdo, $parentId)) {
            $errors[] = 'Zvolená rodičovská komponenta neexistuje.';
        }
    }
}

if ($positionParam !== '') {
    if (!preg_match('/^\d+$/', $positionParam)) {
        $errors[] = 'Pozice musí být nezáporné číslo.';
    } else {
        $position = (int) $positionParam;
    }
}

[$priceValue, $priceError] = components_normalise_price_input($priceParam);
if ($priceError !== null) {
    $errors[] = $priceError;
}

if (!empty($errors)) {
    http_response_code(422);
    components_render_fragments($pdo, [
        'message' => implode(' ', $errors),
        'message_type' => 'error',
    ]);
    return;
}

if ($definitionId === null) {
    http_response_code(422);
    components_render_fragments($pdo, [
        'message' => 'Vyberte prosím definici.',
        'message_type' => 'error',
    ]);
    return;
}

if ($position === null) {
    $position = components_next_position($pdo, $parentId);
}

try {
    components_create(
        $pdo,
        $definitionId,
        $parentId,
        $alternateTitle !== '' ? $alternateTitle : null,
        $description !== '' ? $description : null,
        $image !== '' ? $image : null,
        $color !== '' ? strtoupper($color) : null,
        $position,
        $priceValue,
        'CZK'
    );
    http_response_code(201);
    components_render_fragments($pdo, [
        'message' => 'Komponenta byla uložena.',
        'message_type' => 'success',
    ]);
} catch (Throwable $e) {
    log_message('Component creation failed: ' . $e->getMessage(), 'ERROR');
    http_response_code(500);
    components_render_fragments($pdo, [
        'message' => 'Komponentu se nepodařilo uložit.',
        'message_type' => 'error',
    ]);
}
