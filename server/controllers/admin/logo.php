<?php

declare(strict_types=1);

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

$basePath = defined('BASE_PATH') ? rtrim((string) BASE_PATH, '/') : '';
$buildAssetUrl = static function (string $path, string $updatedAt) use ($basePath): string {
    $normalizedPath = ltrim($path, '/');
    $url = ($basePath !== '' ? $basePath : '') . '/' . $normalizedPath;
    if ($updatedAt !== '') {
        $url .= '?v=' . rawurlencode($updatedAt);
    }
    return $url;
};

/**
 * @return array{raw: string, clean: string}
 */
$readUploadedSvg = static function (array $upload, string $fieldLabel): array {
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException($fieldLabel . ': soubor se nepodařilo nahrát.');
    }

    $tmpName = (string) ($upload['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException($fieldLabel . ': nahraný soubor není dostupný.');
    }

    $maxBytes = 200 * 1024;
    if ((int) ($upload['size'] ?? 0) > $maxBytes) {
        throw new RuntimeException($fieldLabel . ': SVG je příliš velké (max 200 KB).');
    }

    $rawSvg = file_get_contents($tmpName);
    if ($rawSvg === false || trim($rawSvg) === '') {
        throw new RuntimeException($fieldLabel . ': SVG soubor je prázdný nebo nečitelný.');
    }

    if (stripos($rawSvg, '<svg') === false) {
        throw new RuntimeException($fieldLabel . ': soubor nevypadá jako SVG.');
    }

    return ['raw' => $rawSvg, 'clean' => $rawSvg];
};

$repository = new Repository();

$fileDefinitions = [
    'logo_light_svg' => [
        'label' => 'Logo (světlý režim)',
        'destination' => 'public/assets/logo/logo.svg',
        'settings' => [
            'path' => 'logo_path',
            'width' => 'logo_width',
            'height' => 'logo_height',
        ],
    ],
    'logo_dark_svg' => [
        'label' => 'Logo (tmavý režim)',
        'destination' => 'public/assets/logo/logo-dark.svg',
        'settings' => [
            'path' => 'logo_dark_path',
            'width' => 'logo_dark_width',
            'height' => 'logo_dark_height',
        ],
    ],
    'logo_pdf_svg' => [
        'label' => 'Logo pro PDF',
        'destination' => 'public/assets/logo/logo-pdf.svg',
        'settings' => [
            'path' => 'logo_pdf_path',
            'width' => 'logo_pdf_width',
            'height' => 'logo_pdf_height',
        ],
    ],
    'watermark_tile_svg' => [
        'label' => 'Watermark tile pro PDF',
        'destination' => 'public/assets/logo/watermark-tile.svg',
        'settings' => [
            'path' => 'pdf_watermark_tile_path',
        ],
    ],
];

if (
    isset($_FILES['svg_file'])
    && !isset($_FILES['logo_light_svg'])
    && is_array($_FILES['svg_file'])
) {
    $_FILES['logo_light_svg'] = $_FILES['svg_file'];
}

$hasAnyFile = false;
foreach ($fileDefinitions as $field => $_definition) {
    if (
        isset($_FILES[$field])
        && is_array($_FILES[$field])
        && (int) ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
    ) {
        $hasAnyFile = true;
        break;
    }
}

if (!$hasAnyFile) {
    http_response_code(422);
    echo json_encode(['error' => 'Vyberte alespoň jeden SVG soubor k nahrání.']);
    exit;
}

try {
    $baseDir = realpath(__DIR__ . '/../../public/assets');
    if ($baseDir === false) {
        throw new RuntimeException('Public assets directory nebyl nalezen.');
    }

    $settingsUpdates = [];
    $uploadCount = 0;

    foreach ($fileDefinitions as $field => $definition) {
        if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
            continue;
        }

        $upload = $_FILES[$field];
        if ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $svgData = $readUploadedSvg($upload, (string) $definition['label']);
        $cleanSvg = $repository->sanitizeSvg($svgData['clean']);

        $destinationRel = (string) $definition['destination'];
        $destinationSuffix = preg_replace('#^public/assets/#', '', $destinationRel) ?? $destinationRel;
        $destinationAbs = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $destinationSuffix);

        $destinationDir = dirname($destinationAbs);
        if (!is_dir($destinationDir) && !mkdir($destinationDir, 0775, true)) {
            throw new RuntimeException((string) $definition['label'] . ': nelze vytvořit cílovou složku.');
        }

        $tmpOut = $destinationAbs . '.tmp';
        if (file_put_contents($tmpOut, $cleanSvg, LOCK_EX) === false) {
            throw new RuntimeException((string) $definition['label'] . ': nelze uložit soubor.');
        }
        if (!rename($tmpOut, $destinationAbs)) {
            @unlink($tmpOut);
            throw new RuntimeException((string) $definition['label'] . ': nelze přesunout soubor na cílovou cestu.');
        }

        $settingsMap = $definition['settings'];
        $pathKey = (string) $settingsMap['path'];
        $settingsUpdates[$pathKey] = $destinationRel;

        if (isset($settingsMap['width'], $settingsMap['height'])) {
            $widthKey = (string) $settingsMap['width'];
            $heightKey = (string) $settingsMap['height'];
            [$width, $height] = $repository->svgDimensions($cleanSvg);
            $settingsUpdates[$widthKey] = (string) $width;
            $settingsUpdates[$heightKey] = (string) $height;
        }

        $uploadCount++;
    }

    if ($uploadCount <= 0) {
        http_response_code(422);
        echo json_encode(['error' => 'Vyberte alespoň jeden SVG soubor k nahrání.']);
        exit;
    }

    $repository->saveSettingsBatch($settingsUpdates);
    $settings = $repository->readLogoSettings();

    $lightPath = (string) $settings['path'];
    $lightUpdatedAt = (string) $settings['updated_at'];
    $darkPath = (string) $settings['dark_path'];
    $darkUpdatedAt = (string) $settings['dark_updated_at'];
    $pdfPath = (string) $settings['pdf_path'];
    $pdfUpdatedAt = (string) $settings['pdf_updated_at'];
    $watermarkPath = (string) $settings['pdf_watermark_path'];
    $watermarkUpdatedAt = (string) $settings['pdf_watermark_updated_at'];

    $response = [
        'message' => $uploadCount > 1
            ? 'Soubory byly úspěšně nahrány.'
            : 'Soubor byl úspěšně nahrán.',
        'path' => $lightPath,
        'width' => (float) $settings['width'],
        'height' => (float) $settings['height'],
        'updated_at' => $lightUpdatedAt,
        'logos' => [
            'light' => [
                'path' => $lightPath,
                'width' => (float) $settings['width'],
                'height' => (float) $settings['height'],
                'updated_at' => $lightUpdatedAt,
                'url' => $buildAssetUrl($lightPath, $lightUpdatedAt),
            ],
            'dark' => [
                'path' => $darkPath,
                'width' => (float) $settings['dark_width'],
                'height' => (float) $settings['dark_height'],
                'updated_at' => $darkUpdatedAt,
                'url' => $darkPath !== '' ? $buildAssetUrl($darkPath, $darkUpdatedAt) : '',
            ],
            'pdf' => [
                'path' => $pdfPath,
                'width' => (float) $settings['pdf_width'],
                'height' => (float) $settings['pdf_height'],
                'updated_at' => $pdfUpdatedAt,
                'url' => $pdfPath !== '' ? $buildAssetUrl($pdfPath, $pdfUpdatedAt) : '',
            ],
            'watermark' => [
                'path' => $watermarkPath,
                'updated_at' => $watermarkUpdatedAt,
                'url' => $watermarkPath !== '' ? $buildAssetUrl($watermarkPath, $watermarkUpdatedAt) : '',
            ],
            'has_dark_logo' => $darkPath !== '',
        ],
    ];

    echo json_encode($response);
} catch (Throwable $e) {
    http_response_code(500);
    log_message('Admin logo upload failed: ' . $e->getMessage(), 'ERROR');
    echo json_encode(['error' => 'Nahrání souboru selhalo: ' . $e->getMessage()]);
}
