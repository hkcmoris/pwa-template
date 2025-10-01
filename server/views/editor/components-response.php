<?php

require_once __DIR__ . '/../../lib/components.php';
require_once __DIR__ . '/../../lib/definitions.php';

function components_render_fragments(PDO $pdo, array $options = []): void
{

    $componentsTree = components_fetch_tree($pdo);
    $componentsFlat = components_flatten_tree($componentsTree);
    $definitionsTree = definitions_fetch_tree($pdo);
    $definitionsFlat = definitions_flatten_tree($definitionsTree);
    $BASE = rtrim((defined('BASE_PATH') ? BASE_PATH : ''), '/');
    $message = $options['message'] ?? null;
    $messageType = $options['message_type'] ?? 'success';
    include __DIR__ . '/partials/components-tree.php';
    ob_start();
    include __DIR__ . '/partials/components-create-form.php';
    $formMarkup = ob_get_clean();
    echo '<template id="component-create-template" hx-swap-oob="true">' . $formMarkup . '</template>';
    $totalCount = count($componentsFlat);
    echo '<div id="component-summary" hx-swap-oob="true" class="component-summary">' .
        '<p><strong>Celkem komponent:</strong> ' . $totalCount . '</p></div>';
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
