<?php

use Components\Formatter;
use Components\Repository;
use Definitions\Formatter as DefinitionsFormatter;
use Definitions\Repository as DefinitionsRepository;
use Editor\ComponentPresenter;

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../../../views/editor/components-response.php';

log_message('Components update request received', 'INFO');
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
    header('Vary: HX-Request, HX-Boosted, X-Requested-With, Cookie');
}

$user = app_get_current_user();
$role = $user['role'] ?? 'guest';
if (!in_array($role, ['admin', 'superadmin'], true)) {
    http_response_code(403);
    echo '<div id="components-list-wrapper"></div>';
    echo '<div id="component-form-errors" hx-swap-oob="true" class="form-feedback form-feedback--error">' .
        'Nemáte oprávnění spravovat komponenty.' .
        '</div>';
    return;
}

$pdo = get_db_connection();
$formatter = new Formatter();
$definitionsFormatter = new DefinitionsFormatter();
$definitionsRepository = new DefinitionsRepository($pdo);
$repository = new Repository($pdo, $formatter, $definitionsRepository);
$presenter = new ComponentPresenter($repository, $formatter, $definitionsRepository, $definitionsFormatter);

$componentParam = $_POST['component_id'] ?? '';
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
$componentId = null;
$definitionId = null;
$parentId = null;
$position = null;
$priceValue = null;

if ($componentParam === '' || !preg_match('/^\d+$/', (string) $componentParam)) {
    $errors[] = 'Vyberte prosím platnou komponentu.';
} else {
    $componentId = (int) $componentParam;
    if (!$repository->find($componentId)) {
        http_response_code(404);
        $viewModel = $presenter->presentInitial([
            'message' => 'Komponentu se nepodařilo najít.',
            'message_type' => 'error',
        ]);
        components_render_fragments($viewModel);
        return;
    }
}

if ($definitionParam === '' || !preg_match('/^\d+$/', (string) $definitionParam)) {
    $errors[] = 'Vyberte prosím platnou definici.';
} else {
    $definitionId = (int) $definitionParam;
    if (!$definitionsRepository->find($definitionId)) {
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
    }
}

if ($componentId !== null && $parentId !== null) {
    if ($componentId === $parentId) {
        $errors[] = 'Komponenta nemůže být sama sobě rodičem.';
    } elseif (!$repository->parentExists($parentId)) {
        $errors[] = 'Zvolená rodičovská komponenta neexistuje.';
    } elseif ($repository->isDescendant($componentId, $parentId)) {
        $errors[] = 'Nelze přesunout komponentu pod jejího potomka.';
    }
}

if ($positionParam !== '') {
    if (!preg_match('/^\d+$/', $positionParam)) {
        $errors[] = 'Pozice musí být nezáporné číslo.';
    } else {
        $position = (int) $positionParam;
    }
}

[$priceValue, $priceError] = $formatter->normalisePriceInput($priceParam);
if ($priceError !== null) {
    $errors[] = $priceError;
}

if (!empty($errors)) {
    http_response_code(422);
    $viewModel = $presenter->presentInitial([
        'message' => implode(' ', $errors),
        'message_type' => 'error',
    ]);
    components_render_fragments($viewModel);
    return;
}

if ($componentId === null || $definitionId === null) {
    http_response_code(422);
    $viewModel = $presenter->presentInitial([
        'message' => 'Komponentu se nepodařilo zpracovat.',
        'message_type' => 'error',
    ]);
    components_render_fragments($viewModel);
    return;
}

try {
    $repository->update(
        $componentId,
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
    $viewModel = $presenter->presentInitial([
        'message' => 'Komponenta byla aktualizována.',
        'message_type' => 'success',
    ]);
    components_render_fragments($viewModel);
} catch (Throwable $e) {
    log_message('Component update failed: ' . $e->getMessage(), 'ERROR');
    http_response_code(500);
    $viewModel = $presenter->presentInitial([
        'message' => 'Komponentu se nepodařilo aktualizovat.',
        'message_type' => 'error',
    ]);
    components_render_fragments($viewModel);
}
