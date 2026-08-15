<?php
define('BASE_PATH', dirname(__DIR__));

// Load .env before defining constants that depend on environment variables.
// Previously BASE_URL was defined before loading .env, causing production URL leakage
// when BASE_URL was set only in the .env file.
if (file_exists(BASE_PATH . '/.env')) {
    $lines = file(BASE_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        
        if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
            $value = $matches[2];
        }
        
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

// Single source of truth for the site base URL (should be absolute in production).
// Example production value: https://kuplyu-tashkent.uz
define('BASE_URL', getenv('BASE_URL') ?: '');

$publicPath = getenv('PUBLIC_PATH');
if (!$publicPath) {
    $publicPath = is_dir(BASE_PATH . '/public_html') ? BASE_PATH . '/public_html' : BASE_PATH . '/public';
}
define('PUBLIC_PATH', $publicPath);

$uploadPath = getenv('UPLOAD_PATH');
if (!$uploadPath) {
    $uploadPath = PUBLIC_PATH . '/uploads/';
}
// Allow deployment-specific upload directories via .env.
define('UPLOAD_PATH', rtrim($uploadPath, '/\\') . '/');

$uploadUrl = getenv('UPLOAD_URL');
if (!$uploadUrl) {
    $uploadUrl = BASE_URL . '/uploads/';
}
define('UPLOAD_URL', rtrim($uploadUrl, '/\\') . '/');

define('MAX_UPLOAD_SIZE', (int)(getenv('MAX_UPLOAD_SIZE') ?: (5 * 1024 * 1024)));

define('SUPPORTED_LANGUAGES', ['ru', 'uz']);
define('DEFAULT_LANGUAGE', 'ru');
define('GTM_ID', 'GTM-PRK222HD');
define('TELEGRAM_BOT_URL', getenv('TELEGRAM_BOT_URL') ?: 'https://t.me/YOUR_BOT');

// OpenRouter API key for the AI page-editing assistant (set OPENROUTER_API_KEY in .env)
define('OPENROUTER_API_KEY', getenv('OPENROUTER_API_KEY') ?: '');

date_default_timezone_set('Asia/Tashkent');
