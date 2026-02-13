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
        o.position
    FROM configuration_options o
    INNER JOIN components c ON c.id = o.component_id
    INNER JOIN definitions d ON d.id = c.definition_id
    WHERE o.configuration_id = :configuration_id
    ORDER BY o.position ASC, o.id ASC
    SQL
);
$optionsStmt->bindValue(':configuration_id', $configurationId, PDO::PARAM_INT);
$optionsStmt->execute();
/** @var list<array{option_title: string|null, position: int|string}> $options */
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
    foreach ($options as $index => $option) {
        $title = trim((string) ($option['option_title'] ?? 'Option'));
        if ($title === '') {
            $title = 'Option';
        }
        $lines[] = ($index + 1) . '. ' . $title;
    }
}

$pdfEscape = static function (string $value): string {
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
};

$toPdfText = static function (string $value): string {
    $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (!is_string($normalized) || $normalized === '') {
        $normalized = $value;
    }

    return preg_replace('/[^\x20-\x7E]/', '', $normalized) ?? '';
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
$objects[] = '5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj';

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
