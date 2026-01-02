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
