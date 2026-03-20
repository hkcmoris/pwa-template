<?php

declare(strict_types=1);

namespace Administration;

use PDO;
use Throwable;

use function get_db_connection;

final class Repository
{
    private const DEFAULT_LOGO_PATH = 'public/assets/logo/default-logo.svg';

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

    /**
     * @param list<string> $keys
     * @return array<string, array{v: string, updated_at: string}>
     */
    public function getManyWithMeta(array $keys): array
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
            'SELECT k, v, updated_at FROM app_settings WHERE k IN (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($params);

        /** @var array<int, array{k: string, v: string, updated_at: string}> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['k']] = [
                'v' => (string) $row['v'],
                'updated_at' => (string) $row['updated_at'],
            ];
        }

        return $result;
    }

    public function saveLogoSettings(string $path, float $width, float $height): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->set('logo_path', $path);
            $this->set('logo_width', (string) $width);
            $this->set('logo_height', (string) $height);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @param array<string, string> $settings
     */
    public function saveSettingsBatch(array $settings): void
    {
        if ($settings === []) {
            return;
        }

        $this->pdo->beginTransaction();
        try {
            foreach ($settings as $key => $value) {
                $this->set((string) $key, (string) $value);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return array{
     *     path: string,
     *     width: float,
     *     height: float,
     *     updated_at: string,
     *     dark_path: string,
     *     dark_width: float,
     *     dark_height: float,
     *     dark_updated_at: string,
     *     pdf_path: string,
     *     pdf_width: float,
     *     pdf_height: float,
     *     pdf_updated_at: string,
     *     pdf_watermark_path: string,
     *     pdf_watermark_updated_at: string
     * }
     */
    public function readLogoSettings(): array
    {
        $settings = $this->getMany([
            'logo_path',
            'logo_width',
            'logo_height',
            'logo_dark_path',
            'logo_dark_width',
            'logo_dark_height',
            'logo_pdf_path',
            'logo_pdf_width',
            'logo_pdf_height',
            'pdf_watermark_tile_path',
        ]);
        $pathMeta = $this->getManyWithMeta([
            'logo_path',
            'logo_dark_path',
            'logo_pdf_path',
            'pdf_watermark_tile_path',
        ]);
        return [
            'path' => $settings['logo_path'] ?? self::DEFAULT_LOGO_PATH,
            'width' => isset($settings['logo_width']) ? (float) $settings['logo_width'] : self::MAX_LOGO_WIDTH,
            'height' => isset($settings['logo_height']) ? (float) $settings['logo_height'] : self::MAX_LOGO_HEIGHT,
            'updated_at' => (string) ($pathMeta['logo_path']['updated_at'] ?? ''),
            'dark_path' => $settings['logo_dark_path'] ?? '',
            'dark_width' => isset($settings['logo_dark_width'])
                ? (float) $settings['logo_dark_width']
                : self::MAX_LOGO_WIDTH,
            'dark_height' => isset($settings['logo_dark_height'])
                ? (float) $settings['logo_dark_height']
                : self::MAX_LOGO_HEIGHT,
            'dark_updated_at' => (string) ($pathMeta['logo_dark_path']['updated_at'] ?? ''),
            'pdf_path' => $settings['logo_pdf_path'] ?? '',
            'pdf_width' => isset($settings['logo_pdf_width'])
                ? (float) $settings['logo_pdf_width']
                : self::MAX_LOGO_WIDTH,
            'pdf_height' => isset($settings['logo_pdf_height'])
                ? (float) $settings['logo_pdf_height']
                : self::MAX_LOGO_HEIGHT,
            'pdf_updated_at' => (string) ($pathMeta['logo_pdf_path']['updated_at'] ?? ''),
            'pdf_watermark_path' => $settings['pdf_watermark_tile_path'] ?? '',
            'pdf_watermark_updated_at' => (string) ($pathMeta['pdf_watermark_tile_path']['updated_at'] ?? ''),
        ];
    }

    /**
     * @param array{
     *     company_name: string,
     *     country_code: string,
     *     state: string,
     *     city: string,
     *     street: string,
     *     street_number: string,
     *     post_code: string
     * } $address
     */
    public function saveCompanyAddress(array $address): int
    {
        $this->pdo->beginTransaction();
        try {
            $this->set('company_name', $address['company_name']);
            $settings = $this->getMany(['company_address_id']);
            $existingId = isset($settings['company_address_id']) ? (int) $settings['company_address_id'] : 0;

            if ($existingId > 0) {
                $update = $this->pdo->prepare(
                    'UPDATE addresses
                     SET country_code = :country_code,
                         state = :state,
                         city = :city,
                         street = :street,
                         street_number = :street_number,
                         post_code = :post_code
                     WHERE id = :id'
                );
                $update->execute([
                    ':id' => $existingId,
                    ':country_code' => $address['country_code'],
                    ':state' => $address['state'],
                    ':city' => $address['city'],
                    ':street' => $address['street'],
                    ':street_number' => $address['street_number'],
                    ':post_code' => $address['post_code'],
                ]);

                if ($update->rowCount() > 0 || $this->addressExists($existingId)) {
                    $this->set('company_address_id', (string) $existingId);
                    $this->pdo->commit();
                    return $existingId;
                }
            }

            $insert = $this->pdo->prepare(
                'INSERT INTO addresses (country_code, state, city, street, street_number, post_code)
                 VALUES (:country_code, :state, :city, :street, :street_number, :post_code)'
            );
            $insert->execute([
                ':country_code' => $address['country_code'],
                ':state' => $address['state'],
                ':city' => $address['city'],
                ':street' => $address['street'],
                ':street_number' => $address['street_number'],
                ':post_code' => $address['post_code'],
            ]);

            $addressId = (int) $this->pdo->lastInsertId();
            $this->set('company_address_id', (string) $addressId);
            $this->pdo->commit();

            return $addressId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return array{
     *     id: int,
     *     company_name: string,
     *     country_code: string,
     *     state: string,
     *     city: string,
     *     street: string,
     *     street_number: string,
     *     post_code: string
     * }|null
     */
    public function readCompanyAddress(): ?array
    {
        $settings = $this->getMany(['company_address_id', 'company_name']);
        $addressId = isset($settings['company_address_id']) ? (int) $settings['company_address_id'] : 0;
        $companyName = trim((string) ($settings['company_name'] ?? ''));

        if ($addressId <= 0) {
            if ($companyName === '') {
                return null;
            }

            return [
                'id' => 0,
                'company_name' => $companyName,
                'country_code' => '',
                'state' => '',
                'city' => '',
                'street' => '',
                'street_number' => '',
                'post_code' => '',
            ];
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, country_code, state, city, street, street_number, post_code
             FROM addresses
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $addressId]);

        /** @var array{
         *     id: int|string,
         *     country_code: string,
         *     state: string,
         *     city: string,
         *     street: string,
         *     street_number: string,
         *     post_code: string
         * }|false $row
         */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            if ($companyName === '') {
                return null;
            }

            return [
                'id' => 0,
                'company_name' => $companyName,
                'country_code' => '',
                'state' => '',
                'city' => '',
                'street' => '',
                'street_number' => '',
                'post_code' => '',
            ];
        }

        return [
            'id' => (int) $row['id'],
            'company_name' => $companyName,
            'country_code' => (string) $row['country_code'],
            'state' => (string) $row['state'],
            'city' => (string) $row['city'],
            'street' => (string) $row['street'],
            'street_number' => (string) $row['street_number'],
            'post_code' => (string) $row['post_code'],
        ];
    }

    private function addressExists(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM addresses WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetchColumn() !== false;
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
