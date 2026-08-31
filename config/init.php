<?php
// UPDATED: config/init.php
// Add this section right after the require statements

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/security.php';
require_once BASE_PATH . '/core/Autoloader.php';
Autoloader::register();


$logDir = BASE_PATH . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0750, true);
}

$errorLogFile = $logDir . '/php_errors.log';
$securityLogFile = $logDir . '/security.log';

if (!file_exists($errorLogFile)) {
    touch($errorLogFile);
    chmod($errorLogFile, 0640);
}

// Tracking audit log for debugging inflated stats
$trackingAuditLogFile = $logDir . '/tracking_audit.log';
if (!file_exists($trackingAuditLogFile)) {
    touch($trackingAuditLogFile);
    chmod($trackingAuditLogFile, 0640);
}
define('TRACKING_AUDIT_LOG', $trackingAuditLogFile);

// Audit logging is disabled by default; flip to true to re-enable. The full
// logTrackingAudit() implementation stays in core/helpers.php.
define('TRACKING_AUDIT_ENABLED', false);

ini_set('log_errors', 1);
ini_set('error_log', $errorLogFile);
ini_set('display_errors', IS_PRODUCTION ? 0 : 1);

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
    // Return true to prevent double-logging via PHP internal handler (project-13#5)
    return true;
});

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
        if (!headers_sent()) echo "<pre>" . htmlspecialchars($message) . "</pre>";
        return;
    }
    if (headers_sent()) {
        // Avoid fatal loop if headers already sent; just log and exit
        exit(1);
    }
    http_response_code(500);
    try {
        require BASE_PATH . '/views/error.php';
    } catch (Throwable $e) {
        error_log("Exception handler view failed: " . $e->getMessage(), 3, $errorLogFile);
        echo "Internal Server Error";
    }
});


// ---------------------------------------------------------------------------
// Session configuration: separate 14-day admin sessions, ephemeral visitor ones
// ---------------------------------------------------------------------------
// Anchor admin detection to /admin(/|$) to avoid /admin-malicious false positive (project-02#7)
$isAdminRequest = preg_match('#^/admin(/|$)#', $_SERVER['REQUEST_URI'] ?? '');
$adminLifetime = 14 * 24 * 60 * 60; // 1209600 seconds

// Helper: detect HTTPS behind proxy (Cloudflare, X-Forwarded-Proto) (project-02#8)
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    || (!empty($_SERVER['HTTP_CF_VISITOR']) && str_contains($_SERVER['HTTP_CF_VISITOR'], '"scheme":"https"'))
    || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');

// Switch session name/path per admin request — but avoid fixation by not switching mid-session
// If session already started with ADMINSESSID, keep it.
if ($isAdminRequest) {
    $adminSessionPath = BASE_PATH . '/storage/admin_sessions';
    if (!is_dir($adminSessionPath)) {
        @mkdir($adminSessionPath, 0750, true);
    }
    // Only switch if not already using admin session
    if (session_status() === PHP_SESSION_NONE) {
        session_save_path($adminSessionPath);
        session_name('ADMINSESSID');
    } elseif (session_name() !== 'ADMINSESSID') {
        // Already have a visitor session; do not switch name mid-request to avoid fixation
        // Keep current session but ensure next admin request will use ADMINSESSID
    } else {
        session_save_path($adminSessionPath);
    }
    ini_set('session.gc_maxlifetime', $adminLifetime);
    ini_set('session.gc_probability', 1);
    ini_set('session.gc_divisor', 100);
    session_set_cookie_params([
        'lifetime' => $adminLifetime,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
} else {
    session_set_cookie_params([
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

if (session_status() === PHP_SESSION_NONE) session_start();

// Track origin: whether this session was created as admin (for timeout correctness) (project-02#10)
if (!isset($_SESSION['is_admin_session']) && $isAdminRequest && !empty($_SESSION['user_id'])) {
    $_SESSION['is_admin_session'] = true;
} elseif (!isset($_SESSION['is_admin_session']) && !$isAdminRequest && empty($_SESSION['user_id'])) {
    $_SESSION['is_admin_session'] = false;
}

// Extend cookie lifetime on each request for admin only
if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['is_admin_session'])) {
    setcookie(session_name(), session_id(), time() + $adminLifetime, '/', '', $isHttps, true);
}

if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 86400) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Session timeout: use session origin, not current request's isAdmin (project-02#10)
if (!empty($_SESSION['user_id'])) {
    $timeout = !empty($_SESSION['is_admin_session']) ? $adminLifetime : 1440;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . '/admin/login?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}