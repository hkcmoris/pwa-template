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
$startY = 800;
$contentParts = ['BT', '/F1 12 Tf'];
$currentY = $startY;

foreach ($lines as $line) {
    $sanitized = $toPdfText($line);
    $contentParts[] = sprintf('1 0 0 1 48 %d Tm (%s) Tj', $currentY, $pdfEscape($sanitized));
    $currentY -= $lineHeight;
}

$contentParts[] = 'ET';
$content = implode("\n", $contentParts) . "\n";

$objects = [];
$objects[] = '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj';
$objects[] = '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj';
$objects[] = '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj';
$objects[] = '4 0 obj << /Length ' . strlen($content) . ' >> stream' . "\n" . $content . 'endstream endobj';
$objects[] = <<<'PDF'
5 0 obj <<
/Type /Font
/Subtype /Type1
/BaseFont /Helvetica
/Encoding << /Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences [128 /Aacute /Ccaron /Dcaron /Eacute /Ecaron /Iacute /Ncaron /Oacute /Rcaron /Scaron /Tcaron /Uacute /Uring /Yacute /Zcaron /aacute /ccaron /dcaron /eacute /ecaron /iacute /ncaron /oacute /rcaron /scaron /tcaron /uacute /uring /yacute /zcaron] >>
>> endobj
PDF;

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
