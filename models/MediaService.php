<?php
require_once BASE_PATH . '/models/Media.php';
require_once BASE_PATH . '/models/PageMedia.php';

class MediaService {
    const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    private $media;
    private $pageMedia;

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
     * Store a file that already sits on the server (e.g. a downloaded stock photo) as a media item.
     * Not for $_FILES uploads (those use uploadSingle). Verifies the real image bytes and the size limit.
     */
    public function storeLocalFile($tmpPath, $originalName) {
        $detected = $this->detectImage($tmpPath);
        if ($detected === null) return ['success' => false, 'error' => 'Invalid file type'];
        $size = (int)@filesize($tmpPath);
        if ($size <= 0 || $size > MAX_UPLOAD_SIZE) return ['success' => false, 'error' => 'File too large'];
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
