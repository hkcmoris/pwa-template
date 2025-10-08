<?php

declare(strict_types=1);

namespace Images;

use Throwable;

final class Repository
{
    private Formatter $formatter;

    private string $lastError = '';

    public function __construct(?Formatter $formatter = null)
    {
        $this->formatter = $formatter ?? new Formatter();
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    private function setError(string $message): void
    {
        $this->lastError = $message;
    }

    private function parseSize(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return -1;
        }
        $unit = strtoupper(substr($value, -1));
        $number = (float) $value;
        switch ($unit) {
            case 'G':
                $number *= 1024;
                // no break
            case 'M':
                $number *= 1024;
                // no break
            case 'K':
                $number *= 1024;
                break;
            default:
                // bytes
        }
        return (int) round($number);
    }

    /**
     * @param array{0?:int,1?:int,2?:int,3?:string,mime?:string,channels?:int,bits?:int} $info
     */
    private function gdCanDecode(array $info): bool
    {
        if (!isset($info[0], $info[1], $info['mime'])) {
            return false;
        }

        $width = (int) $info[0];
        $height = (int) $info[1];
        if ($width <= 0 || $height <= 0) {
            return false;
        }
        $mime = (string) $info['mime'];
        $bpp = 4;
        if ($mime === 'image/png') {
            $bpp = 5;
        } elseif ($mime === 'image/gif') {
            $bpp = 2;
        }
        $estimated = (int) ($width * $height * $bpp + 8_000_000);

        $limit = $this->parseSize((string) ini_get('memory_limit'));
        if ($limit < 0) {
            return true;
        }
        $used = function_exists('memory_get_usage') ? (int) memory_get_usage(true) : 0;
        return ($used + $estimated) < max(0, $limit - 16_000_000);
    }

    private function tryCwebp(string $source, string $destination, int $quality = 85): bool
    {
        if (!function_exists('shell_exec')) {
            return false;
        }
        $quality = max(10, min(100, $quality));
        $command = 'cwebp -quiet -q ' . (int) $quality . ' ' . escapeshellarg($source) . ' -o ' . escapeshellarg($destination) . ' 2>&1';
        @shell_exec($command);
        return is_file($destination) && @filesize($destination) > 0;
    }

    public function getRootDir(): string
    {
        $root = dirname(__DIR__) . '/public/assets/images/upload';
        if (!is_dir($root)) {
            @mkdir($root, 0775, true);
        }
        return realpath($root) ?: $root;
    }

    public function getRootUrl(string $base = ''): string
    {
        return $this->formatter->buildRootUrl($base);
    }

    public function sanitizeRelative(string $relative): string
    {
        return $this->formatter->sanitizeRelative($relative);
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    public function resolve(string $relative): array
    {
        $relative = $this->sanitizeRelative($relative);
        $root = $this->getRootDir();
        $full = $relative ? ($root . '/' . $relative) : $root;
        $full = str_replace(['\\'], '/', $full);
        $realRoot = str_replace(['\\'], '/', realpath($root) ?: $root);
        $realFull = str_replace(['\\'], '/', realpath($full) ?: $full);
        if (strpos($realFull, $realRoot) !== 0) {
            $relative = '';
            $realFull = $realRoot;
        }
        return [$realFull, $relative, $realRoot];
    }

    public function ensureDir(string $directory): bool
    {
        if (is_dir($directory)) {
            return true;
        }
        return @mkdir($directory, 0775, true);
    }

    /**
     * @return array{
     *     root:string,
     *     dir:string,
     *     rel:string,
     *     urlPrefix:string,
     *     dirs:list<array{name:string,rel:string,url:string}>,
     *     images:list<array{name:string,rel:string,url:string,thumbUrl:string,mtime:int,size:int}>
     * }
     */
    public function listDirectory(string $relative, string $baseUrl): array
    {
        [$directory, $relative, $root] = $this->resolve($relative);
        $relative = (string) $relative;
        $this->ensureDir($directory);
        $urlPrefix = $this->formatter->buildUrlPrefix($baseUrl, $relative);

        $directories = [];
        $images = [];
        foreach ((scandir($directory) ?: []) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $fullPath = $directory . '/' . $entry;
            $relativeChild = ltrim(($relative === '' ? '' : ($relative . '/')) . $entry, '/');
            $url = $urlPrefix . '/' . rawurlencode($entry);
            if (is_dir($fullPath)) {
                $directories[] = $this->formatter->formatDirectoryEntry($entry, $relativeChild, $url);
                continue;
            }
            $extension = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (!in_array($extension, ['webp', 'jpg', 'jpeg', 'png', 'gif', 'avif', 'svg'], true)) {
                continue;
            }
            if ($extension === 'webp' && preg_match('#\.thumb\.webp$#i', $entry)) {
                continue;
            }
            $thumbUrl = $url;
            if ($extension === 'webp') {
                $thumbFull = preg_replace('#\.webp$#i', '.thumb.webp', $fullPath);
                if ($thumbFull && is_file($thumbFull)) {
                    $thumbName = basename($thumbFull);
                    $thumbUrl = $urlPrefix . '/' . rawurlencode($thumbName);
                }
            }
            $images[] = $this->formatter->formatImageEntry(
                $entry,
                $relativeChild,
                $url,
                $thumbUrl,
                @filemtime($fullPath) ?: 0,
                @filesize($fullPath) ?: 0
            );
        }
        usort($directories, static fn($a, $b) => strcasecmp($a['name'], $b['name']));
        usort(
            $images,
            static fn($a, $b) => ($b['mtime'] <=> $a['mtime']) ?: strcasecmp($a['name'], $b['name'])
        );
        return [
            'root' => $root,
            'dir' => $directory,
            'rel' => $relative,
            'urlPrefix' => $urlPrefix,
            'dirs' => $directories,
            'images' => $images,
        ];
    }

    public function safeName(string $name, string $directory): string
    {
        $name = preg_replace('#\.[^.]+$#', '', $name);
        $name = preg_replace('#[^\p{L}\p{N}._ -]#u', '_', $name);
        if ($name === '' || $name === '.' || $name === '..') {
            $name = 'obrázek';
        }
        $base = $name;
        $index = 0;
        do {
            $candidate = $base . ($index ? '_' . $index : '') . '.webp';
            $index++;
        } while (file_exists($directory . '/' . $candidate) && $index < 10000);
        return $candidate;
    }

    public function convertToWebp(string $temporaryFile, string $destinationPath): bool
    {
        $info = @getimagesize($temporaryFile);
        if (!$info) {
            return false;
        }
        $mime = (string) $info['mime'];
        $width = (int) $info[0];
        $height = (int) $info[1];

        if (class_exists('Imagick')) {
            try {
                $imagick = new \Imagick();
                if ($imagick->readImage($temporaryFile)) {
                    if ($width > 6000 || $height > 6000) {
                        $imagick->setOption('webp:method', '6');
                        $imagick->thumbnailImage(4000, 4000, true, true);
                    }
                    $imagick->setImageFormat('webp');
                    try {
                        $imagick->setImageCompressionQuality(85);
                    } catch (Throwable $e) {
                        // ignore unsupported method
                    }
                    $success = $imagick->writeImage($destinationPath);
                    $imagick->clear();
                    $imagick->destroy();
                    if ($success) {
                        return true;
                    }
                }
            } catch (Throwable $e) {
                // fall back to GD
            }
        }

        if (!function_exists('imagewebp')) {
            if ($mime === 'image/webp') {
                return @move_uploaded_file($temporaryFile, $destinationPath) || @copy($temporaryFile, $destinationPath);
            }
            return false;
        }

        if (!$this->gdCanDecode($info)) {
            if ($mime === 'image/webp') {
                return @move_uploaded_file($temporaryFile, $destinationPath) || @copy($temporaryFile, $destinationPath);
            }
            if ($this->tryCwebp($temporaryFile, $destinationPath, 85)) {
                return true;
            }
            $this->setError('Obrázek je příliš velký pro zpracování na tomto serveru. Před nahráním jej prosím zmenšete.');
            return false;
        }

        $image = null;
        switch ($mime) {
            case 'image/jpeg':
                if (function_exists('imagecreatefromjpeg')) {
                    $image = @imagecreatefromjpeg($temporaryFile);
                }
                break;
            case 'image/png':
                if (function_exists('imagecreatefrompng')) {
                    $image = @imagecreatefrompng($temporaryFile);
                    if ($image) {
                        @imagepalettetotruecolor($image);
                        @imagealphablending($image, true);
                        @imagesavealpha($image, true);
                    }
                }
                break;
            case 'image/gif':
                if (function_exists('imagecreatefromgif')) {
                    $image = @imagecreatefromgif($temporaryFile);
                }
                break;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    $image = @imagecreatefromwebp($temporaryFile);
                } else {
                    return @move_uploaded_file($temporaryFile, $destinationPath) || @copy($temporaryFile, $destinationPath);
                }
                break;
            default:
                return false;
        }
        if (!$image) {
            return false;
        }
        $result = @imagewebp($image, $destinationPath, 85);
        @imagedestroy($image);
        return (bool) $result;
    }

    public function move(string $relativeFile, string $relativeDestinationDir): bool
    {
        [$fullFile] = $this->resolve($relativeFile);
        [$destDir] = $this->resolve($relativeDestinationDir);
        if (!is_file($fullFile)) {
            return false;
        }
        if (!$this->ensureDir($destDir)) {
            return false;
        }
        $name = basename($fullFile);
        $sourceThumb = preg_replace('#\.webp$#i', '.thumb.webp', $fullFile);
        $hasThumb = $sourceThumb && is_file($sourceThumb);
        $target = $destDir . '/' . $name;
        $index = 0;
        do {
            $candidateName = $index === 0
                ? $name
                : preg_replace('#(.*?)(?:_(\d+))?\.(webp|[^.]+)$#', '$1_' . $index . '.$3', $name);
            $target = $destDir . '/' . $candidateName;
            $targetThumb = preg_replace('#\.webp$#i', '.thumb.webp', $target);
            $index++;
        } while ((file_exists($target) || ($hasThumb && $targetThumb && file_exists($targetThumb))) && $index < 10000);
        $success = @rename($fullFile, $target);
        if ($success && $hasThumb) {
            @rename($sourceThumb, $targetThumb);
        }
        return (bool) $success;
    }

    public function rename(string $relativeFile, string $newBase): bool
    {
        [$fullFile] = $this->resolve($relativeFile);
        if (!is_file($fullFile)) {
            return false;
        }
        $directory = dirname($fullFile);
        $newBase = preg_replace('#[^\p{L}\p{N}._ -]#u', '_', $newBase);
        if ($newBase === '' || $newBase === '.' || $newBase === '..') {
            return false;
        }
        $newName = $newBase . '.webp';
        $sourceThumb = preg_replace('#\.webp$#i', '.thumb.webp', $fullFile);
        $hasThumb = $sourceThumb && is_file($sourceThumb);
        $index = 0;
        do {
            $target = $directory . '/' . ($index === 0 ? $newName : ($newBase . '_' . $index . '.webp'));
            $targetThumb = preg_replace('#\.webp$#i', '.thumb.webp', $target);
            $index++;
        } while ((file_exists($target) || ($hasThumb && $targetThumb && file_exists($targetThumb))) && $index < 10000);
        $success = @rename($fullFile, $target);
        if ($success && $hasThumb) {
            @rename($sourceThumb, $targetThumb);
        }
        return (bool) $success;
    }

    public function delete(string $relativeFile): bool
    {
        [$fullFile] = $this->resolve($relativeFile);
        if (!is_file($fullFile)) {
            return false;
        }
        $success = @unlink($fullFile);
        $thumb = preg_replace('#\.webp$#i', '.thumb.webp', $fullFile);
        if ($thumb && is_file($thumb)) {
            @unlink($thumb);
        }
        return (bool) $success;
    }

    public function renameDir(string $relativeDir, string $newBase): bool
    {
        [$fullDir] = $this->resolve($relativeDir);
        if (!is_dir($fullDir)) {
            return false;
        }
        $parent = dirname($fullDir);
        $newBase = preg_replace('#[^\p{L}\p{N}._ -]#u', '_', $newBase);
        if ($newBase === '' || $newBase === '.' || $newBase === '..') {
            return false;
        }
        $target = $parent . '/' . $newBase;
        $index = 0;
        while (file_exists($target) && $index < 10000) {
            $index++;
            $target = $parent . '/' . $newBase . '_' . $index;
        }
        return @rename($fullDir, $target);
    }

    public function moveDir(string $relativeDir, string $relativeDestinationDir): bool
    {
        [$fullDir] = $this->resolve($relativeDir);
        [$destDir] = $this->resolve($relativeDestinationDir);
        if (!is_dir($fullDir)) {
            return false;
        }
        if (!$this->ensureDir($destDir)) {
            return false;
        }
        $normalisedFull = str_replace('\\', '/', realpath($fullDir) ?: $fullDir);
        $normalisedDest = str_replace('\\', '/', realpath($destDir) ?: $destDir);
        if ($normalisedDest === $normalisedFull || strpos($normalisedDest . '/', $normalisedFull . '/') === 0) {
            return false;
        }
        $name = basename($fullDir);
        $target = $destDir . '/' . $name;
        $index = 0;
        while (file_exists($target) && $index < 10000) {
            $index++;
            $target = $destDir . '/' . preg_replace('#(.*?)(?:_(\d+))?$#', '$1_' . $index, $name);
        }
        return @rename($fullDir, $target);
    }

    public function deleteDir(string $relativeDir, bool $recursive = false): bool
    {
        [$fullDir, $relativeDir, $root] = $this->resolve($relativeDir);
        if (!is_dir($fullDir)) {
            return false;
        }
        if (!$recursive) {
            return @rmdir($fullDir);
        }
        $root = str_replace('\\', '/', $root);
        $fullDir = str_replace('\\', '/', $fullDir);
        if (strpos($fullDir, $root) !== 0) {
            return false;
        }
        $success = true;
        $entries = scandir($fullDir) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $fullDir . '/' . $entry;
            if (is_dir($path)) {
                $success = $this->deleteDir($relativeDir . '/' . $entry, true) && $success;
            } else {
                $success = @unlink($path) && $success;
            }
        }
        return @rmdir($fullDir) && $success;
    }

    public function createDir(string $relativeParent, string $name): bool
    {
        [$parentFull] = $this->resolve($relativeParent);
        if (!$this->ensureDir($parentFull)) {
            return false;
        }
        $segment = trim(str_replace(['\\', '/'], '-', $name));
        $segment = preg_replace('#[^\p{L}\p{N}._ -]#u', '_', $segment);
        if ($segment === '' || $segment === '.' || $segment === '..') {
            $segment = 'složka';
        }
        $base = $segment;
        $index = 0;
        do {
            $candidate = $base . ($index ? '_' . $index : '');
            $index++;
            $target = $parentFull . '/' . $candidate;
        } while (file_exists($target) && $index < 10000);
        return @mkdir($target, 0775, true);
    }

    public function generateThumb(string $webpPath, int $size = 96): bool
    {
        $thumbPath = preg_replace('#\.webp$#i', '.thumb.webp', $webpPath);
        if (!$thumbPath || !is_file($webpPath)) {
            return false;
        }
        if (class_exists('Imagick')) {
            try {
                $imagick = new \Imagick();
                if ($imagick->readImage($webpPath)) {
                    $imagick->setImageFormat('webp');
                    try {
                        $imagick->setImageCompressionQuality(75);
                    } catch (Throwable $e) {
                        // ignore unsupported method
                    }
                    $imagick->thumbnailImage($size, $size, true, true);
                    $success = $imagick->writeImage($thumbPath);
                    $imagick->clear();
                    $imagick->destroy();
                    return (bool) $success;
                }
            } catch (Throwable $e) {
                // fall through to GD
            }
        }
        if (function_exists('imagecreatefromwebp') && function_exists('imagewebp')) {
            $source = @imagecreatefromwebp($webpPath);
            if (!$source) {
                return false;
            }
            $width = imagesx($source);
            $height = imagesy($source);
            $ratio = min($size / max(1, $width), $size / max(1, $height), 1);
            $newWidth = max(1, (int) floor($width * $ratio));
            $newHeight = max(1, (int) floor($height * $ratio));
            $destination = @imagecreatetruecolor($newWidth, $newHeight);
            if (!$destination) {
                @imagedestroy($source);
                return false;
            }
            @imagealphablending($destination, false);
            @imagesavealpha($destination, true);
            $transparent = imagecolorallocatealpha($destination, 0, 0, 0, 127);
            @imagefilledrectangle($destination, 0, 0, $newWidth, $newHeight, $transparent);
            @imagecopyresampled($destination, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            $success = @imagewebp($destination, $thumbPath, 75);
            @imagedestroy($destination);
            @imagedestroy($source);
            return (bool) $success;
        }
        return false;
    }
}
