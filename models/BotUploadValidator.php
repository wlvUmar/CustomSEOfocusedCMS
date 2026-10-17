<?php

class BotUploadValidator {
    const MAX_PHOTOS = 4;

    private static $allowedMimes = array('image/jpeg', 'image/png', 'image/webp', 'image/gif');
    private static $allowedExts = array('jpg', 'jpeg', 'png', 'webp', 'gif');
    private static $extToMime = array(
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
    );

    public function normalizeUploadedFiles($files) {
        if (isset($files['images']) && isset($files['images']['name'])) {
            return $this->flattenFileArray($files['images']);
        }
        if (isset($files['image']) && isset($files['image']['name'])) {
            return $this->flattenFileArray($files['image']);
        }
        return array();
    }

    public function flattenFileArray($fileSpec) {
        if (!is_array($fileSpec['name'])) {
            return array($fileSpec);
        }
        $result = array();
        $count = count($fileSpec['name']);
        for ($i = 0; $i < $count; $i++) {
            $result[] = array(
                'name' => $fileSpec['name'][$i],
                'type' => $fileSpec['type'][$i],
                'tmp_name' => $fileSpec['tmp_name'][$i],
                'error' => $fileSpec['error'][$i],
                'size' => $fileSpec['size'][$i],
            );
        }
        return $result;
    }

    public function checkCount($files) {
        if (count($files) > self::MAX_PHOTOS) {
            return array('message' => 'Too many images uploaded', 'code' => 400);
        }
        return null;
    }

    public function extensionFor($filename) {
        return strtolower(pathinfo((string)$filename, PATHINFO_EXTENSION));
    }

    public function realMimeFor($tmpPath) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = $finfo ? finfo_file($finfo, $tmpPath) : null;
        if ($finfo) {
            finfo_close($finfo);
        }
        if ($realMime === 'image/jpg') {
            $realMime = 'image/jpeg';
        }
        return $realMime;
    }

    public function validateFile($file) {
        if (!isset($file['tmp_name']) || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return array('message' => 'Invalid image upload', 'code' => 400);
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return array('message' => 'Invalid image upload', 'code' => 400);
        }
        if ($file['size'] > MAX_UPLOAD_SIZE) {
            return array('message' => 'File too large', 'code' => 400);
        }
        $ext = $this->extensionFor(isset($file['name']) ? $file['name'] : '');
        if ($ext === '' || !in_array($ext, self::$allowedExts, true)) {
            return array('message' => 'Invalid image type', 'code' => 400);
        }
        if (!function_exists('finfo_open')) {
            return array('message' => 'Server upload validation unavailable', 'code' => 500);
        }
        $normalizedMime = $this->realMimeFor($file['tmp_name']);
        if (!in_array($normalizedMime, self::$allowedMimes, true)) {
            return array('message' => 'Invalid image type', 'code' => 400);
        }
        $expected = isset(self::$extToMime[$ext]) ? self::$extToMime[$ext] : null;
        if ($expected !== null && $expected !== $normalizedMime) {
            return array('message' => 'Invalid image type', 'code' => 400);
        }
        return null;
    }
}
