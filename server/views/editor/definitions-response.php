<?php

require_once __DIR__ . '/../../lib/definitions.php';

function definitions_render_fragments(PDO $pdo, array $options = []): void
{
    $definitionsTree = definitions_fetch_tree($pdo);
    $definitionsFlat = definitions_flatten_tree($definitionsTree);
    $selectedParent = $options['selected_parent'] ?? null;
    $message = $options['message'] ?? null;
    $messageType = $options['message_type'] ?? 'success';
    $BASE = rtrim((defined('BASE_PATH') ? BASE_PATH : ''), '/');

    include __DIR__ . '/partials/definitions-tree.php';

    ob_start();
    include __DIR__ . '/partials/definitions-parent-select.php';
    $parentSelectInner = ob_get_clean();

    echo '<div id="definition-parent-select" class="definition-parent-select" data-island="select" hx-swap-oob="true" hx-on="select:change: const hidden=this.querySelector(&#39;#definition-parent-value&#39;); if(hidden){const raw=(event.detail && event.detail.value) || &#39;&#39;; hidden.value=raw;}" aria-hidden="true" style="display:none">' . $parentSelectInner . '</div>';

    $class = 'form-feedback';
    if ($message) {
        $class .= $messageType === 'error' ? ' form-feedback--error' : ' form-feedback--success';
    } else {
        $class .= ' hidden';
    }
    $safeMessage = $message ? htmlspecialchars($message, ENT_QUOTES, 'UTF-8') : '';
    echo '<div id="definition-form-errors" hx-swap-oob="true" class="' . $class . '" role="status" aria-live="polite">' . $safeMessage . '</div>';
}
