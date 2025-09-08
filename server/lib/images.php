<?php
// Image manager helpers: path resolution, listing, webp conversion

declare(strict_types=1);

// --- Internal error storage for friendlier messages from this module ---
function img__set_error(string $msg): void { $GLOBALS['__img_last_error'] = $msg; }
function img_last_error(): string { return (string)($GLOBALS['__img_last_error'] ?? ''); }

// Parse php.ini style sizes like 128M/1G into bytes
function img__parse_size(string $val): int {
  $val = trim($val);
  if ($val === '' || $val === '-1') return -1; // no limit
  $unit = strtoupper(substr($val, -1));
  $num = (float)$val;
  switch ($unit) {
    case 'G': $num *= 1024;
    // no break
    case 'M': $num *= 1024;
    // no break
    case 'K': $num *= 1024; break;
    default: /* bytes */
  }
  return (int)round($num);
}

// Estimate whether GD can safely decode this image within memory limits
function img__gd_can_decode(array $info): bool {
  $w = (int)($info[0] ?? 0);
  $h = (int)($info[1] ?? 0);
  if ($w <= 0 || $h <= 0) return false;
  $mime = (string)($info['mime'] ?? '');
  // Rough bytes-per-pixel estimates + overhead
  $bpp = 4; // default for truecolor
  if ($mime === 'image/png') $bpp = 5; // RGBA + overhead
  elseif ($mime === 'image/gif') $bpp = 2; // palette based
  $estimated = (int)($w * $h * $bpp + 8_000_000); // add ~8MB headroom

  $limit = img__parse_size((string)ini_get('memory_limit'));
  if ($limit < 0) return true; // unlimited
  $used = function_exists('memory_get_usage') ? (int)memory_get_usage(true) : 0;
  // Keep a safety margin (~16MB) below the hard limit
  return ($used + $estimated) < max(0, $limit - 16_000_000);
}

// Optional: try external `cwebp` binary to convert without PHP memory pressure
function img__try_cwebp(string $src, string $dest, int $quality = 85): bool {
  // Disabled exec environments will simply return false
  if (!function_exists('shell_exec')) return false;
  $q = max(10, min(100, $quality));
  $cmd = 'cwebp -quiet -q ' . (int)$q . ' ' . escapeshellarg($src) . ' -o ' . escapeshellarg($dest) . ' 2>&1';
  $out = @shell_exec($cmd);
  // cwebp prints nothing on success with -quiet; file existence is our signal
  return is_file($dest) && @filesize($dest) > 0;
}

// Root on disk where uploads live
function img_root_dir(): string {
  $root = __DIR__ . '/../public/assets/images/upload';
  if (!is_dir($root)) @mkdir($root, 0775, true);
  return realpath($root) ?: $root;
}

// Public URL prefix for uploads
function img_root_url(string $base = ''): string {
  $base = rtrim($base, '/');
  return $base . '/public/assets/images/upload';
}

// Sanitize a relative path submitted by client and ensure it stays under root
function img_sanitize_rel(string $rel): string {
  $rel = str_replace(['\\'], '/', $rel);
  $rel = trim($rel, '/');
  // Collapse dot segments
  $parts = [];
  foreach (explode('/', $rel) as $seg) {
    if ($seg === '' || $seg === '.') continue;
    if ($seg === '..') { array_pop($parts); continue; }
    // allow Unicode letters/numbers + space, underscore, dot, dash
    $seg = preg_replace('#[^\p{L}\p{N}._ -]#u', '_', $seg);
    if ($seg !== '') $parts[] = $seg;
  }
  return implode('/', $parts);
}

// Resolve a relative path to full path, ensuring it is under root
function img_resolve(string $rel): array {
  $rel = img_sanitize_rel($rel);
  $root = img_root_dir();
  $full = $rel ? ($root . '/' . $rel) : $root;
  $full = str_replace(['\\'], '/', $full);
  $realRoot = str_replace(['\\'], '/', realpath($root) ?: $root);
  $realFull = str_replace(['\\'], '/', realpath($full) ?: $full);
  if (strpos($realFull, $realRoot) !== 0) {
    // Reset to root if outside
    $rel = '';
    $realFull = $realRoot;
  }
  return [$realFull, $rel, $realRoot];
}

// Ensure a directory exists
function img_ensure_dir(string $dir): bool {
  if (is_dir($dir)) return true;
  return @mkdir($dir, 0775, true);
}

// List a directory: returns [dirs => [name, rel, url], images => [name, rel, url, mtime, size]]
function img_list(string $rel, string $baseUrl): array {
  [$dir, $rel, $root] = img_resolve($rel);
  img_ensure_dir($dir);
  $baseUrl = rtrim($baseUrl, '/');
  // Build a URL-safe prefix by percent-encoding each segment of the relative path
  $relUrlEnc = '';
  if ($rel !== '') {
    $segments = explode('/', $rel);
    $segments = array_map(static fn($s) => rawurlencode($s), $segments);
    $relUrlEnc = '/' . implode('/', $segments);
  }
  $urlPrefix = $baseUrl . $relUrlEnc;

  $dirs = [];
  $images = [];
  foreach ((scandir($dir) ?: []) as $entry) {
    if ($entry === '.' || $entry === '..') continue;
    $full = $dir . '/' . $entry;
    $relChild = ltrim(($rel === '' ? '' : ($rel . '/')) . $entry, '/');
    $url = $urlPrefix . '/' . rawurlencode($entry);
    if (is_dir($full)) {
      $dirs[] = ['name' => $entry, 'rel' => $relChild, 'url' => $url];
    } else {
      $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
      if (in_array($ext, ['webp','jpg','jpeg','png','gif','avif','svg'], true)) {
        // Skip generated thumbnails (e.g., *.thumb.webp)
        if ($ext === 'webp' && preg_match('#\.thumb\.webp$#i', $entry)) continue;
        $thumbUrl = $url;
        if ($ext === 'webp') {
          $thumbFull = preg_replace('#\.webp$#i', '.thumb.webp', $full);
          if (is_file($thumbFull)) {
            $thumbName = basename($thumbFull);
            $thumbUrl = $urlPrefix . '/' . rawurlencode($thumbName);
          }
        }
        $images[] = [
          'name' => $entry,
          'rel' => $relChild,
          'url' => $url,
          'thumbUrl' => $thumbUrl,
          'mtime' => @filemtime($full) ?: 0,
          'size' => @filesize($full) ?: 0,
        ];
      }
    }
  }
  // Sort dirs A-Z, images by mtime desc
  usort($dirs, fn($a,$b)=> strcasecmp($a['name'],$b['name']));
  usort($images, fn($a,$b)=> ($b['mtime'] <=> $a['mtime']) ?: strcasecmp($a['name'],$b['name']));
  return ['root' => $root, 'dir' => $dir, 'rel' => $rel, 'urlPrefix' => $urlPrefix, 'dirs' => $dirs, 'images' => $images];
}

// Generate a safe filename with .webp extension, avoiding collisions
function img_safe_name(string $name, string $dir): string {
  $name = preg_replace('#\.[^.]+$#', '', $name); // strip extension
  $name = preg_replace('#[^\p{L}\p{N}._ -]#u', '_', $name);
  if ($name === '' || $name === '.' || $name === '..') $name = 'obrázek';
  $base = $name;
  $i = 0;
  do {
    $candidate = $base . ($i ? "_$i" : '') . '.webp';
    $i++;
  } while (file_exists($dir . '/' . $candidate) && $i < 10000);
  return $candidate;
}

// Convert an uploaded file (tmp path) into WebP at $destPath. Returns bool.
function img_convert_to_webp(string $tmpFile, string $destPath): bool {
  $info = @getimagesize($tmpFile);
  if (!$info) return false;
  $mime = $info['mime'] ?? '';
  $w = (int)($info[0] ?? 0);
  $h = (int)($info[1] ?? 0);

  // 1) Try Imagick if available
  if (class_exists('Imagick')) {
    try {
      $im = new Imagick();
      if ($im->readImage($tmpFile)) {
        // If extremely large, generate a thumbnail to reduce memory/size
        if ($w > 6000 || $h > 6000) {
          $im->setOption('webp:method', '6');
          $im->thumbnailImage(4000, 4000, true, true);
        }
        $im->setImageFormat('webp');
        if (method_exists($im, 'setImageCompressionQuality')) {
          $im->setImageCompressionQuality(85);
        }
        $ok = $im->writeImage($destPath);
        $im->clear();
        $im->destroy();
        if ($ok) return true;
      }
    } catch (Throwable $e) {
      // ignore and try GD below
    }
  }

  // 2) Try GD if available
  if (!function_exists('imagewebp')) {
    // If input is already webp, copy it over even without GD
    if ($mime === 'image/webp') {
      return @move_uploaded_file($tmpFile, $destPath) || @copy($tmpFile, $destPath);
    }
    return false;
  }

  // If the image is likely too large for GD, try external tool first
  if (!img__gd_can_decode($info)) {
    if ($mime === 'image/webp') {
      return @move_uploaded_file($tmpFile, $destPath) || @copy($tmpFile, $destPath);
    }
    if (img__try_cwebp($tmpFile, $destPath, 85)) return true;
    img__set_error('Obrázek je příliš velký pro zpracování na tomto serveru. Před nahráním jej prosím zmenšete.');
    return false;
  }

  $image = null;
  switch ($mime) {
    case 'image/jpeg':
      if (function_exists('imagecreatefromjpeg')) $image = @imagecreatefromjpeg($tmpFile);
      break;
    case 'image/png':
      if (function_exists('imagecreatefrompng')) {
        $image = @imagecreatefrompng($tmpFile);
        if ($image) {
          @imagepalettetotruecolor($image);
          @imagealphablending($image, true);
          @imagesavealpha($image, true);
        }
      }
      break;
    case 'image/gif':
      if (function_exists('imagecreatefromgif')) $image = @imagecreatefromgif($tmpFile);
      break;
    case 'image/webp':
      if (function_exists('imagecreatefromwebp')) $image = @imagecreatefromwebp($tmpFile);
      else return @move_uploaded_file($tmpFile, $destPath) || @copy($tmpFile, $destPath);
      break;
    default:
      return false;
  }
  if (!$image) return false;
  $ok = @imagewebp($image, $destPath, 85);
  @imagedestroy($image);
  return (bool)$ok;
}

// Move a file safely within the root
function img_move(string $relFile, string $relDestDir): bool {
  [$fullFile, $relFile] = img_resolve($relFile);
  [$destDir, $relDestDir] = img_resolve($relDestDir);
  if (!is_file($fullFile)) return false;
  if (!img_ensure_dir($destDir)) return false;
  $name = basename($fullFile);
  $srcThumb = preg_replace('#\.webp$#i', '.thumb.webp', $fullFile);
  $hasThumb = is_file($srcThumb);
  $target = $destDir . '/' . $name;
  $i = 0;
  do {
    $target = $destDir . '/' . ($i === 0 ? $name : preg_replace('#(.*?)(?:_(\d+))?\.(webp|[^.]+)$#', '$1_' . $i . '.$3', $name));
    $targetThumb = preg_replace('#\.webp$#i', '.thumb.webp', $target);
    $i++;
  } while ((file_exists($target) || ($hasThumb && file_exists($targetThumb))) && $i < 10000);
  $ok = @rename($fullFile, $target);
  if ($ok && $hasThumb) {
    @rename($srcThumb, $targetThumb);
  }
  return (bool)$ok;
}

// Rename a file to new base name (without extension), keeping/forcing .webp
function img_rename(string $relFile, string $newBase): bool {
  [$fullFile, $relFile] = img_resolve($relFile);
  if (!is_file($fullFile)) return false;
  $dir = dirname($fullFile);
  $newBase = preg_replace('#[^\p{L}\p{N}._ -]#u', '_', $newBase);
  if ($newBase === '' || $newBase === '.' || $newBase === '..') return false;
  $newName = $newBase . '.webp';
  $srcThumb = preg_replace('#\.webp$#i', '.thumb.webp', $fullFile);
  $hasThumb = is_file($srcThumb);
  $i = 0;
  do {
    $target = $dir . '/' . ($i === 0 ? $newName : ($newBase . '_' . $i . '.webp'));
    $targetThumb = preg_replace('#\.webp$#i', '.thumb.webp', $target);
    $i++;
  } while ((file_exists($target) || ($hasThumb && file_exists($targetThumb))) && $i < 10000);
  $ok = @rename($fullFile, $target);
  if ($ok && $hasThumb) {
    @rename($srcThumb, $targetThumb);
  }
  return (bool)$ok;
}

// Delete a file safely within the root
function img_delete(string $relFile): bool {
  [$fullFile, $relFile] = img_resolve($relFile);
  if (!is_file($fullFile)) return false;
  $ok = @unlink($fullFile);
  $thumb = preg_replace('#\.webp$#i', '.thumb.webp', $fullFile);
  if (is_file($thumb)) @unlink($thumb);
  return $ok;
}

// Rename a directory under the root
function img_rename_dir(string $relDir, string $newBase): bool {
  [$fullDir, $relDir] = img_resolve($relDir);
  if (!is_dir($fullDir)) return false;
  $parent = dirname($fullDir);
  $newBase = preg_replace('#[^\p{L}\p{N}._ -]#u', '_', $newBase);
  if ($newBase === '' || $newBase === '.' || $newBase === '..') return false;
  $target = $parent . '/' . $newBase;
  $i = 0;
  while (file_exists($target) && $i < 10000) {
    $i++;
    $target = $parent . '/' . $newBase . '_' . $i;
  }
  return @rename($fullDir, $target);
}

// Move a directory under the root into another directory
function img_move_dir(string $relDir, string $relDestDir): bool {
  [$fullDir, $relDir, $root] = img_resolve($relDir);
  [$destDir, $relDestDir, $root2] = img_resolve($relDestDir);
  if (!is_dir($fullDir)) return false;
  if (!img_ensure_dir($destDir)) return false;
  // Prevent moving a dir into itself or its own descendant
  $normFull = str_replace('\\', '/', realpath($fullDir) ?: $fullDir);
  $normDest = str_replace('\\', '/', realpath($destDir) ?: $destDir);
  if ($normDest === $normFull || strpos($normDest . '/', $normFull . '/') === 0) return false;
  $name = basename($fullDir);
  $target = $destDir . '/' . $name;
  $i = 0;
  while (file_exists($target) && $i < 10000) {
    $i++;
    $target = $destDir . '/' . preg_replace('#(.*?)(?:_(\d+))?$#', '$1_' . $i, $name);
  }
  return @rename($fullDir, $target);
}

// Recursively delete a directory (safe within root)
function img_delete_dir(string $relDir, bool $recursive = false): bool {
  [$fullDir, $relDir, $root] = img_resolve($relDir);
  if (!is_dir($fullDir)) return false;
  if (!$recursive) {
    // remove only if empty
    return @rmdir($fullDir);
  }
  $root = str_replace('\\', '/', $root);
  $fullDir = str_replace('\\', '/', $fullDir);
  if (strpos($fullDir, $root) !== 0) return false;

  $ok = true;
  $it = scandir($fullDir) ?: [];
  foreach ($it as $entry) {
    if ($entry === '.' || $entry === '..') continue;
    $p = $fullDir . '/' . $entry;
    if (is_dir($p)) {
      $ok = img_delete_dir($relDir . '/' . $entry, true) && $ok;
    } else {
      $ok = @unlink($p) && $ok;
    }
  }
  return @rmdir($fullDir) && $ok;
}

// Create a new subdirectory under a parent relative path
function img_create_dir(string $relParent, string $name): bool {
  [$parentFull, $relParent] = img_resolve($relParent);
  if (!img_ensure_dir($parentFull)) return false;
  // Sanitize single-segment folder name
  $seg = trim(str_replace(['\\','/'], '-', $name));
  $seg = preg_replace('#[^\p{L}\p{N}._ -]#u', '_', $seg);
  if ($seg === '' || $seg === '.' || $seg === '..') $seg = 'složka';
  $base = $seg;
  $i = 0;
  do {
    $candidate = $base . ($i ? "_$i" : '');
    $i++;
    $target = $parentFull . '/' . $candidate;
  } while (file_exists($target) && $i < 10000);
  return @mkdir($target, 0775, true);
}

// Generate a small WebP thumbnail next to the given WebP image (e.g., foo.webp -> foo.thumb.webp)
function img_generate_thumb(string $webpPath, int $size = 96): bool {
  $thumbPath = preg_replace('#\.webp$#i', '.thumb.webp', $webpPath);
  if (!$thumbPath || !is_file($webpPath)) return false;
  // Imagick path
  if (class_exists('Imagick')) {
    try {
      $im = new Imagick();
      if ($im->readImage($webpPath)) {
        $im->setImageFormat('webp');
        if (method_exists($im, 'setImageCompressionQuality')) $im->setImageCompressionQuality(75);
        $im->thumbnailImage($size, $size, true, true);
        $ok = $im->writeImage($thumbPath);
        $im->clear();
        $im->destroy();
        return (bool)$ok;
      }
    } catch (Throwable $e) {
      // fall through to GD
    }
  }
  // GD path
  if (function_exists('imagecreatefromwebp') && function_exists('imagewebp')) {
    $src = @imagecreatefromwebp($webpPath);
    if (!$src) return false;
    $w = imagesx($src); $h = imagesy($src);
    $ratio = min($size / max(1,$w), $size / max(1,$h), 1);
    $nw = max(1, (int)floor($w * $ratio));
    $nh = max(1, (int)floor($h * $ratio));
    $dst = @imagecreatetruecolor($nw, $nh);
    if (!$dst) { @imagedestroy($src); return false; }
    // preserve alpha
    @imagealphablending($dst, false);
    @imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    @imagefilledrectangle($dst, 0, 0, $nw, $nh, $transparent);
    @imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $ok = @imagewebp($dst, $thumbPath, 75);
    @imagedestroy($dst);
    @imagedestroy($src);
    return (bool)$ok;
  }
  return false;
}
