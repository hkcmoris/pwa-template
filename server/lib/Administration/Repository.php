<?php

declare(strict_types=1);

namespace Administration;

use PDO;
use Throwable;

use function get_db_connection;

final class Repository
{
    private const DEFAULT_LOGO_PATH = 'default-logo.svg';

    private const DEFAULT_LOGO_WIDTH = 130;

    private const DEFAULT_LOGO_HEIGHT = 30;

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? get_db_connection();
    }

    public function set(string $key, string $value): void
    {
        log_message("Saving setting: $key = $value", 'DEBUG');
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
        log_message("Fetching settings for keys: " . implode(', ', $keys), 'DEBUG');
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
        log_message("Saving logo settings: path=$path, width=$width, height=$height, updated_at=$updatedAt", 'DEBUG');
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
        log_message("Reading logo settings", 'DEBUG');
        $settings = $this->getMany(['logo_path', 'logo_width', 'logo_height', 'logo_updated_at']);
        return [
            'path' => $settings['logo_path'] ?? self::DEFAULT_LOGO_PATH,
            'width' => isset($settings['logo_width']) ? (float) $settings['logo_width'] : self::DEFAULT_LOGO_WIDTH,
            'height' => isset($settings['logo_height']) ? (float) $settings['logo_height'] : self::DEFAULT_LOGO_HEIGHT,
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
        log_message("Extracted SVG dimensions: width=$w, height=$h", 'DEBUG');
        return [$w, $h];
    }

    /**
     * VERY basic sanitization (good enough for “logo svg”)
     */
    public function sanitizeSvg(string $svg): string
    {
        log_message("Sanitizing SVG content", 'DEBUG');
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
