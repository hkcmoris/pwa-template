<?php

declare(strict_types=1);

use Mpdf\Mpdf;
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
        COALESCE(NULLIF(c.alternate_title, ''), d.title) AS option_title,
        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(c.images, '$[0]')), '') AS option_image,
        lp.amount AS option_price_amount,
        UPPER(COALESCE(NULLIF(lp.currency, ''), 'CZK')) AS option_price_currency
    FROM configuration_selections o
    INNER JOIN components c ON c.id = o.component_id
    INNER JOIN definitions d ON d.id = c.definition_id
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
/** @var list<array{option_title: string|null, option_image: string|null, option_price_amount: string|null, option_price_currency: string|null}> $options */
$options = $optionsStmt->fetchAll(PDO::FETCH_ASSOC);

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

    // Must be root-relative or assets/public path you control
    if ($path[0] !== '/') {
        return '';
    }

    /** @var string $basePath */
    $basePath = defined('BASE_PATH') ? (string) BASE_PATH : '';
    if ($basePath === '/') {
        $basePath = '';
    }
    if ($basePath !== '' && str_starts_with($path, $basePath . '/')) {
        $path = substr($path, strlen($basePath));
    }

    // server/ absolute path
    $serverRoot = dirname(__DIR__, 4); // controllers/... -> server/
    $candidate = $serverRoot . $path;

    // Normalize slashes for Windows
    $candidate = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $candidate);

    if (!is_file($candidate)) {
        log_message("Missing image: $candidate", 'DEBUG');
    }

    return (is_file($candidate) && is_readable($candidate)) ? $candidate : '';
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

// ---- Build view model ----
$finalPriceByCurrency = [];
$items = [];

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '';
$origin = ($host !== '') ? ($scheme . '://' . $host) : '';

foreach ($options as $option) {
    $title = trim((string)($option['option_title'] ?? ''));
    if ($title === '') {
        $title = 'Option';
    }
    $amountRaw = trim((string)($option['option_price_amount'] ?? ''));
    $currency = strtoupper(trim((string)($option['option_price_currency'] ?? 'CZK')));
    if ($currency === '') {
        $currency = 'CZK';
    }

    $amount = null;
    $priceLabel = 'N/A';
    if ($amountRaw !== '' && is_numeric($amountRaw)) {
        $amount = (float)$amountRaw;
        $priceLabel = number_format($amount, 2, ',', ' ') . ' ' . $currency;

        $finalPriceByCurrency[$currency] = ($finalPriceByCurrency[$currency] ?? 0.0) + $amount;
    }

    $imageRaw = trim((string)($option['option_image'] ?? ''));
    $imageLocal = $resolveLocalImagePath($imageRaw);
    $imagePdfSafe = $ensurePdfSafeImagePath($imageLocal);

    $items[] = [
      'title' => $title,
      'image_local' => $imageLocal,   // <- use this
      'image_pdf_safe' => $imagePdfSafe, // <- use this for PDF compatibility
      'price_label' => $priceLabel,
    ];
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
body {font-family: sans-serif; font-size: 11pt; color: #111;}
table.head { width: 100%; border-collapse: collapse; }
.head-left { width: 70%; vertical-align: top; }
.head-right { width: 30%; vertical-align: top; text-align: right; }
.head h1 { margin: 0 0 1mm 0; }
h1 { font-size: 16pt; margin: 0 0 4mm 0; }
.meta { color: #444; font-size: 9pt; margin-bottom: 4mm; }
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
    foreach ($items as $i => $item) {
        $imgHtml = '—';
        if ($item['image_pdf_safe'] !== '') {
            $imgHtml = '<img class="thumb" src="' . $escape($item['image_pdf_safe']) . '" alt="">';
        }

        $rowsHtml .= '<tr>'
            . '<td class="col-no">' . ($i + 1) . '</td>'
            . '<td><strong>' . $escape($item['title']) . '</strong></td>'
            . '<td class="col-img">' . $imgHtml . '</td>'
            . '<td class="price">' . $escape($item['price_label']) . '</td>'
            . '</tr>';
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
      <h1>{$escape($documentTitle)}</h1>
      <div class="meta">{$user['email']}</div>
    </td>
    <td class="head-right">
      <div class="meta">Aktualizace: {$escape($updatedAt)}</div>
      <div class="meta">Vygenerováno: {$escape($generatedAt)}</div>
    </td>
  </tr>
</table>
<div class="hr"></div>

<table>
  <thead>
    <tr>
      <th class="col-no">#</th>
      <th>Volba</th>
      <th class="col-img">Obrázek</th>
      <th class="price">Cena</th>
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
    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'tempDir' => $tempDir,
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
