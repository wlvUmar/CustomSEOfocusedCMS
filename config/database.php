<?php
if (file_exists(BASE_PATH . '/.env')) {
    $lines = file(BASE_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            putenv(trim($line));
        }
    }
}

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'kuplyuta_db');
define('DB_USER', getenv('DB_USER') ?: 'kuplyuta_admin');
define('DB_PASS', getenv('DB_PASS') ?: '=@iX?z~gukWg');
define('DB_CHARSET', 'utf8mb4');