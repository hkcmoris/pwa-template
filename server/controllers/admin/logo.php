<?php

require_once __DIR__ . '/../../bootstrap.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
}

log_message(json_encode($_POST, JSON_PRETTY_PRINT), 'DEBUG');
log_message(json_encode(csrf_require_valid($_POST, 'json'), JSON_PRETTY_PRINT), 'DEBUG');

csrf_require_valid($_POST, 'json');

$user = app_get_current_user();
$role = $user['role'] ?? 'guest';
if ($role !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['error' => 'Nemáte oprávnění nahrát logo.']);
    exit;
}

if (!isset($_FILES['svg_file'])) {
    http_response_code(422);
    echo json_encode(['error' => 'Vyberte SVG soubor k nahrání.']);
    exit;
}

$upload = $_FILES['svg_file'];
if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(422);
    echo json_encode(['error' => 'Soubor se nepodařilo nahrát.']);
    exit;
}

$tmpName = (string) ($upload['tmp_name'] ?? '');
if ($tmpName === '' || !is_uploaded_file($tmpName)) {
    http_response_code(422);
    echo json_encode(['error' => 'Nahraný soubor není dostupný.']);
    exit;
}

// size limit (e.g. 200KB)
$maxBytes = 200 * 1024;
if (($upload['size'] ?? 0) > $maxBytes) {
    http_response_code(422);
    echo json_encode(['error' => 'SVG je příliš velké (max 200 KB).']);
    exit;
}

$rawSvg = file_get_contents($tmpName);
if ($rawSvg === false || trim($rawSvg) === '') {
    http_response_code(422);
    echo json_encode(['error' => 'SVG soubor je prázdný nebo nečitelný.']);
    exit;
}

// quick “is it SVG” check
if (stripos($rawSvg, '<svg') === false) {
    http_response_code(422);
    echo json_encode(['error' => 'Soubor nevypadá jako SVG.']);
    exit;
}

function svg_dimensions(string $svg): array {
    $w = $h = null;

    if (preg_match('/<svg[^>]*\bwidth=["\']([^"\']+)["\']/i', $svg, $m)) {
        $w = (float)preg_replace('/[^0-9.]/', '', $m[1]);
    }
    if (preg_match('/<svg[^>]*\bheight=["\']([^"\']+)["\']/i', $svg, $m)) {
        $h = (float)preg_replace('/[^0-9.]/', '', $m[1]);
    }
    if ((!$w || !$h) && preg_match('/\bviewBox=["\']\s*[-0-9.]+\s+[-0-9.]+\s+([0-9.]+)\s+([0-9.]+)\s*["\']/i', $svg, $m)) {
        $w = $w ?: (float)$m[1];
        $h = $h ?: (float)$m[2];
    }

    if (!$w || !$h) { $w = 130; $h = 30; }
    return [$w, $h];
}

// VERY basic sanitization (good enough for “logo svg”)
// If you ever inline SVG, this matters a lot.
function sanitize_svg(string $svg): string {
    // remove scripts
    $svg = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $svg) ?? $svg;
    // remove foreignObject (html embedding)
    $svg = preg_replace('#<foreignObject\b[^>]*>.*?</foreignObject>#is', '', $svg) ?? $svg;
    // remove event handlers like onload=, onclick=...
    $svg = preg_replace('/\son[a-z]+\s*=\s*(["\']).*?\1/i', '', $svg) ?? $svg;
    // remove external hrefs (http/https/javascript:)
    $svg = preg_replace('/\b(href|xlink:href)\s*=\s*(["\'])(https?:|javascript:).*?\2/i', '', $svg) ?? $svg;
    return $svg;
}

try {
    $cleanSvg = sanitize_svg($rawSvg);
    [$w, $h] = svg_dimensions($cleanSvg);

    // store file with stable name (caching can use query param with updated_at)
    $baseDir = realpath(__DIR__ . '/../../public/assets');
    if ($baseDir === false) {
        throw new RuntimeException('Public dir not found');
    }
    $logoDir = $baseDir . '/logo';
    if (!is_dir($logoDir) && !mkdir($logoDir, 0775, true)) {
        throw new RuntimeException('Cannot create upload dir');
    }

    $logoPathRel = '/public/assets/logo/logo.svg';
    $logoPathAbs = $baseDir . '/logo/logo.svg';

    // atomic write
    $tmpOut = $logoPathAbs . '.tmp';
    if (file_put_contents($tmpOut, $cleanSvg, LOCK_EX) === false) {
        throw new RuntimeException('Cannot write SVG');
    }
    rename($tmpOut, $logoPathAbs);

    $pdo = get_db_connection();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "INSERT INTO app_settings (k, v) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE v = VALUES(v), updated_at = CURRENT_TIMESTAMP"
    );

    $now = (string)time();

    $stmt->execute([':k' => 'logo_path', ':v' => $logoPathRel]);
    $stmt->execute([':k' => 'logo_width', ':v' => (string)$w]);
    $stmt->execute([':k' => 'logo_height', ':v' => (string)$h]);
    $stmt->execute([':k' => 'logo_updated_at', ':v' => $now]);

    $pdo->commit();

    echo json_encode([
        'message' => 'Nahrání loga proběhlo úspěšně.',
        'path' => $logoPathRel,
        'width' => $w,
        'height' => $h,
        'updated_at' => $now,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    log_message('Admin logo upload failed: ' . $e->getMessage(), 'ERROR');
    echo json_encode(['error' => 'Nahrání loga selhalo: ' . $e->getMessage()]);
}
