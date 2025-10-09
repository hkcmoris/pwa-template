<?php

use Components\Formatter;
use Components\Repository;
use Definitions\Formatter as DefinitionsFormatter;
use Definitions\Repository as DefinitionsRepository;
use Editor\ComponentPresenter;

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../../../views/editor/components-response.php';

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
        'Nemate opravneni spravovat komponenty.' .
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
if (!preg_match('/^\d+$/', (string) $componentParam)) {
    http_response_code(422);
    $viewModel = $presenter->presentInitial([
        'message' => 'Neplatne ID komponenty.',
        'message_type' => 'error',
    ]);
    components_render_fragments($viewModel);
    return;
}

$componentId = (int) $componentParam;

try {
    $repository->delete($componentId);
    $viewModel = $presenter->presentInitial([
        'message' => 'Komponenta byla odstranena.',
        'message_type' => 'success',
    ]);
    components_render_fragments($viewModel);
} catch (RuntimeException $e) {
    http_response_code(404);
    $viewModel = $presenter->presentInitial([
        'message' => 'Komponentu se nepodarilo najit.',
        'message_type' => 'error',
    ]);
    components_render_fragments($viewModel);
} catch (Throwable $e) {
    log_message('Component delete failed: ' . $e->getMessage(), 'ERROR');
    http_response_code(500);
    $viewModel = $presenter->presentInitial([
        'message' => 'Odstraneni komponenty se nepodarilo.',
        'message_type' => 'error',
    ]);
    components_render_fragments($viewModel);
}
