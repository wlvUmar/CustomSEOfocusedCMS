<?php
// Basic paths and app settings
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', 'https://test.kuplyu-tashkent.uz');

define('UPLOAD_PATH', BASE_PATH . '/public/uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB

define('SUPPORTED_LANGUAGES', ['ru', 'uz']);
define('DEFAULT_LANGUAGE', 'ru');

date_default_timezone_set('Asia/Tashkent');

// Load environment variables from .env file
if (file_exists(BASE_PATH . '/.env')) {
    $lines = file(BASE_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) continue;
        // Skip invalid lines
        if (strpos($line, '=') === false) continue;
        
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        
        // Remove quotes if present
        if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
            $value = $matches[2];
        }
        
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}
