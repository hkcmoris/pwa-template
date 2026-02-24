<?php

declare(strict_types=1);

namespace Administration;

use PDO;
use Throwable;

use function get_db_connection;

final class Repository
{
    private const DEFAULT_LOGO_PATH = 'default-logo.svg';

    private const MAX_LOGO_WIDTH = 130;

    private const MAX_LOGO_HEIGHT = 30;

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? get_db_connection();
    }

    public function set(string $key, string $value): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO app_settings (k, v) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE v = VALUES(v), updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([':k' => $key, ':v' => $value]);
    }

    /**
     * @param list<string> $keys
     * @return array<string, string>
     */
    public function getMany(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($keys as $index => $key) {
            $placeholder = ':k' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $key;
        }

        $stmt = $this->pdo->prepare(
            'SELECT k, v FROM app_settings WHERE k IN (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($params);

        /** @var array<int, array{k: string, v: string}> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['k']] = (string) $row['v'];
        }

        return $result;
    }

    public function saveLogoSettings(string $path, float $width, float $height, string $updatedAt): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->set('logo_path', $path);
            $this->set('logo_width', (string) $width);
            $this->set('logo_height', (string) $height);
            $this->set('logo_updated_at', $updatedAt);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return array{path: string, width: float, height: float, updated_at: string}
     */
    public function readLogoSettings(): array
    {
        $settings = $this->getMany(['logo_path', 'logo_width', 'logo_height', 'logo_updated_at']);
        return [
            'path' => $settings['logo_path'] ?? self::DEFAULT_LOGO_PATH,
            'width' => isset($settings['logo_width']) ? (float) $settings['logo_width'] : self::MAX_LOGO_WIDTH,
            'height' => isset($settings['logo_height']) ? (float) $settings['logo_height'] : self::MAX_LOGO_HEIGHT,
            'updated_at' => $settings['logo_updated_at'] ?? '',
        ];
    }

    /**
     * Extract width and height from SVG string. Returns [width, height] or defaults if not found.
     * @return array{0: float, 1: float}
     */
    public function svgDimensions(string $svg): array
    {
        $w = $h = null;

        if (preg_match('/<svg[^>]*\bwidth=["\']([^"\']+)["\']/i', $svg, $m)) {
            $w = (float)preg_replace('/[^0-9.]/', '', $m[1]);
        }
        if (preg_match('/<svg[^>]*\bheight=["\']([^"\']+)["\']/i', $svg, $m)) {
            $h = (float)preg_replace('/[^0-9.]/', '', $m[1]);
        }
        if (
            (!$w || !$h) &&
            preg_match(
                '/\bviewBox=["\']\s*[-0-9.]+\s+[-0-9.]+\s+([0-9.]+)\s+([0-9.]+)\s*["\']/i',
                $svg,
                $m
            )
        ) {
            $w = $w ?: (float)$m[1];
            $h = $h ?: (float)$m[2];
        }

        if (!$w || !$h) {
            $w = 130;
            $h = 30;
        }
        
        [$w, $h] = $this->clampLogo((float)$w, (float)$h);

        return [$w, $h];
    }

    /**
     * Clamp dimensions to max allowed, preserving aspect ratio. Returns [width, height].
     * @return array{0: float, 1: float}
     */
    private function clampLogo(float $w, float $h): array
    {
        if ($w <= 0 || $h <= 0) {
            return [self::MAX_LOGO_WIDTH, self::MAX_LOGO_HEIGHT];
        }

        // scale down only (never upscale)
        $scale = min(
            1.0,
            self::MAX_LOGO_WIDTH / $w,
            self::MAX_LOGO_HEIGHT / $h
        );

        return [$w * $scale, $h * $scale];
    }

    /**
     * VERY basic sanitization (good enough for “logo svg”)
     */
    public function sanitizeSvg(string $svg): string
    {
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
}
