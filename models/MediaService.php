<?php
require_once BASE_PATH . '/models/Media.php';
require_once BASE_PATH . '/models/PageMedia.php';

class MediaService {
    const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    private $media;
    private $pageMedia;
    private $addedBytes = 0; // estimated bytes added by this request (usage is cached per request)

    public function __construct($media = null, $pageMedia = null) {
        $this->media = $media ?: new Media();
        $this->pageMedia = $pageMedia ?: new PageMedia();
    }

    public function thumbUrlFor($filename) {
        if (empty($filename)) return UPLOAD_URL . $filename;
        if (function_exists('findExistingDerivedSources')) {
            $srcs = findExistingDerivedSources($filename, [480]);
            if (!empty($srcs['fallback'])) {
                if (preg_match('/^(\S+)/', $srcs['fallback'], $m)) {
                    $derived = $m[1];
                    if (defined('BASE_URL') && BASE_URL !== '' && strpos($derived, '://') === false && $derived[0] === '/') {
                        return rtrim(BASE_URL, '/') . $derived;
                    }
                    return $derived;
                }
            }
        }
        return UPLOAD_URL . $filename;
    }

    public function validateFile($file) {
        if (!in_array($file['type'] ?? '', self::ALLOWED_TYPES)) {
            return 'Invalid file type';
        }
        if (($file['size'] ?? 0) > MAX_UPLOAD_SIZE) {
            return 'File too large';
        }
        return null;
    }

    /**
     * UPLOAD_QUOTA_MB in .env (unset/0 = no limit): refuse a new image when the uploads folder (originals + derived
     * copies) would pass it. Protects a small hosting plan from filling up — a full disk breaks MySQL and logging too.
     * @return string|null error message, or null when the upload fits
     */
    public function quotaError($incomingBytes) {
        $quotaMb = (float)(getenv('UPLOAD_QUOTA_MB') ?: 0);
        if ($quotaMb <= 0) return null;
        if (!class_exists('ImageVariants', false)) require_once BASE_PATH . '/services/ImageVariants.php';
        // a new image also creates derived copies: roughly +60% of the original in lean mode, +150% in full mode
        $est = (int)($incomingBytes * (ImageVariants::leanMode() ? 1.6 : 2.5));
        $used = ImageVariants::uploadsUsageBytes() + $this->addedBytes;
        if ($used + $est > $quotaMb * 1048576) {
            return 'Недостаточно места для фото: занято ' . round($used / 1048576, 1) . ' из ' . $quotaMb
                . ' МБ (UPLOAD_QUOTA_MB). Удалите ненужные фото или запустите scripts/cleanup-media.php';
        }
        $this->addedBytes += $est;
        return null;
    }

    /**
     * Real image check on the uploaded bytes (the browser-supplied $file['type'] and file name are untrusted).
     * @return array{0:string,1:string}|null [mime, extension] for jpeg/png/gif/webp, else null
     */
    public function detectImage($tmpPath) {
        if (!is_string($tmpPath) || $tmpPath === '' || !is_file($tmpPath)) return null;
        $info = @getimagesize($tmpPath);
        $map = [
            IMAGETYPE_JPEG => ['image/jpeg', 'jpg'],
            IMAGETYPE_PNG  => ['image/png', 'png'],
            IMAGETYPE_GIF  => ['image/gif', 'gif'],
            IMAGETYPE_WEBP => ['image/webp', 'webp'],
        ];
        if (!$info || !isset($map[$info[2]])) return null;
        return $map[$info[2]];
    }

    public function buildFilename($originalName, $suffix = '', $ext = null) {
        // $ext (from detectImage) wins over the client-supplied name so a disguised file can't keep a .php extension
        $ext = $ext ?: strtolower(preg_replace('/[^a-zA-Z0-9]/', '', pathinfo($originalName, PATHINFO_EXTENSION)));
        return uniqid() . '_' . time() . $suffix . '.' . $ext;
    }

    public function ensureUploadDir() {
        if (!is_dir(UPLOAD_PATH)) {
            mkdir(UPLOAD_PATH, 0755, true);
        }
    }

    public function uploadSingle($file, $pageId = null, $section = 'content', $position = 0) {
        if (!in_array($file['type'] ?? '', self::ALLOWED_TYPES)) {
            error_log('Media upload: invalid type ' . ($file['type'] ?? ''));
            return ['success' => false, 'error' => 'Invalid file type'];
        }
        if (($file['size'] ?? 0) > MAX_UPLOAD_SIZE) {
            error_log('Media upload: file too large size=' . ($file['size'] ?? 0));
            return ['success' => false, 'error' => 'File too large'];
        }
        $detected = $this->detectImage($file['tmp_name'] ?? '');
        if ($detected === null) {
            error_log('Media upload: content is not a jpeg/png/gif/webp image, name=' . ($file['name'] ?? ''));
            return ['success' => false, 'error' => 'Invalid file type'];
        }
        $file['type'] = $detected[0];
        if (($quota = $this->quotaError($file['size'] ?? 0)) !== null) {
            return ['success' => false, 'error' => $quota];
        }
        $filename = $this->buildFilename($file['name'], '', $detected[1]);
        $filepath = UPLOAD_PATH . $filename;
        $this->ensureUploadDir();
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            error_log('Media upload: move_uploaded_file failed to ' . $filepath);
            return ['success' => false, 'error' => 'Failed to save file'];
        }
        $mediaId = $this->media->create([
            'filename' => $filename,
            'original_name' => $file['name'],
            'file_size' => $file['size'],
            'mime_type' => $file['type']
        ]);
        generateResponsiveImageVariants($filename, getResponsiveImageWidths());
        if (!empty($pageId)) {
            $this->pageMedia->attachMedia($pageId, $mediaId, [
                'section' => $section,
                'position' => $position
            ]);
        }
        return ['success' => true, 'media_id' => $mediaId, 'filename' => $filename];
    }

    /**
     * Crop / resize / convert an image file with GD (used for stock photos, where the browser only has a thumbnail).
     * $opts: 'crop' => [x, y, w, h] as fractions (0..1) of the source image, or null;
     *        'max_width' => int px (0 = keep size, never upscales); 'format' => '' (keep) | 'jpeg' | 'webp'.
     * @return array{0:string,1:string}|null [path of a NEW temp file (caller deletes it), mime]; null = nothing to do or failed
     */
    public function transformFile($path, array $opts) {
        $crop = $opts['crop'] ?? null;
        $maxW = max(0, (int)($opts['max_width'] ?? 0));
        $fmt = (string)($opts['format'] ?? '');
        if (!$crop && !$maxW && $fmt === '') return null;
        if (!function_exists('imagecreatefromstring')) return null;
        $info = @getimagesize($path);
        $raw = @file_get_contents($path);
        $img = $raw !== false ? @imagecreatefromstring($raw) : false;
        unset($raw);
        if (!$info || !$img) return null;

        $sw = imagesx($img);
        $sh = imagesy($img);
        if (is_array($crop) && count($crop) === 4) {
            [$cx, $cy, $cw, $ch] = array_map('floatval', $crop);
            if ($cx >= 0 && $cy >= 0 && $cw > 0.01 && $ch > 0.01 && $cx + $cw <= 1.001 && $cy + $ch <= 1.001) {
                $x = (int)round($cx * $sw);
                $y = (int)round($cy * $sh);
                $w = max(1, min($sw - $x, (int)round($cw * $sw)));
                $h = max(1, min($sh - $y, (int)round($ch * $sh)));
                $cropped = @imagecrop($img, ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h]);
                if ($cropped) {
                    imagedestroy($img);
                    $img = $cropped;
                    $sw = $w;
                    $sh = $h;
                }
            }
        }
        if ($maxW > 0 && $sw > $maxW) {
            $scaled = @imagescale($img, $maxW, (int)max(1, round($sh * $maxW / $sw)), IMG_BICUBIC);
            if ($scaled) {
                imagedestroy($img);
                $img = $scaled;
                $sw = imagesx($img);
                $sh = imagesy($img);
            }
        }

        $type = $info[2];
        $out = $fmt === 'webp' ? 'webp' : ($fmt === 'jpeg' ? 'jpeg'
            : ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF ? 'png' : ($type === IMAGETYPE_WEBP ? 'webp' : 'jpeg')));
        if ($out === 'webp' && !function_exists('imagewebp')) $out = 'jpeg';

        $tmp = tempnam(sys_get_temp_dir(), 'img');
        $ok = false;
        if ($out === 'jpeg') {
            $flat = imagecreatetruecolor($sw, $sh);          // flatten any transparency onto white
            imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
            imagecopy($flat, $img, 0, 0, 0, 0, $sw, $sh);
            imagedestroy($img);
            $img = $flat;
            $ok = imagejpeg($img, $tmp, 88);
            $mime = 'image/jpeg';
        } elseif ($out === 'webp') {
            if (function_exists('imagepalettetotruecolor') && !imageistruecolor($img)) imagepalettetotruecolor($img);
            $ok = imagewebp($img, $tmp, 85);
            $mime = 'image/webp';
        } else {
            imagesavealpha($img, true);
            $ok = imagepng($img, $tmp, 6);
            $mime = 'image/png';
        }
        imagedestroy($img);
        if (!$ok || (int)@filesize($tmp) <= 0) {
            @unlink($tmp);
            return null;
        }
        return [$tmp, $mime];
    }

    /**
     * Store a file that already sits on the server (e.g. a downloaded stock photo) as a media item.
     * Not for $_FILES uploads (those use uploadSingle). Verifies the real image bytes and the size limit.
     */
    public function storeLocalFile($tmpPath, $originalName) {
        $detected = $this->detectImage($tmpPath);
        if ($detected === null) return ['success' => false, 'error' => 'Invalid file type'];
        $size = (int)@filesize($tmpPath);
        if ($size <= 0 || $size > MAX_UPLOAD_SIZE) return ['success' => false, 'error' => 'File too large'];
        if (($quota = $this->quotaError($size)) !== null) return ['success' => false, 'error' => $quota];
        $filename = $this->buildFilename($originalName, '', $detected[1]);
        $this->ensureUploadDir();
        if (!@copy($tmpPath, UPLOAD_PATH . $filename)) {
            error_log('Media storeLocalFile: copy failed to ' . UPLOAD_PATH . $filename);
            return ['success' => false, 'error' => 'Failed to save file'];
        }
        $mediaId = $this->media->create([
            'filename' => $filename,
            'original_name' => $originalName,
            'file_size' => $size,
            'mime_type' => $detected[0],
        ]);
        generateResponsiveImageVariants($filename, getResponsiveImageWidths());
        return ['success' => true, 'media_id' => $mediaId, 'filename' => $filename];
    }

    public function uploadBulk($files, $pageId = null, $section = 'content') {
        $uploaded = 0;
        $failed = 0;
        $errors = [];
        foreach ($files['tmp_name'] as $key => $tmpName) {
            if ($files['error'][$key] !== UPLOAD_ERR_OK) {
                $failed++;
                $errors[] = $files['name'][$key] . ': Upload error';
                error_log('Media bulk upload: upload error code=' . $files['error'][$key]);
                continue;
            }
            $file = [
                'name' => $files['name'][$key],
                'type' => $files['type'][$key],
                'size' => $files['size'][$key]
            ];
            $error = $this->validateFile($file);
            if ($error !== null) {
                $failed++;
                $errors[] = $file['name'] . ': Invalid file type or size';
                error_log('Media bulk upload: invalid type/size name=' . $file['name'] . ' type=' . ($file['type'] ?? '') . ' size=' . ($file['size'] ?? 0));
                continue;
            }
            $detected = $this->detectImage($tmpName);
            if ($detected === null) {
                $failed++;
                $errors[] = $file['name'] . ': Not a valid image';
                error_log('Media bulk upload: content is not a jpeg/png/gif/webp image, name=' . $file['name']);
                continue;
            }
            $file['type'] = $detected[0];
            if (($quota = $this->quotaError($file['size'] ?? 0)) !== null) {
                $failed++;
                $errors[] = $file['name'] . ': ' . $quota;
                continue;
            }
            $filename = $this->buildFilename($file['name'], '_' . $key, $detected[1]);
            $filepath = UPLOAD_PATH . $filename;
            $this->ensureUploadDir();
            if (move_uploaded_file($tmpName, $filepath)) {
                $mediaId = $this->media->create([
                    'filename' => $filename,
                    'original_name' => $file['name'],
                    'file_size' => $file['size'],
                    'mime_type' => $file['type']
                ]);
                generateResponsiveImageVariants($filename, getResponsiveImageWidths());
                if ($pageId) {
                    $this->pageMedia->attachMedia($pageId, $mediaId, [
                        'section' => $section,
                        'position' => $uploaded
                    ]);
                }
                $uploaded++;
            } else {
                $failed++;
                $errors[] = $file['name'] . ': Failed to save file';
                error_log('Media bulk upload: move_uploaded_file failed to ' . $filepath);
            }
        }
        return ['uploaded' => $uploaded, 'failed' => $failed, 'errors' => $errors];
    }

    public function attach($pageId, $mediaId, $data = []) {
        $this->pageMedia->attachMedia($pageId, $mediaId, $data);
    }

    public function detach($pageId, $mediaId, $section = null) {
        $this->pageMedia->detachMedia($pageId, $mediaId, $section);
    }

    public function bulkAttach($mediaIds, $pageId, $section = 'content') {
        foreach ($mediaIds as $mediaId) {
            $this->pageMedia->attachMedia($pageId, $mediaId, ['section' => $section]);
        }
        return count($mediaIds);
    }

    public function bulkDelete($mediaIds) {
        foreach ($mediaIds as $mediaId) {
            $this->media->delete($mediaId);
        }
        return count($mediaIds);
    }

    public function regenerate() {
        return regenerateImageVariants();
    }
}
