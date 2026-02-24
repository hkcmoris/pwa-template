<?php

use Administration\Repository;

require_once __DIR__ . '/../../bootstrap.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
}

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

$repository = new Repository();

try {
    $cleanSvg = $repository->sanitizeSvg($rawSvg);
    [$w, $h] = $repository->svgDimensions($cleanSvg);

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

    $now = (string)time();
    $repository->saveLogoSettings($logoPathRel, $w, $h, $now);

    echo json_encode([
        'message' => 'Nahrání loga proběhlo úspěšně.',
        'path' => $logoPathRel,
        'width' => $w,
        'height' => $h,
        'updated_at' => $now,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    log_message('Admin logo upload failed: ' . $e->getMessage(), 'ERROR');
    echo json_encode(['error' => 'Nahrání loga selhalo: ' . $e->getMessage()]);
}
