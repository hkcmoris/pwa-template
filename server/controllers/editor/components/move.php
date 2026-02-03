<?php

use Components\Formatter;
use Components\Repository;
use Components\ValidationException;
use Definitions\Formatter as DefinitionsFormatter;
use Definitions\Repository as DefinitionsRepository;
use Editor\ComponentPresenter;

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../../../views/editor/components-response.php';

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
    header('Vary: HX-Request, HX-Boosted, X-Requested-With, Cookie');
}

$wrapper_id = 'components-list-wrapper';
$errors_container_id = 'component-form-errors';

$user = app_get_current_user();
$role = $user['role'] ?? 'guest';
if (!in_array($role, ['admin', 'superadmin'], true)) {
    http_response_code(403);
    echo '<div id="' . $wrapper_id . '"></div>';
    echo '<div id="' . $errors_container_id . '"'
        . ' hx-swap-oob="true"'
        . ' class="form-feedback form-feedback--error"'
        . '>'
        . 'Nemáte oprávnění spravovat komponenty.'
        . '</div>';
    return;
}

$pdo = get_db_connection();
$formatter = new Formatter();
$definitionsFormatter = new DefinitionsFormatter();
$definitionsRepository = new DefinitionsRepository($pdo);
$repository = new Repository($pdo, $formatter, $definitionsRepository);
$presenter = new ComponentPresenter(
    $repository,
    $formatter,
    $definitionsRepository,
    $definitionsFormatter,
    EDITOR_COMPONENT_PAGE_SIZE
);

$idParam = $_POST['component_id'] ?? $_POST['id'] ?? '';
$parentParam = null;
if (isset($_POST['parent_id'])) {
    $rawParentValue = $_POST['parent_id'];
    if (is_scalar($rawParentValue)) {
        $trimmedParent = trim((string) $rawParentValue);
        if ($trimmedParent !== '') {
            $parentParam = $trimmedParent;
        }
    }
}
$positionParam = $_POST['position'] ?? '';

if (!preg_match('/^\d+$/', (string) $idParam)) {
    http_response_code(422);
    $viewModel = $presenter->presentInitial([
        'message' => 'Neplatné ID komponenty.',
        'message_type' => 'error',
    ]);
    components_render_fragments($viewModel);
    return;
}

$id = (int) $idParam;
$parentId = null;
if ($parentParam !== null) {
    if (!preg_match('/^\d+$/', (string) $parentParam)) {
        http_response_code(422);
        $viewModel = $presenter->presentInitial([
            'message' => 'Neplatný rodič.',
            'message_type' => 'error',
        ]);
        components_render_fragments($viewModel);
        return;
    }
    $parentId = (int) $parentParam;
}

if (!preg_match('/^\d+$/', (string) $positionParam)) {
    http_response_code(422);
    $viewModel = $presenter->presentInitial([
        'message' => 'Neplatná pozice.',
        'message_type' => 'error',
    ]);
    components_render_fragments($viewModel);
    return;
}

$position = (int) $positionParam;

try {
    $repository->move($id, $parentId, $position);
    $viewModel = $presenter->presentInitial([
        'message' => 'Komponenta byla přesunuta.',
        'message_type' => 'success',
    ]);
    components_render_fragments($viewModel);
} catch (ValidationException $e) {
    http_response_code(422);
    $viewModel = $presenter->presentInitial([
        'message' => $e->getMessage(),
        'message_type' => 'error',
    ]);
    components_render_fragments($viewModel);
} catch (RuntimeException $e) {
    log_message('Component move RuntimeException: ' . $e->getMessage(), 'ERROR');
    http_response_code(200);
    $viewModel = $presenter->presentInitial([
        'message' => 'Komponentu se nepodařilo najít. ' . $e->getMessage(),
        'message_type' => 'error',
    ]);
    components_render_fragments($viewModel);
} catch (Throwable $e) {
    log_message('Component move failed: ' . $e->getMessage(), 'ERROR');
    http_response_code(500);
    $viewModel = $presenter->presentInitial([
        'message' => 'Přesun komponenty se nepodařil.',
        'message_type' => 'error',
    ]);
    components_render_fragments($viewModel);
}
