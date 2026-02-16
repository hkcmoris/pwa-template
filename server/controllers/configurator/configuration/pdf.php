<?php

declare(strict_types=1);

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
    'SELECT id, user_id, status, updated_at FROM configurations WHERE id = :id LIMIT 1'
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
        UPPER(COALESCE(NULLIF(lp.currency, ''), 'CZK')) AS option_price_currency,
        o.position
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
    ORDER BY o.position ASC, o.id ASC
    SQL
);
$optionsStmt->bindValue(':configuration_id', $configurationId, PDO::PARAM_INT);
$optionsStmt->execute();
/** @var list<array{option_title: string|null, option_image: string|null, option_price_amount: string|null, option_price_currency: string|null, position: int|string}> $options */
$options = $optionsStmt->fetchAll(PDO::FETCH_ASSOC);

$lines = [
    'Configuration #' . $configurationId,
    'Updated: ' . (string) ($configuration['updated_at'] ?? ''),
    '',
    'Selected options:',
];

if ($options === []) {
    $lines[] = '- No selected options';
} else {
    $finalPriceByCurrency = [];

    foreach ($options as $index => $option) {
        $title = trim((string) ($option['option_title'] ?? 'Option'));
        if ($title === '') {
            $title = 'Option';
        }

        $priceAmountRaw = trim((string) ($option['option_price_amount'] ?? ''));
        $priceCurrency = strtoupper(trim((string) ($option['option_price_currency'] ?? 'CZK')));
        if ($priceCurrency === '') {
            $priceCurrency = 'CZK';
        }

        $priceLabel = 'N/A';
        if ($priceAmountRaw !== '' && is_numeric($priceAmountRaw)) {
            $normalised = number_format((float) $priceAmountRaw, 2, '.', ' ');
            $priceLabel = $normalised . ' ' . $priceCurrency;

            if (!isset($finalPriceByCurrency[$priceCurrency])) {
                $finalPriceByCurrency[$priceCurrency] = 0.0;
            }
            $finalPriceByCurrency[$priceCurrency] += (float) $priceAmountRaw;
        }

        $imageLabel = trim((string) ($option['option_image'] ?? ''));
        if ($imageLabel === '') {
            $imageLabel = 'N/A';
        }

        $lines[] = sprintf(
            '%d. %s | Price: %s | Image: %s',
            $index + 1,
            $title,
            $priceLabel,
            $imageLabel
        );
    }

    $lines[] = '';
    if ($finalPriceByCurrency === []) {
        $lines[] = 'Final price: N/A';
    } else {
        foreach ($finalPriceByCurrency as $currency => $amount) {
            $lines[] = sprintf(
                'Final price (%s): %s',
                $currency,
                number_format($amount, 2, '.', ' ')
            );
        }
    }
}

$czechMap = [
    'Á' => 128,
    'Č' => 129,
    'Ď' => 130,
    'É' => 131,
    'Ě' => 132,
    'Í' => 133,
    'Ň' => 134,
    'Ó' => 135,
    'Ř' => 136,
    'Š' => 137,
    'Ť' => 138,
    'Ú' => 139,
    'Ů' => 140,
    'Ý' => 141,
    'Ž' => 142,
    'á' => 143,
    'č' => 144,
    'ď' => 145,
    'é' => 146,
    'ě' => 147,
    'í' => 148,
    'ň' => 149,
    'ó' => 150,
    'ř' => 151,
    'š' => 152,
    'ť' => 153,
    'ú' => 154,
    'ů' => 155,
    'ý' => 156,
    'ž' => 157,
];

$toPdfText = static function (string $value) use ($czechMap): string {
    $buffer = '';
    $chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($chars)) {
        return '';
    }

    foreach ($chars as $char) {
        if (isset($czechMap[$char])) {
            $buffer .= chr($czechMap[$char]);
            continue;
        }

        if (strlen($char) === 1) {
            $ord = ord($char);
            if ($ord >= 32 && $ord <= 126) {
                $buffer .= $char;
            }
        }
    }

    return $buffer;
};

$pdfEscape = static function (string $value): string {
    $escaped = '';
    $length = strlen($value);

    for ($i = 0; $i < $length; $i++) {
        $byte = ord($value[$i]);
        if ($byte === 40 || $byte === 41 || $byte === 92) {
            $escaped .= '\\' . chr($byte);
            continue;
        }

        if ($byte < 32 || $byte > 126) {
            $escaped .= sprintf('\\%03o', $byte);
            continue;
        }

        $escaped .= chr($byte);
    }

    return $escaped;
};

$lineHeight = 16;
$fontSize = 12;
$startY = 800;
$pageHeight = 842;
$leftX = 48;
$maxImageWidth = 120;
$maxImageHeight = 90;
$imageGap = 12;
$contentParts = ['BT', '/F1 12 Tf'];
$currentY = $startY;

/**
 * @param string $imagePath
 * @return array{width: int, height: int, data: string}|null
 */
$preparePdfImage = static function (string $imagePath): ?array {
    if ($imagePath === '' || !is_file($imagePath) || !is_readable($imagePath)) {
        return null;
    }

    $rawData = file_get_contents($imagePath);
    if ($rawData === false || $rawData === '') {
        return null;
    }

    if (class_exists('Imagick')) {
        try {
            $imagick = new \Imagick();
            $imagick->readImageBlob($rawData);
            if ((int) $imagick->getNumberImages() > 1) {
                $imagick = $imagick->coalesceImages();
                $imagick->setFirstIterator();
                $imagick = $imagick->getImage();
            }
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality(82);

            $jpegData = $imagick->getImageBlob();
            $width = (int) $imagick->getImageWidth();
            $height = (int) $imagick->getImageHeight();
            $imagick->clear();
            $imagick->destroy();

            if ($jpegData !== '') {
                return [
                    'width' => $width,
                    'height' => $height,
                    'data' => $jpegData,
                ];
            }
        } catch (Throwable $exception) {
            // Ignore and continue to GD fallback.
        }
    }

    if (function_exists('imagecreatefromstring') && function_exists('imagejpeg') && function_exists('imagesx') && function_exists('imagesy')) {
        $source = @imagecreatefromstring($rawData);
        if ($source !== false) {
            $width = imagesx($source);
            $height = imagesy($source);

            ob_start();
            imagejpeg($source, null, 82);
            $jpegData = (string) ob_get_clean();
            imagedestroy($source);

            if ($jpegData !== '') {
                return [
                    'width' => $width,
                    'height' => $height,
                    'data' => $jpegData,
                ];
            }
        }
    }

    return null;
};

/**
 * @param string $imageLabel
 */
$appBase = isset($BASE) && is_string($BASE) ? $BASE : '';

$resolveImagePath = static function (string $imageLabel) use ($appBase): string {
    $trimmed = trim($imageLabel);
    if ($trimmed === '') {
        return '';
    }

    $parsedPath = (string) parse_url($trimmed, PHP_URL_PATH);
    if ($parsedPath === '') {
        $parsedPath = $trimmed;
    }

    $decodedPath = rawurldecode($parsedPath);

    if (strpos($decodedPath, '/public/') === 0) {
        $candidate = dirname(__DIR__, 3) . $decodedPath;
        return is_file($candidate) ? $candidate : '';
    }

    $basePath = '';
    if ($appBase !== '' && $appBase !== '/') {
        $parsedBasePath = parse_url($appBase, PHP_URL_PATH);
        if (is_string($parsedBasePath)) {
            $basePath = $parsedBasePath;
        }
    }

    if ($basePath !== '' && strpos($decodedPath, $basePath . '/public/') === 0) {
        $decodedPath = substr($decodedPath, strlen($basePath));
    }

    if (strpos($decodedPath, 'public/') === 0) {
        $decodedPath = '/' . $decodedPath;
    }

    if (strpos($decodedPath, '/public/') === 0) {
        $candidate = dirname(__DIR__, 3) . $decodedPath;
        return is_file($candidate) ? $candidate : '';
    }

    return '';
};

/** @var array<string, array{width: int, height: int, data: string}> $embeddedImages */
$embeddedImages = [];
/** @var array<int, string> $lineImageRefs */
$lineImageRefs = [];

foreach ($lines as $line) {
    $lineImageRefs[] = '';
}

foreach ($options as $index => $option) {
    $imageLabel = trim((string) ($option['option_image'] ?? ''));
    if ($imageLabel === '') {
        continue;
    }

    if (!isset($embeddedImages[$imageLabel])) {
        $imagePath = $resolveImagePath($imageLabel);
        $preparedImage = $preparePdfImage($imagePath);
        if ($preparedImage !== null) {
            $embeddedImages[$imageLabel] = $preparedImage;
        }
    }

    if (!isset($embeddedImages[$imageLabel])) {
        continue;
    }

    $linePosition = 4 + $index;
    $lineImageRefs[$linePosition] = $imageLabel;
}

$imageAliasByLabel = [];
$imageAliasCounter = 1;
foreach ($embeddedImages as $imageLabel => $_imageData) {
    $imageAliasByLabel[$imageLabel] = 'Im' . $imageAliasCounter;
    $imageAliasCounter++;
}

foreach ($lines as $lineIndex => $line) {
    $sanitized = $toPdfText($line);
    $contentParts[] = sprintf('1 0 0 1 %d %d Tm (%s) Tj', $leftX, $currentY, $pdfEscape($sanitized));
    $currentY -= $lineHeight;

    $imageLabel = $lineImageRefs[$lineIndex] ?? '';
    if ($imageLabel === '' || !isset($embeddedImages[$imageLabel], $imageAliasByLabel[$imageLabel])) {
        continue;
    }

    $sourceWidth = (int) $embeddedImages[$imageLabel]['width'];
    $sourceHeight = (int) $embeddedImages[$imageLabel]['height'];
    if ($sourceWidth <= 0 || $sourceHeight <= 0) {
        continue;
    }

    $ratio = min($maxImageWidth / $sourceWidth, $maxImageHeight / $sourceHeight, 1.0);
    $drawWidth = max(1.0, round($sourceWidth * $ratio, 2));
    $drawHeight = max(1.0, round($sourceHeight * $ratio, 2));

    $imageTop = $currentY - 2;
    $imageBottom = $imageTop - $drawHeight;
    if ($imageBottom < 40) {
        break;
    }

    $contentParts[] = sprintf(
        'q %.2F 0 0 %.2F %d %.2F cm /%s Do Q',
        $drawWidth,
        $drawHeight,
        $leftX + 16,
        $imageBottom,
        $imageAliasByLabel[$imageLabel]
    );
    $currentY -= (int) ceil($drawHeight + $imageGap);
}

$contentParts[] = 'ET';
$content = implode("\n", $contentParts) . "\n";

$objects = [];
$objects[] = '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj';
$objects[] = '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj';
$xObjectResources = '';
$imageObjectNumbers = [];
$nextObjectNumber = 6;

foreach ($embeddedImages as $imageLabel => $imageData) {
    $imageObjectNumbers[$imageLabel] = $nextObjectNumber;
    $alias = $imageAliasByLabel[$imageLabel] ?? '';
    if ($alias !== '') {
        $xObjectResources .= sprintf('/%s %d 0 R ', $alias, $nextObjectNumber);
    }

    $nextObjectNumber++;
}

$resources = '/Font << /F1 5 0 R >>';
if ($xObjectResources !== '') {
    $resources .= ' /XObject << ' . trim($xObjectResources) . ' >>';
}

$objects[] = sprintf(
    '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 %d] /Contents 4 0 R /Resources << %s >> >> endobj',
    $pageHeight,
    $resources
);
$objects[] = '4 0 obj << /Length ' . strlen($content) . ' >> stream' . "\n" . $content . 'endstream endobj';
$objects[] = <<<'PDF'
5 0 obj <<
/Type /Font
/Subtype /Type1
/BaseFont /Helvetica
/Encoding << /Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences [128 /Aacute /Ccaron /Dcaron /Eacute /Ecaron /Iacute /Ncaron /Oacute /Rcaron /Scaron /Tcaron /Uacute /Uring /Yacute /Zcaron /aacute /ccaron /dcaron /eacute /ecaron /iacute /ncaron /oacute /rcaron /scaron /tcaron /uacute /uring /yacute /zcaron] >>
>> endobj
PDF;

foreach ($embeddedImages as $imageLabel => $imageData) {
    $objectNumber = $imageObjectNumbers[$imageLabel] ?? 0;
    if ($objectNumber <= 0) {
        continue;
    }

    $imageBytes = $imageData['data'];
    $objects[] = sprintf(
        "%d 0 obj << /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >> stream\n",
        $objectNumber,
        (int) $imageData['width'],
        (int) $imageData['height'],
        strlen($imageBytes)
    ) . $imageBytes . "\nendstream endobj";
}

$pdf = "%PDF-1.4\n";
$offsets = [0];
foreach ($objects as $object) {
    $offsets[] = strlen($pdf);
    $pdf .= $object . "\n";
}

$xrefOffset = strlen($pdf);
$pdf .= 'xref' . "\n";
$pdf .= '0 ' . (count($objects) + 1) . "\n";
$pdf .= "0000000000 65535 f \n";

for ($i = 1; $i <= count($objects); $i++) {
    $pdf .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n";
}

$pdf .= 'trailer << /Size ' . (count($objects) + 1) . ' /Root 1 0 R >>' . "\n";
$pdf .= 'startxref' . "\n";
$pdf .= $xrefOffset . "\n";
$pdf .= '%%EOF';

$filename = sprintf('configuration-%d.pdf', $configurationId);
if (!headers_sent()) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');
}

echo $pdf;
