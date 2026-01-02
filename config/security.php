<?php

define('IS_PRODUCTION', !in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', 'test.kuplyu-tashkent.uz']));

// --------------------
// Error / Exception / Shutdown Handlers
// --------------------
$logDir = BASE_PATH . '/logs';
if (!is_dir($logDir)) mkdir($logDir, 0775, true);
$customLogFile = $logDir . '/php_errors.log';

error_reporting(E_ALL);

set_error_handler(function ($severity, $message, $file, $line) use ($customLogFile) {
    $log = "[".date('Y-m-d H:i:s')."] [Error][$severity] $message in $file on line $line\n";
    file_put_contents($customLogFile, $log, FILE_APPEND);
    if (!IS_PRODUCTION) {
        echo "$message in $file on line $line\n";
    }
});

set_exception_handler(function ($e) use ($customLogFile) {
    $log = "[".date('Y-m-d H:i:s')."] [Exception] ".$e->getMessage().
           " in ".$e->getFile()." on line ".$e->getLine()."\n";
    file_put_contents($customLogFile, $log, FILE_APPEND);
    if (!IS_PRODUCTION) {
        echo $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
    }
    http_response_code(500);
});

register_shutdown_function(function () use ($customLogFile) {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        $log = "[".date('Y-m-d H:i:s')."] [Fatal] {$error['message']} in {$error['file']} on line {$error['line']}\n";
        file_put_contents($customLogFile, $log, FILE_APPEND);
        if (!IS_PRODUCTION) {
            echo "{$error['message']} in {$error['file']} on line {$error['line']}";
        }
        http_response_code(500);
    }
});

// --------------------
// Display / Logging Settings
// --------------------
if (IS_PRODUCTION) {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', $customLogFile);
} else {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    ini_set('log_errors', 0);
}

// --------------------
// Session settings
// --------------------
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', IS_PRODUCTION ? 1 : 0);
ini_set('session.cookie_samesite', 'Lax');

// --------------------
// Security headers
// --------------------
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
if (IS_PRODUCTION) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
$csp = [
    "default-src 'self'",
    "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://unpkg.com",
    "style-src 'self' 'unsafe-inline'",
    "img-src 'self' data: https:",
    "font-src 'self' data:",
    "connect-src 'self' https://unpkg.com",
    "frame-ancestors 'self'"
];
header('Content-Security-Policy: ' . implode('; ', $csp));

// --------------------
// Security functions, RateLimiter, CSRF, Upload, Password, Logs
// --------------------
function generateCSRFToken() { /* ... */ }
function validateCSRFToken($token) { /* ... */ }
function csrfField() { /* ... */ }

class RateLimiter { /* ... */ }

function validateUpload($file) { /* ... */ }

function hashPassword($password) { /* ... */ }

function securityLog($message, $level = 'INFO') { /* ... */ }
