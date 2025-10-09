<?php

declare(strict_types=1);

use Components\Formatter;
use Components\Repository;
use Definitions\Formatter as DefinitionsFormatter;
use Definitions\Repository as DefinitionsRepository;
use PDO;

/**
 * @param array{message?: string|null, message_type?: string} $options
 */
function components_render_fragments(\PDO $pdo, array $options = []): void
{
    $formatter = new Formatter();
    $repository = new Repository($pdo, $formatter);
    $componentPageSize = 50;
    $totalComponents = $repository->countAll();
    $componentsPage = $repository->fetchRows($componentPageSize, 0);
    $componentsTree = $repository->fetchTree();
    $componentsFlat = $formatter->flattenTree($componentsTree);
    $definitionsFormatter = new DefinitionsFormatter();
    $definitionsRepository = new DefinitionsRepository($pdo);
    $definitionsTree = $definitionsRepository->fetchTree($definitionsFormatter);
    $definitionsFlat = $definitionsFormatter->flattenTree($definitionsTree);

    $BASE = rtrim((defined('BASE_PATH') ? BASE_PATH : ''), '/');
    $message = $options['message'] ?? null;
    $messageType = $options['message_type'] ?? 'success';

    include __DIR__ . '/partials/components-list.php';

    ob_start();
    include __DIR__ . '/partials/components-create-form.php';
    $formMarkup = ob_get_clean();

    echo '<template id="component-create-template" hx-swap-oob="true">' . $formMarkup . '</template>';
    echo '<div id="component-summary" hx-swap-oob="true" class="component-summary">' .
        '<p><strong>Celkem komponent:</strong> ' . $totalComponents . '</p></div>';
    $class = 'form-feedback';
    if ($message) {
        $class .= $messageType === 'error' ? ' form-feedback--error' : ' form-feedback--success';
    } else {
        $class .= ' hidden';
    }
    $safeMessage = $message ? htmlspecialchars($message, ENT_QUOTES, 'UTF-8') : '';
    echo '<div id="component-form-errors" hx-swap-oob="true" class="' . $class .
        '" role="status" aria-live="polite">' . $safeMessage . '</div>';
}
