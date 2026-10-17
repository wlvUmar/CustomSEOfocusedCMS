<?php
// path: ./services/ImageVariants.php
// Responsive image variant helpers extracted from core/helpers.php.
// Filesystem/GD only - no DB, no HTML.

class ImageVariants {
    public static function getResponsiveImageWidths() {
        // 640 added to match 513px displayed LCP (was 768 → oversized by ~50% = 18.4 KiB waste)
        // 480 for small, 640 for mobile LCP, 768 still useful for tablet, 1024/1366 for desktop
        return [480, 640, 768, 1024, 1366];
    }

    public static function getUploadsDir() {
        if (defined('UPLOAD_PATH') && UPLOAD_PATH) {
            return rtrim(UPLOAD_PATH, '/\\');
        }
        if (defined('PUBLIC_PATH') && PUBLIC_PATH) {
            return rtrim(PUBLIC_PATH, '/\\') . '/uploads';
        }
        return __DIR__ . '/../public/uploads';
    }

    public static function getImageDimensions($filename) {
        $path = self::getUploadsDir() . '/' . $filename;
        if (!file_exists($path)) return null;
        $info = @getimagesize($path);
        if (!$info) return null;
        return ['width' => $info[0], 'height' => $info[1]];
    }

    public static function getPublicImageDimensions($url) {
        $url = trim((string)($url ?? ''));
        if ($url === '') return null;

        $path = '';
        if (strpos($url, '/') === 0) {
            $path = $url;
        } else {
            $parsed = @parse_url($url);
            if (!is_array($parsed) || empty($parsed['path'])) return null;
            $base = siteBaseUrl();
            $baseHost = parse_url($base, PHP_URL_HOST);
            $urlHost = $parsed['host'] ?? '';
            if ($urlHost && $baseHost && strcasecmp($urlHost, $baseHost) !== 0) {
                return null;
            }
            $path = $parsed['path'];
        }

        if ($path === '' || $path[0] !== '/') return null;
        if (!defined('PUBLIC_PATH')) return null;

        $fullPath = rtrim(PUBLIC_PATH, '/\\') . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (!file_exists($fullPath)) return null;
        $info = @getimagesize($fullPath);
        if (!$info) return null;
        return ['width' => $info[0], 'height' => $info[1]];
    }

    public static function buildResponsiveImageSources($filename, $widths) {
        $path = self::getUploadsDir() . '/' . $filename;
        if (!file_exists($path)) return null;
        $prebuilt = self::findExistingDerivedSources($filename, $widths);
        if ($prebuilt) {
            return $prebuilt;
        }
        if (!function_exists('getimagesize')) return null;
        $info = @getimagesize($path);
        if (!$info) return null;
        $originalWidth = (int)$info[0];
        $usableWidths = array_values(array_filter($widths, function($w) use ($originalWidth) {
            return $w > 0 && $w <= $originalWidth;
        }));
        if (empty($usableWidths)) return null;
        $fallbackSrcset = [];
        $webpSrcset = [];
        foreach ($usableWidths as $width) {
            $fallback = self::ensureDerivedImage($filename, $width, 'jpg');
            if ($fallback) {
                $fallbackSrcset[] = $fallback . ' ' . $width . 'w';
            }
            $webp = self::ensureDerivedImage($filename, $width, 'webp');
            if ($webp) {
                $webpSrcset[] = $webp . ' ' . $width . 'w';
            }
        }
        if (empty($fallbackSrcset) && empty($webpSrcset)) return null;
        return [
            'fallback' => implode(', ', $fallbackSrcset),
            'webp' => implode(', ', $webpSrcset)
        ];
    }

    public static function findExistingDerivedSources($filename, $widths) {
        $derivedDir = self::getUploadsDir() . '/derived';
        if (!is_dir($derivedDir)) return null;
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $fallbackSrcset = [];
        $webpSrcset = [];
        foreach ($widths as $width) {
            if ($width <= 0) continue;
            $fallbackPath = $derivedDir . '/' . $name . '_w' . $width . '.jpg';
            if (file_exists($fallbackPath)) {
                $fallbackSrcset[] = '/uploads/derived/' . $name . '_w' . $width . '.jpg ' . $width . 'w';
            }
            $webpPath = $derivedDir . '/' . $name . '_w' . $width . '.webp';
            if (file_exists($webpPath)) {
                $webpSrcset[] = '/uploads/derived/' . $name . '_w' . $width . '.webp ' . $width . 'w';
            }
        }
        if (empty($fallbackSrcset) && empty($webpSrcset)) {
            return null;
        }
        return [
            'fallback' => implode(', ', $fallbackSrcset),
            'webp' => implode(', ', $webpSrcset)
        ];
    }

    public static function ensureDerivedImage($filename, $targetWidth, $format) {
        $sourcePath = self::getUploadsDir() . '/' . $filename;
        if (!file_exists($sourcePath)) return null;
        if (!function_exists('getimagesize')) return null;
        if ($format === 'webp' && !function_exists('imagewebp')) return null;

        $info = @getimagesize($sourcePath);
        if (!$info) return null;
        $sourceWidth = (int)$info[0];
        $sourceHeight = (int)$info[1];
        if ($targetWidth <= 0 || $targetWidth >= $sourceWidth) return null;

        $derivedDir = self::getUploadsDir() . '/derived';
        if (!is_dir($derivedDir)) {
            @mkdir($derivedDir, 0755, true);
        }

        $name = pathinfo($filename, PATHINFO_FILENAME);
        $targetName = $name . '_w' . $targetWidth . '.' . $format;
        $targetPath = $derivedDir . '/' . $targetName;

        if (file_exists($targetPath) && filemtime($targetPath) >= filemtime($sourcePath)) {
            return '/uploads/derived/' . $targetName;
        }

        $type = $info[2];
        $sourceImage = null;
        if ($type === IMAGETYPE_JPEG) {
            $sourceImage = @imagecreatefromjpeg($sourcePath);
        } elseif ($type === IMAGETYPE_PNG) {
            $sourceImage = @imagecreatefrompng($sourcePath);
        } elseif ($type === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) {
            $sourceImage = @imagecreatefromwebp($sourcePath);
        }
        if (!$sourceImage) return null;

        $targetHeight = (int)round($sourceHeight * ($targetWidth / $sourceWidth));
        $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP) {
            imagealphablending($targetImage, false);
            imagesavealpha($targetImage, true);
            $transparent = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
            imagefilledrectangle($targetImage, 0, 0, $targetWidth, $targetHeight, $transparent);
        }

        imagecopyresampled($targetImage, $sourceImage, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

        $saved = false;
        if ($format === 'webp') {
            // 80 → 72 saves ~15% (~8 KiB) per hero LCP per Lighthouse; visual diff negligible on photos
            $saved = imagewebp($targetImage, $targetPath, 72);
        } else {
            // 82 → 75 for JPEG fallback
            $saved = imagejpeg($targetImage, $targetPath, 75);
        }

        imagedestroy($sourceImage);
        imagedestroy($targetImage);

        if (!$saved) return null;
        return '/uploads/derived/' . $targetName;
    }

    public static function generateResponsiveImageVariants($filename, $widths) {
        if (empty($filename)) return;
        if (empty($widths)) {
            $widths = self::getResponsiveImageWidths();
        }
        foreach ($widths as $width) {
            self::ensureDerivedImage($filename, (int)$width, 'jpg');
            self::ensureDerivedImage($filename, (int)$width, 'webp');
        }
    }

    public static function regenerateImageVariants($widths = null) {
        if (empty($widths)) {
            $widths = self::getResponsiveImageWidths();
        }

        $uploadsDir = self::getUploadsDir();
        if (!is_dir($uploadsDir)) {
            return [
                'success' => false,
                'message' => 'Uploads directory not found.'
            ];
        }

        $canProcess = function_exists('getimagesize') &&
            (function_exists('imagecreatefromjpeg') || function_exists('imagecreatefrompng') || function_exists('imagecreatefromwebp'));
        if (!$canProcess) {
            return [
                'success' => false,
                'message' => 'Image processing not available (PHP GD missing).'
            ];
        }

        $processed = 0;
        $skipped = 0;
        $errors = 0;
        $webpEnabled = function_exists('imagewebp');

        @set_time_limit(0);

        $iterator = new DirectoryIterator($uploadsDir);
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDot()) continue;
            if ($fileInfo->isDir()) {
                if ($fileInfo->getFilename() === 'derived') continue;
                continue;
            }

            $filename = $fileInfo->getFilename();
            if ($filename === '.htaccess') {
                $skipped++;
                continue;
            }

            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $skipped++;
                continue;
            }

            try {
                self::generateResponsiveImageVariants($filename, $widths);
                $processed++;
            } catch (Throwable $e) {
                $errors++;
                error_log('Variant generation failed for ' . $filename . ': ' . $e->getMessage());
            }
        }

        return [
            'success' => true,
            'processed' => $processed,
            'skipped' => $skipped,
            'errors' => $errors,
            'webp' => $webpEnabled
        ];
    }
}
