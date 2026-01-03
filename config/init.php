<?php
// UPDATED: config/init.php
// Add this section right after the require statements

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/security.php';

// ========================================
// ERROR LOGGING SETUP
// ========================================
$logDir = BASE_PATH . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0750, true);
}

$errorLogFile = $logDir . '/php_errors.log';
$securityLogFile = $logDir . '/security.log';

// Ensure log files exist and are writable
if (!file_exists($errorLogFile)) {
    touch($errorLogFile);
    chmod($errorLogFile, 0640);
}

// Set PHP error logging
ini_set('log_errors', 1);
ini_set('error_log', $errorLogFile);
ini_set('display_errors', IS_PRODUCTION ? 0 : 1);

// Custom error handler for better logging
set_error_handler(function($errno, $errstr, $errfile, $errline) use ($errorLogFile) {
    $errorTypes = [
        E_ERROR => 'ERROR',
        E_WARNING => 'WARNING',
        E_NOTICE => 'NOTICE',
        E_USER_ERROR => 'USER_ERROR',
        E_USER_WARNING => 'USER_WARNING',
        E_USER_NOTICE => 'USER_NOTICE',
        E_STRICT => 'STRICT',
        E_DEPRECATED => 'DEPRECATED'
    ];
    
    $type = $errorTypes[$errno] ?? 'UNKNOWN';
    $timestamp = date('Y-m-d H:i:s');
    $message = "[$timestamp] [$type] $errstr in $errfile on line $errline\n";
    
    error_log($message, 3, $errorLogFile);
    
    // Don't execute PHP internal error handler
    return false;
});

// Exception handler
set_exception_handler(function($exception) use ($errorLogFile) {
    $timestamp = date('Y-m-d H:i:s');
    $message = sprintf(
        "[%s] [EXCEPTION] %s: %s in %s:%d\nStack trace:\n%s\n",
        $timestamp,
        get_class($exception),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTraceAsString()
    );
    
    error_log($message, 3, $errorLogFile);
    
    if (!IS_PRODUCTION) {
        echo "<pre>$message</pre>";
    } else {
        http_response_code(500);
        require BASE_PATH . '/views/error.php';
    }
});

// ========================================
// SESSION SETUP (existing code continues...)
// ========================================
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Session timeout (1 hour)
$timeout = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . '/admin/login?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();