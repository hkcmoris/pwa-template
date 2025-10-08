<?php

declare(strict_types=1);

use Definitions\Formatter;
use Definitions\Repository;

/** @param array<string, mixed> $options */
function definitions_render_fragments(PDO $pdo, array $options = []): void
{
    $repository = new Repository($pdo);
    $formatter = new Formatter();
    $definitionsTree = $repository->fetchTree($formatter);
    $definitionsFlat = $formatter->flattenTree($definitionsTree);
    $selectedParent = $options['selected_parent'] ?? null;
    $message = $options['message'] ?? null;
    $messageType = $options['message_type'] ?? 'success';
    $BASE = rtrim((defined('BASE_PATH') ? BASE_PATH : ''), '/');

    $hxOnSelect = 'select:change: const hidden=this.querySelector(\'#definition-parent-value\'); ' .
        'if(hidden){const raw=(event.detail && event.detail.value) || \'\'; hidden.value=raw;}';

    include __DIR__ . '/partials/definitions-tree.php';

    ob_start();
    include __DIR__ . '/partials/definitions-parent-select.php';
    $parentSelectInner = ob_get_clean();

    echo '<div'
        . ' id="definition-parent-select"'
        . ' class="definition-parent-select"'
        . ' data-island="select"'
        . ' hx-swap-oob="true"'
        . ' hx-on="' . htmlspecialchars($hxOnSelect, ENT_QUOTES, 'UTF-8') . '"'
        . ' aria-hidden="true"'
        . ' style="display:none"'
        . '>'
        . $parentSelectInner
        . '</div>';

    $class = 'form-feedback';
    if ($message) {
        $class .= $messageType === 'error' ? ' form-feedback--error' : ' form-feedback--success';
    } else {
        $class .= ' hidden';
    }
    $safeMessage = $message ? htmlspecialchars($message, ENT_QUOTES, 'UTF-8') : '';
    echo '<div'
        . ' id="definition-form-errors"'
        . ' hx-swap-oob="true"'
        . ' class="' . $class . '"'
        . ' role="status"'
        . ' aria-live="polite"'
        . '>'
        . $safeMessage
        . '</div>';
}
