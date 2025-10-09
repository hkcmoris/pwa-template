<?php

declare(strict_types=1);

/**
 * @param array{
 *     summary?: array{totalComponents?: int},
 *     createForm?: array{
 *         definitionsFlat?: array<int, array<string, mixed>>,
 *         componentsFlat?: array<int, array<string, mixed>>
 *     },
 *     listHtml?: array{
 *         componentsPage?: array<int, array<string, mixed>>,
 *         componentPageSize?: int,
 *         totalComponents?: int,
 *         nextOffset?: int,
 *         hasMore?: bool
 *     },
 *     message?: array{content?: string|null, type?: string}
 * } $viewModel
 */
function components_render_fragments(array $viewModel): void
{
    $summary = $viewModel['summary'] ?? [];
    $createForm = $viewModel['createForm'] ?? [];
    $list = $viewModel['listHtml'] ?? [];
    $message = $viewModel['message'] ?? [];

    $BASE = rtrim((defined('BASE_PATH') ? BASE_PATH : ''), '/');

    $definitionsFlat = $createForm['definitionsFlat'] ?? [];
    $componentsFlat = $createForm['componentsFlat'] ?? [];
    $componentsPage = $list['componentsPage'] ?? [];
    $componentPageSize = isset($list['componentPageSize']) ? (int) $list['componentPageSize'] : 50;
    $totalComponents = isset($summary['totalComponents'])
        ? (int) $summary['totalComponents']
        : (int) ($list['totalComponents'] ?? 0);
    $nextOffset = isset($list['nextOffset']) ? (int) $list['nextOffset'] : count($componentsPage);
    $hasMore = isset($list['hasMore']) ? (bool) $list['hasMore'] : ($nextOffset < $totalComponents);

    $messageContent = $message['content'] ?? null;
    $messageType = $message['type'] ?? 'success';

    include __DIR__ . '/partials/components-list.php';

    ob_start();
    include __DIR__ . '/partials/components-create-form.php';
    $formMarkup = ob_get_clean();

    if ($formMarkup === false) {
        $formMarkup = '';
    }

    echo '<template id="component-create-template" hx-swap-oob="true">' . $formMarkup . '</template>';
    echo '<div id="component-summary" hx-swap-oob="true" class="component-summary">' .
        '<p><strong>Celkem komponent:</strong> ' . $totalComponents . '</p></div>';

    $class = 'form-feedback';
    if ($messageContent) {
        $class .= $messageType === 'error' ? ' form-feedback--error' : ' form-feedback--success';
    } else {
        $class .= ' hidden';
    }
    $safeMessage = $messageContent ? htmlspecialchars($messageContent, ENT_QUOTES, 'UTF-8') : '';
    echo '<div id="component-form-errors" hx-swap-oob="true" class="' . $class .
        '" role="status" aria-live="polite">' . $safeMessage . '</div>';
}
