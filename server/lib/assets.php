<?php

declare(strict_types=1);

if (!function_exists('vite_asset')) {
    /**
     * Resolve a build asset entry from the Vite manifest.
     *
     * @param string $entry Relative source path (e.g. 'src/main.ts').
     *
     * @return array<string,mixed>|null
     */
    function vite_asset(string $entry): ?array
    {
        static $manifest = null;
        $manifestPath = __DIR__ . '/../public/assets/.vite/manifest.json';

        if ($manifest === null) {
            if (is_file($manifestPath)) {
                $decoded = json_decode((string) file_get_contents($manifestPath), true);
                $manifest = is_array($decoded) ? $decoded : [];
            } else {
                $manifest = [];
            }
        }

        $value = $manifest[$entry] ?? null;

        return is_array($value) ? $value : null;
    }
}

if (!function_exists('vite_asset_href')) {
    function vite_asset_href(string $entry, bool $isDevEnv, string $basePath): ?string
    {
        if ($isDevEnv) {
            return 'http://localhost:5173/' . ltrim($entry, '/');
        }

        $asset = vite_asset($entry);
        if (!$asset || empty($asset['file'])) {
            return null;
        }

        $prefix = $basePath !== '' ? $basePath : '';

        return $prefix . '/public/assets/' . $asset['file'];
    }
}
