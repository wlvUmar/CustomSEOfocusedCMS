<?php
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', getenv('BASE_URL') ?: '');

$publicPath = getenv('PUBLIC_PATH');
if (!$publicPath) {
    $publicPath = is_dir(BASE_PATH . '/public_html') ? BASE_PATH . '/public_html' : BASE_PATH . '/public';
}
define('PUBLIC_PATH', $publicPath);

define('UPLOAD_PATH', PUBLIC_PATH . '/uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); 

define('SUPPORTED_LANGUAGES', ['ru', 'uz']);
define('DEFAULT_LANGUAGE', 'ru');

date_default_timezone_set('Asia/Tashkent');

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
