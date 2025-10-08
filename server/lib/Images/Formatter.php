<?php

declare(strict_types=1);

namespace Images;

final class Formatter
{
    public function buildRootUrl(string $base): string
    {
        $base = rtrim($base, '/');
        return $base . '/public/assets/images/upload';
    }

    public function sanitizeRelative(string $relative): string
    {
        $relative = str_replace(['\\'], '/', $relative);
        $relative = trim($relative, '/');
        $parts = [];
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($parts);
                continue;
            }
            $segment = preg_replace('#[^\p{L}\p{N}._ -]#u', '_', $segment);
            if ($segment === null) {
                continue;
            }
            $parts[] = (string) $segment;
        }
        return implode('/', $parts);
    }

    public function buildUrlPrefix(string $baseUrl, string $relative): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        if ($relative === '') {
            return $baseUrl;
        }
        $segments = explode('/', $relative);
        $segments = array_map(static fn($segment) => rawurlencode($segment), $segments);
        return $baseUrl . '/' . implode('/', $segments);
    }

    /**
     * @return array{name:string,rel:string,url:string}
     */
    public function formatDirectoryEntry(string $name, string $relative, string $url): array
    {
        return [
            'name' => $name,
            'rel' => $relative,
            'url' => $url,
        ];
    }

    /**
     * @return array{name:string,rel:string,url:string,thumbUrl:string,mtime:int,size:int}
     */
    public function formatImageEntry(string $name, string $relative, string $url, string $thumbUrl, int $mtime, int $size): array
    {
        return [
            'name' => $name,
            'rel' => $relative,
            'url' => $url,
            'thumbUrl' => $thumbUrl,
            'mtime' => $mtime,
            'size' => $size,
        ];
    }
}
