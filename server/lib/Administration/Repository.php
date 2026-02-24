<?php

declare(strict_types=1);

namespace Administration;

final class Repository
{
    // TODO: implement read/write functions with PDO

    public function __construct()
    {
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
        return [$w, $h];
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
