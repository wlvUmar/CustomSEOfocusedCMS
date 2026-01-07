<?php
// create_indexnow_key.php - run during deploy to ensure the IndexNow verification file exists in the deployed webroot
// Usage: php /home/kuplyuta/config/create_indexnow_key.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../core/Database.php';

try {
    $db = Database::getInstance();
    $row = $db->fetchOne("SELECT api_key FROM search_engine_config WHERE engine = 'bing' LIMIT 1");
    if (empty($row['api_key'])) {
        echo "No api_key found for bing\n";
        exit(0);
    }

    $key = $row['api_key'];

    // Prefer environment-provided PUBLICPATH (set in .cpanel.yml), then common names public_html, then public
    $publicDir = getenv('PUBLICPATH') ?: (getenv('DEPLOYPATH') . '/public' ?: null);

    if (!$publicDir) {
        $candidates = [
            __DIR__ . '/../public_html',
            __DIR__ . '/../public',
            __DIR__ . '/../../public_html',
            __DIR__ . '/../../public'
        ];
        foreach ($candidates as $cand) {
            $real = realpath($cand) ?: $cand;
            if (is_dir($real)) { $publicDir = $real; break; }
        }
    }

    // Final fallback: sibling public_html or public under deploy path
    if (!$publicDir) {
        $publicDir = __DIR__ . '/../public_html';
    }

    // Ensure directory exists (attempt create only under DEPLOYPATH, not system root)
    if (!is_dir($publicDir)) {
        if (!mkdir($publicDir, 0755, true) && !is_dir($publicDir)) {
            echo "Failed to create webroot dir: $publicDir\n";
            exit(1);
        }
    }

    $path = rtrim($publicDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $key . '.txt';

    if (@file_put_contents($path, $key) === false) {
        echo "Failed to write key file: $path\n";
        exit(1);
    }

    @chmod($path, 0644);
    echo "OK: Created $path\n";
    exit(0);
} catch (Exception $e) {
    echo "ERR: " . $e->getMessage() . "\n";
    exit(1);
}
