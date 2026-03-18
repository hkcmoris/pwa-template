<?php

declare(strict_types=1);

use Administration\Repository as AdministrationRepository;
use Components\Repository as ComponentsRepository;
use Configuration\RuleEngine;
use Configuration\WizardRepository;
use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Output\Destination;

require_once __DIR__ . '/../../../bootstrap.php';

$user = app_get_current_user();
$role = $user['role'] ?? 'guest';
if (!in_array($role, ['user', 'admin', 'superadmin'], true)) {
    http_response_code(403);
    exit;
}

$userId = isset($user['id']) ? (int) $user['id'] : 0;
if ($userId <= 0) {
    http_response_code(403);
    exit;
}

$configurationId = isset($_GET['configuration_id']) ? (int) $_GET['configuration_id'] : 0;
if ($configurationId <= 0) {
    http_response_code(400);
    echo 'Neplatné ID konfigurace.';
    exit;
}

$pdo = get_db_connection();

$configurationStmt = $pdo->prepare(
    'SELECT id, user_id, title, status, updated_at FROM configurations WHERE id = :id LIMIT 1'
);
$configurationStmt->bindValue(':id', $configurationId, PDO::PARAM_INT);
$configurationStmt->execute();
/** @var array<string, mixed>|false $configuration */
$configuration = $configurationStmt->fetch(PDO::FETCH_ASSOC);

if ($configuration === false || (int) ($configuration['user_id'] ?? 0) !== $userId) {
    http_response_code(404);
    echo 'Konfigurace nebyla nalezena.';
    exit;
}

if (($configuration['status'] ?? 'draft') === 'draft') {
    http_response_code(400);
    echo 'PDF je dostupné pouze pro dokončené konfigurace.';
    exit;
}

$optionsStmt = $pdo->prepare(
    <<<'SQL'
    SELECT
        o.id AS selection_id,
        o.component_id AS selected_component_id,
        o.parent_component_id AS selected_parent_component_id,
        COALESCE(NULLIF(c.alternate_title, ''), d.title) AS option_title,
        COALESCE(parent_d.title, '') AS option_parent_title,
        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(c.images, '$[0]')), '') AS option_image,
        lp.amount AS option_price_amount,
        UPPER(COALESCE(NULLIF(lp.currency, ''), 'CZK')) AS option_price_currency
    FROM configuration_selections o
    INNER JOIN components c ON c.id = o.component_id
    INNER JOIN definitions d ON d.id = c.definition_id
    LEFT JOIN definitions parent_d ON parent_d.id = d.parent_id
    LEFT JOIN (
        SELECT p.component_id, p.amount, p.currency
        FROM prices p
        INNER JOIN (
            SELECT component_id, MAX(created_at) AS max_created_at
            FROM prices
            GROUP BY component_id
        ) latest_price
            ON latest_price.component_id = p.component_id
            AND latest_price.max_created_at = p.created_at
    ) lp ON lp.component_id = c.id
    WHERE o.configuration_id = :configuration_id
    ORDER BY o.id ASC
    SQL
);
$optionsStmt->bindValue(':configuration_id', $configurationId, PDO::PARAM_INT);
$optionsStmt->execute();
/** @var list<array{
 *     selection_id: int|string|null,
 *     selected_component_id: int|string|null,
 *     selected_parent_component_id: int|string|null,
 *     option_title: string|null,
 *     option_parent_title: string|null,
 *     option_image: string|null,
 *     option_price_amount: string|null,
 *     option_price_currency: string|null
 * }> $options
 */
$options = $optionsStmt->fetchAll(PDO::FETCH_ASSOC);

$wizardRepository = new WizardRepository($pdo);
$componentsRepository = new ComponentsRepository($pdo);
$ruleEngine = new RuleEngine();
$selectedPath = $wizardRepository->fetchSelectedPath($configurationId);

/** @var array<int, bool> $singleChoiceSelectionIds */
$singleChoiceSelectionIds = [];
$pathPrefix = [];
foreach ($selectedPath as $selection) {
    $selectionId = isset($selection['id']) ? (int) $selection['id'] : 0;
    $parentComponentId = isset($selection['parent_component_id']) ? (int) $selection['parent_component_id'] : 0;
    $parentComponentId = $parentComponentId > 0 ? $parentComponentId : null;

    $children = $componentsRepository->fetchChildren($parentComponentId);
    $availableCount = 0;
    foreach ($children as $child) {
        if ($ruleEngine->allowsComponent($child, $pathPrefix)) {
            $availableCount++;
        }
    }

    if ($selectionId > 0) {
        $singleChoiceSelectionIds[$selectionId] = $availableCount === 1;
    }

    $pathPrefix[] = $selection;
}

/**
 * Convert stored image URL/path to a local filesystem path for mPDF.
 * Returns '' if not resolvable or not readable.
 */
$resolveLocalImagePath = static function (string $image): string {
    $image = trim($image);
    if ($image === '') {
        return '';
    }

    // Strip query string etc.
    $path = (string) parse_url($image, PHP_URL_PATH);
    if ($path === '') {
        $path = $image;
    }
    $path = rawurldecode($path);
    if ($path[0] !== '/') {
        $path = '/' . ltrim($path, '/');
    }

    /** @var string $basePath */
    $basePath = defined('BASE_PATH') ? (string) BASE_PATH : '';
    if ($basePath === '/') {
        $basePath = '/server';
    }

    $pathVariants = [$path];
    if ($basePath !== '') {
        $basePath = '/' . trim($basePath, '/');
        $prefixedPath = $basePath . $path;
        $strippedPath = str_starts_with($path, $basePath . '/')
            ? substr($path, strlen($basePath))
            : null;

        if ($strippedPath !== null && $strippedPath !== '') {
            $pathVariants[] = $strippedPath;
        }
        if (!str_starts_with($path, $basePath . '/')) {
            $pathVariants[] = $prefixedPath;
        }
    }

    $projectRoot = dirname(__DIR__, 4);
    /** @var array<string, bool> $seenVariants */
    $seenVariants = [];
    foreach ($pathVariants as $variant) {
        if (isset($seenVariants[$variant])) {
            continue;
        }
        $seenVariants[$variant] = true;

        $candidate = $projectRoot . $variant;
        $candidate = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $candidate);

        log_message('Checking image path: ' . $candidate, 'DEBUG');

        if (is_file($candidate) && is_readable($candidate)) {
            return $candidate;
        }
    }

    log_message('Missing image: ' . $path, 'DEBUG');
    return '';
};

/**
 * Return a PDF-safe local image path for mPDF.
 * - Non-WebP images are returned unchanged
 * - WebP images are converted to cached PNG (to preserve transparency)
 * - Cached PNG is reused until source file changes
 */
$ensurePdfSafeImagePath = static function (string $sourcePath): string {
    $sourcePath = trim($sourcePath);
    if ($sourcePath === '' || !is_file($sourcePath) || !is_readable($sourcePath)) {
        return '';
    }

    $extension = strtolower((string) pathinfo($sourcePath, PATHINFO_EXTENSION));

    // Fast path: if it is not WebP, just use original
    if ($extension !== 'webp') {
        return $sourcePath;
    }

    $serverRoot = dirname(__DIR__, 3);
    $cacheDir = $serverRoot . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'pdf-image-cache';

    if (!is_dir($cacheDir) && !@mkdir($cacheDir, 0777, true) && !is_dir($cacheDir)) {
        log_message("Failed to create PDF image cache dir: $cacheDir", 'ERROR');
        return $sourcePath; // fallback
    }

    $mtime = @filemtime($sourcePath);
    $mtimeKey = $mtime !== false ? (string) $mtime : '0';

    // Deterministic cache key: source path + filemtime
    $cacheKey = sha1($sourcePath . '|' . $mtimeKey);
    $targetPath = $cacheDir . DIRECTORY_SEPARATOR . $cacheKey . '.png';

    if (is_file($targetPath) && is_readable($targetPath)) {
        return $targetPath;
    }

    // Try Imagick first if available
    if (class_exists(Imagick::class)) {
        try {
            $imagick = new Imagick();
            $imagick->readImage($sourcePath);

            // Flattening would kill transparency, so don't do that.
            $imagick->setImageFormat('png');
            $imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
            $imagick->thumbnailImage(378, 260, true, true);
            $imagick->stripImage();

            $imagick->writeImage($targetPath);
            $imagick->clear();
            $imagick->destroy();

            if (is_file($targetPath) && is_readable($targetPath)) {
                return $targetPath;
            }
        } catch (\Throwable $e) {
            log_message('Imagick WebP->PNG conversion failed: ' . $e->getMessage(), 'ERROR');
        }
    }

    // Fallback to GD
    if (function_exists('imagecreatefromwebp') && function_exists('imagepng')) {
        try {
            $image = @imagecreatefromwebp($sourcePath);
            if ($image !== false) {
                // Preserve alpha
                imagealphablending($image, false);
                imagesavealpha($image, true);

                // Write PNG with default compression
                if (@imagepng($image, $targetPath) === true) {
                    imagedestroy($image);

                    if (is_file($targetPath) && is_readable($targetPath)) {
                        return $targetPath;
                    }
                }

                imagedestroy($image);
            }
        } catch (\Throwable $e) {
            log_message('GD WebP->PNG conversion failed: ' . $e->getMessage(), 'ERROR');
        }
    }

    // Last resort: return original and accept that mPDF may render artifacts
    log_message("Could not convert WebP for PDF, using original: $sourcePath", 'ERROR');
    return $sourcePath;
};

$repository = new AdministrationRepository($pdo);
$logoSettings = $repository->readLogoSettings();
$companyAddress = $repository->readCompanyAddress();
$logoPath = trim((string) $logoSettings['path']);
if ($logoPath !== '' && $logoPath[0] !== '/') {
    $logoPath = '/' . ltrim($logoPath, '/');
}
$logoLocal = $logoPath !== '' ? $resolveLocalImagePath($logoPath) : '';
$logoPdfSafe = $ensurePdfSafeImagePath($logoLocal);

// ---- Build view model ----
$finalPriceByCurrency = [];
$items = [];

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '';
$origin = ($host !== '') ? ($scheme . '://' . $host) : '';

/** @var list<array{
 *     selection_id: int,
 *     component_id: int,
 *     parent_component_id: int|null,
 *     title: string,
 *     image_pdf_safe: string,
 *     price_label: string,
 *     price_amount: float|null,
 *     price_currency: string
 * }> $optionNodes
 */
$optionNodes = [];

foreach ($options as $option) {
    $title = trim((string)($option['option_title'] ?? ''));
    if ($title === '') {
        $title = 'Option';
    }

    $parentTitle = trim((string) ($option['option_parent_title'] ?? ''));
    if ($parentTitle !== '') {
        $title = $parentTitle . ' — ' . $title;
    }

    $amountRaw = trim((string)($option['option_price_amount'] ?? ''));
    $currency = strtoupper(trim((string)($option['option_price_currency'] ?? 'CZK')));
    if ($currency === '') {
        $currency = 'CZK';
    }

    $amount = null;
    $priceLabel = '';
    if ($amountRaw !== '' && is_numeric($amountRaw)) {
        $amount = (float)$amountRaw;
        $priceLabel = number_format($amount, 2, ',', ' ') . ' ' . $currency;

        $finalPriceByCurrency[$currency] = ($finalPriceByCurrency[$currency] ?? 0.0) + $amount;
    }

    $imageRaw = trim((string)($option['option_image'] ?? ''));
    $imageLocal = $resolveLocalImagePath($imageRaw);
    $imagePdfSafe = $ensurePdfSafeImagePath($imageLocal);

    $componentId = isset($option['selected_component_id']) ? (int) $option['selected_component_id'] : 0;
    $parentComponentId = $option['selected_parent_component_id'] !== null
        ? (int) $option['selected_parent_component_id']
        : null;
    $selectionId = isset($option['selection_id']) ? (int) $option['selection_id'] : 0;

    $optionNodes[] = [
        'selection_id' => $selectionId,
        'component_id' => $componentId,
        'parent_component_id' => $parentComponentId,
        'title' => $title,
        'image_pdf_safe' => $imagePdfSafe,
        'price_label' => $priceLabel,
        'price_amount' => $amount,
        'price_currency' => $currency,
    ];
}

/** @var array<int, array{
 *     selection_id: int,
 *     component_id: int,
 *     parent_component_id: int|null,
 *     title: string,
 *     image_pdf_safe: string,
 *     price_label: string,
 *     price_amount: float|null,
 *     price_currency: string
 * }> $nodesByComponentId
 */
$nodesByComponentId = [];
/** @var array<int, list<array{
 *     selection_id: int,
 *     component_id: int,
 *     parent_component_id: int|null,
 *     title: string,
 *     image_pdf_safe: string,
 *     price_label: string,
 *     price_amount: float|null,
 *     price_currency: string
 * }>> $childrenByParentId
 */
$childrenByParentId = [];

foreach ($optionNodes as $node) {
    $componentId = $node['component_id'];
    if ($componentId <= 0) {
        continue;
    }

    $nodesByComponentId[$componentId] = $node;

    $parentId = $node['parent_component_id'];
    if ($parentId !== null) {
        if (!isset($childrenByParentId[$parentId])) {
            $childrenByParentId[$parentId] = [];
        }
        $childrenByParentId[$parentId][] = $node;
    }
}

/** @var array<int, bool> $visited */
$visited = [];
$rootIndex = 0;

/**
 * @param array<string, float> $priceByCurrency
 */
$formatSummedPriceLabel = static function (array $priceByCurrency): string {
    if ($priceByCurrency === []) {
        return '';
    }

    $parts = [];
    foreach ($priceByCurrency as $currency => $amount) {
        $parts[] = number_format((float) $amount, 2, ',', ' ') . ' ' . $currency;
    }

    return implode(' + ', $parts);
};

/**
 * Merge a node title into its visible parent when they overlap.
 * Example: parent "Nástavba — Valník" + child "Valník — Bez plachty"
 * becomes parent "Nástavba — Valník — Bez plachty".
 */
$mergeNodeTitleIntoVisibleParent = static function (array &$items, int $depth, string $nodeTitle): bool {
    if ($depth <= 0 || $items === []) {
        return false;
    }

    $parentIndex = null;
    for ($i = count($items) - 1; $i >= 0; $i--) {
        if (($items[$i]['depth'] ?? -1) === ($depth - 1)) {
            $parentIndex = $i;
            break;
        }
    }

    if ($parentIndex === null) {
        return false;
    }

    $splitSegments = static function (string $title): array {
        $parts = preg_split('/\s+—\s+/u', trim($title));
        if (!is_array($parts)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', $parts), static fn(string $part): bool => $part !== ''));
    };

    $parentSegments = $splitSegments((string) ($items[$parentIndex]['title'] ?? ''));
    $nodeSegments = $splitSegments($nodeTitle);

    if ($parentSegments === [] || count($nodeSegments) < 2) {
        return false;
    }

    $maxOverlap = min(count($parentSegments), count($nodeSegments) - 1);
    $overlap = 0;
    for ($k = $maxOverlap; $k >= 1; $k--) {
        if (array_slice($parentSegments, -$k) === array_slice($nodeSegments, 0, $k)) {
            $overlap = $k;
            break;
        }
    }

    if ($overlap === 0) {
        return false;
    }

    $tail = array_slice($nodeSegments, $overlap);
    if ($tail === []) {
        return false;
    }

    $items[$parentIndex]['title'] .= ' — ' . implode(' — ', $tail);

    return true;
};

/**
 * @param array{
 *     selection_id: int,
 *     component_id: int,
 *     parent_component_id: int|null,
 *     title: string,
 *     image_pdf_safe: string,
 *     price_label: string,
 *     price_amount: float|null,
 *     price_currency: string
 * } $rootNode
 */
$appendChain = static function (
    array $rootNode,
    array $childrenByParentId,
    array $singleChoiceSelectionIds,
    callable $formatSummedPriceLabel,
    callable $mergeNodeTitleIntoVisibleParent,
    array &$items,
    array &$visited,
    int &$rootIndex
): void {
    if ($rootNode['component_id'] <= 0) {
        return;
    }

    $rootIndex++;

    /** @var list<array{
     *     node: array{
     *         selection_id: int,
     *         component_id: int,
     *         parent_component_id: int|null,
     *         title: string,
     *         image_pdf_safe: string,
     *         price_label: string,
     *         price_amount: float|null,
     *         price_currency: string
     *     },
     *     depth: int,
     *     is_root: bool
     * }> $stack
     */
    $stack = [[
        'node' => $rootNode,
        'depth' => 0,
        'is_root' => true,
    ]];
    $rootItemIndex = null;
    /** @var array<string, float> $chainPriceByCurrency */
    $chainPriceByCurrency = [];

    while ($stack !== []) {
        $entry = array_pop($stack);

        $node = $entry['node'];
        $componentId = $node['component_id'];
        if (isset($visited[$componentId])) {
            continue;
        }
        $visited[$componentId] = true;

        if ($node['price_amount'] !== null) {
            $priceCurrency = $node['price_currency'] !== '' ? $node['price_currency'] : 'CZK';
            $chainPriceByCurrency[$priceCurrency] = ($chainPriceByCurrency[$priceCurrency] ?? 0.0)
                + (float) $node['price_amount'];
        }

        $selectionId = $node['selection_id'];
        $isSingleChoiceStep = !$entry['is_root']
            && $selectionId > 0
            && ($singleChoiceSelectionIds[$selectionId] ?? false);
        $hasChildren = count($childrenByParentId[$componentId] ?? []) > 0;
        $shouldSkipNode = $isSingleChoiceStep && $hasChildren;
        $shouldMergeIntoParent = false;

        if (!$shouldSkipNode) {
            $shouldMergeIntoParent = !$entry['is_root']
                && $hasChildren
                && $node['price_label'] === ''
                && $node['image_pdf_safe'] === ''
                && $mergeNodeTitleIntoVisibleParent($items, $entry['depth'], $node['title']);
        }

        if (!$shouldSkipNode && !$shouldMergeIntoParent) {
            $items[] = [
                'row_number' => $entry['is_root'] ? (string) $rootIndex : '',
                'depth' => $entry['depth'],
                'is_root' => $entry['is_root'],
                'title' => $node['title'],
                'image_pdf_safe' => $node['image_pdf_safe'],
                'price_label' => $entry['is_root'] ? $node['price_label'] : '',
            ];

            if ($entry['is_root']) {
                $rootItemIndex = count($items) - 1;
            }
        }

        $children = $childrenByParentId[$componentId] ?? [];
        for ($i = count($children) - 1; $i >= 0; $i--) {
            $stack[] = [
                'node' => $children[$i],
                'depth' => $entry['depth'] + (($shouldSkipNode || $shouldMergeIntoParent) ? 0 : 1),
                'is_root' => false,
            ];
        }
    }

    if ($rootItemIndex !== null) {
        $items[$rootItemIndex]['price_label'] = $formatSummedPriceLabel($chainPriceByCurrency);
    }
};

foreach ($optionNodes as $node) {
    $componentId = $node['component_id'];
    if ($componentId <= 0) {
        continue;
    }

    $parentId = $node['parent_component_id'];
    $isRoot = $parentId === null || !isset($nodesByComponentId[$parentId]);
    if ($isRoot) {
        $appendChain(
            $node,
            $childrenByParentId,
            $singleChoiceSelectionIds,
            $formatSummedPriceLabel,
            $mergeNodeTitleIntoVisibleParent,
            $items,
            $visited,
            $rootIndex
        );
    }
}

foreach ($optionNodes as $node) {
    $componentId = $node['component_id'];
    if ($componentId <= 0 || isset($visited[$componentId])) {
        continue;
    }

    $appendChain(
        $node,
        $childrenByParentId,
        $singleChoiceSelectionIds,
        $formatSummedPriceLabel,
        $mergeNodeTitleIntoVisibleParent,
        $items,
        $visited,
        $rootIndex
    );
}

$updatedAtRaw = (string)($configuration['updated_at'] ?? '');
$dt = new DateTimeImmutable($updatedAtRaw, new DateTimeZone('Europe/Prague'));
$updatedAt = $dt->format('d.m.Y H:i:s');
$generatedAt = date('d.m.Y H:i:s');
$configurationTitle = trim((string)($configuration['title'] ?? ''));
$documentTitle = $configurationTitle !== ''
    ? $configurationTitle
    : "Konfigurace #{$configurationId}";

$slugifyForFilename = static function (string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (function_exists('iconv')) {
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($transliterated !== false) {
            $value = $transliterated;
        }
    }

    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');

    return $value;
};

$escape = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

// ---- HTML for PDF ----
$watermarkTile = dirname(__DIR__, 3) . '/public/watermark-tile.svg';
$watermarkTile = str_replace('\\', '/', $watermarkTile);
$css = <<<CSS
@page {
    background-image: url('{$watermarkTile}');
    background-repeat: repeat;
    background-position: 0 0;
    odd-footer-name: html_configFooter;
    even-footer-name: html_configFooter;
}
body {font-family: 'Montserrat', sans-serif; font-size: 11pt; color: #111;}
table.head { width: 100%; border-collapse: collapse; }
.head-left { width: 70%; vertical-align: top; }
.head-right { width: 30%; vertical-align: top; text-align: right; }
.brand-logo {
  display: block;
  max-width: 36mm;
  min-height: 10mm;
  width: auto;
  height: auto;
  margin: 0 0 2mm 0;
}
.head h1 { margin: 0 0 1mm 0; }
h1 { font-size: 16pt; margin: 0 0 4mm 0; text-align: center; }
.meta { color: #444; font-size: 9pt; margin-bottom: 4mm; }
.supplier { margin-top: 3mm; font-size: 9pt; }
.supplier-label { font-weight: bold; margin-bottom: 1mm; }
.supplier-line { margin: 0; }
.hr { height: 1px; background: #bbb; margin: 4mm 0; }

table { width: 100%; border-collapse: collapse; }
thead th { text-align: left; font-size: 9pt; color: #444; border-bottom: 2px solid #bbb; padding: 3mm 2mm; }
tbody td { border-bottom: 1px solid #ccc; padding: 3mm 2mm; vertical-align: top; }
.col-no { width: 10mm; color:#666; }
.col-img { width: 35mm; }
.thumb {
  max-width: 32mm;
  max-height: 22mm;
  width: auto;
  height: auto;
  border: 0.2mm solid #ccc;
  border-radius: 2mm;
  background: #fafafa;
  display: block;
}
.price { white-space: nowrap; }

.totals { margin-top: 6mm; }
.totals-box { 
    background-color: rgba(0, 0, 0, 0.05);
    border: 0.2mm solid #bbb;
    border-radius: 3mm;
    padding: 3mm;
    width: 70mm;
    margin-left: auto;
}
.totals-title { font-weight: bold; margin-bottom: 2mm; }
.totals-table {
  width: 100%;
  border-collapse: collapse;
}
.totals-table td {
  border: 0;
}
.totals-amount {
  padding-right: 1.5mm !important;
  white-space: nowrap;
  text-align: right;
}
.totals-currency {
  color: #444;
  white-space: nowrap;
}
CSS;

$rowsHtml = '';
if ($items === []) {
    $rowsHtml = '<tr><td colspan="4">Žádné vybrané možnosti.</td></tr>';
} else {
    foreach ($items as $item) {
        $imgHtml = '—';
        if ($item['image_pdf_safe'] !== '') {
            $imgHtml = '<img class="thumb" src="' . $escape($item['image_pdf_safe']) . '" alt="">';
        }

        $titleText = $item['title'];
        $titleHtml = $item['is_root']
            ? '<strong>' . $escape($titleText) . '</strong>'
            : $escape($titleText);

        $rowsHtml .= '<tr>'
            . '<td class="col-no">' . $escape($item['row_number']) . '</td>'
            . '<td>' . $titleHtml . '</td>'
            . '<td class="price">' . $escape($item['price_label']) . '</td>'
            . '<td class="col-img">' . $imgHtml . '</td>'
            . '</tr>';
    }
}

$logoHtml = '';
if ($logoPdfSafe !== '') {
    $logoHtml = '<img class="brand-logo" src="' . $escape($logoPdfSafe) . '" alt="Logo">';
}

$supplierHtml = '';
if ($companyAddress !== null) {
    $supplierLines = [];

    $companyName = trim((string) $companyAddress['company_name']);
    if ($companyName !== '') {
        $supplierLines[] = $companyName;
    }

    $streetParts = array_values(array_filter([
        trim((string) $companyAddress['street']),
        trim((string) $companyAddress['street_number']),
    ], static fn(string $part): bool => $part !== ''));
    if ($streetParts !== []) {
        $supplierLines[] = implode(' ', $streetParts);
    }

    $cityParts = array_values(array_filter([
        trim((string) $companyAddress['post_code']),
        trim((string) $companyAddress['city']),
    ], static fn(string $part): bool => $part !== ''));
    if ($cityParts !== []) {
        $supplierLines[] = implode(' ', $cityParts);
    }

    $state = trim((string) $companyAddress['state']);
    if ($state !== '') {
        $supplierLines[] = $state;
    }

    $country = strtoupper(trim((string) $companyAddress['country_code']));
    if ($country !== '') {
        $supplierLines[] = $country;
    }

    if ($supplierLines !== []) {
        $supplierHtml = '<div class="supplier">'
            . '<div class="supplier-label">Dodavatel</div>';

        foreach ($supplierLines as $supplierLine) {
            $supplierHtml .= '<p class="supplier-line">' . $escape($supplierLine) . '</p>';
        }

        $supplierHtml .= '</div>';
    }
}

$totalsHtml = '';
if ($finalPriceByCurrency === []) {
    $totalsHtml .= '<tr>'
        . '<td class="totals-amount"><strong>' . $escape(number_format(0, 2, ',', ' ')) . '</strong></td>'
        . '<td class="totals-currency">-</td>'
        . '</tr>';
} else {
    $totalsHtml = '<table class="totals-table">';
    foreach ($finalPriceByCurrency as $cur => $amount) {
        $totalsHtml .= '<tr>'
            . '<td class="totals-amount"><strong>'
            . $escape(number_format((float)$amount, 2, ',', ' '))
            . '</strong></td>'
            . '<td class="totals-currency">' . $escape((string)$cur) . '</td>'
            . '</tr>';
    }
    $totalsHtml .= '</table>';
}

$html = <<<HTML
<table class="head">
  <tr>
    <td class="head-left">
      {$logoHtml}
      {$supplierHtml}
    </td>
    <td class="head-right">
      <div class="meta">Aktualizace: {$escape($updatedAt)}</div>
      <div class="meta">Vygenerováno: {$escape($generatedAt)}</div>
      <div class="meta">{$user['email']}</div>
    </td>
  </tr>
</table>
<div class="hr"></div>
<h1>{$escape($documentTitle)}</h1>
<table>
  <thead>
    <tr>
      <th class="col-no" colspan="2">#</th>
      <th class="price">Cena</th>
      <th class="col-img">Obrázek</th>
    </tr>
  </thead>
  <tbody>
    {$rowsHtml}
  </tbody>
</table>

<div class="totals">
  <div class="totals-box">
    <div class="totals-title">Celková cena</div>
    {$totalsHtml}
  </div>
</div>
HTML;

$footerHtml = '<div style="text-align:right; font-size:9pt; color:#666;">Strana {PAGENO} / {nb}</div>';

// ---- Render PDF ----
try {
    $tempDir = dirname(__DIR__, 3) . '/tmp/mpdf';
    if (!is_dir($tempDir)) {
        @mkdir($tempDir, 0777, true);
    }

    $fontConfig = (new FontVariables())->getDefaults();
    $fontData = $fontConfig['fontdata'];
    $config = (new ConfigVariables())->getDefaults();
    $fontDirs = $config['fontDir'];

    $customFontDir = dirname(__DIR__, 3) . '/src/assets/fonts';
    $montserratRegular = $customFontDir . '/Montserrat-Medium.ttf';
    $montserratBold = $customFontDir . '/Montserrat-Bold.ttf';
    $hasMontserratRegular = is_file($montserratRegular) && is_readable($montserratRegular);
    $hasMontserratBold = is_file($montserratBold) && is_readable($montserratBold);

    if (!$hasMontserratRegular) {
        log_message('Montserrat font file is missing: ' . $montserratRegular, 'ERROR');
    }

    if ($hasMontserratRegular) {
        $fontData['montserrat'] = [
            'R' => 'Montserrat-Medium.ttf',
            'B' => $hasMontserratBold ? 'Montserrat-Bold.ttf' : 'Montserrat-Medium.ttf',
        ];
    }

    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'tempDir' => $tempDir,
        'fontDir' => array_merge($fontDirs, [$customFontDir]),
        'fontdata' => $fontData,
        'default_font' => $hasMontserratRegular ? 'montserrat' : 'dejavusans',
        'margin_left' => 12,
        'margin_right' => 12,
        'margin_top' => 12,
        'margin_bottom' => 18,
    ]);

    // Helps with images over HTTPS with odd certs (optional):
    // $mpdf->curlAllowUnsafeSslRequests = true;

    $mpdf->SetTitle($documentTitle);
    $mpdf->SetAuthor('HAGEMANN konfigurátor');
    $mpdf->SetDisplayMode('fullpage');
    $mpdf->AliasNbPages();
    $mpdf->DefHTMLFooterByName('configFooter', $footerHtml);

    $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
    $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);

    $filenameSlug = $slugifyForFilename($documentTitle);
    $filename = ($filenameSlug !== '' ? $filenameSlug : "configuration-{$configurationId}") . '.pdf';
    // mPDF will send headers + output
    $mpdf->Output($filename, Destination::DOWNLOAD);
} catch (\Throwable $e) {
    http_response_code(500);
    log_message($e->getMessage(), 'ERROR');
    echo 'Nepodařilo se vygenerovat PDF.';
}
