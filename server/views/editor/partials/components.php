<?php

// Normalize base path for view usage.
$baseCandidate = defined('BASE_PATH') ? (string) BASE_PATH : '';
$BASE = isset($BASE) && $BASE !== '' ? (string) $BASE : $baseCandidate;
$BASE = rtrim($BASE, '/');

$componentSummaryData = $componentSummaryData ?? [];
$componentCreateData = $componentCreateData ?? [];
$componentListData = $componentListData ?? [];

$definitionsFlat = $componentCreateData['definitionsFlat'] ?? [];
$componentsFlat = $componentCreateData['componentsFlat'] ?? [];
$componentsPage = $componentListData['componentsPage'] ?? [];
$componentPageSize = isset($componentListData['componentPageSize'])
    ? (int) $componentListData['componentPageSize']
    : 50;
$totalComponents = isset($componentSummaryData['totalComponents'])
    ? (int) $componentSummaryData['totalComponents']
    : (int) ($componentListData['totalComponents'] ?? count($componentsPage));
$nextOffset = isset($componentListData['nextOffset'])
    ? (int) $componentListData['nextOffset']
    : count($componentsPage);
$hasMore = isset($componentListData['hasMore'])
    ? (bool) $componentListData['hasMore']
    : ($nextOffset < $totalComponents);

$componentStyleEntry = 'src/styles/editor/components.css';
if (isset($_SERVER['HTTP_HX_REQUEST'])) {
    $componentCssHref = vite_asset_href($componentStyleEntry, $isDevEnv ?? false, $BASE);
    if ($componentCssHref !== null) {
        ?>
        <link
            rel="stylesheet"
            id="editor-partial-style"
            href="<?= htmlspecialchars($componentCssHref, ENT_QUOTES, 'UTF-8') ?>"
            hx-swap-oob="true">
        <?php
    }
}
?>

<div
    id="components-root"
    data-island="components"
    data-base="<?= htmlspecialchars($BASE) ?>">
    <h2>Komponenty</h2>
    <p style="max-width:640px">
        Komponenty rozšiřují definice konfigurátoru o konkrétní stavební bloky.
        Každá komponenta vychází z vybrané definice, může mít vlastní hierarchii
        a ukládá popis, obrázek i závislosti na dalších volbách.
    </p>
    <div class="component-toolbar">
        <button type="button" id="component-open-create" class="component-primary">Přidat komponentu</button>
    </div>
    <div id="component-form-errors" class="form-feedback hidden" role="status" aria-live="polite"></div>
    <div id="component-summary" class="component-summary">
        <p><strong>Celkem komponent:</strong> <?= $totalComponents ?></p>
    </div>
    <template id="component-create-template">
        <?php include __DIR__ . '/components-create-form.php'; ?>
    </template>
    <div id="components-modal" class="components-modal hidden" aria-hidden="true"></div>
    <?php
    include __DIR__ . '/components-list.php';
    ?>
</div>
