<?php
// path: ./config/security.php
// PRODUCTION SECURITY CONFIGURATION

// ========================================
// ENVIRONMENT DETECTION
// ========================================
define('IS_PRODUCTION', !in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', 'test.kuplyu-tashkent.uz']));

// ========================================
// ERROR REPORTING
// ========================================
if (IS_PRODUCTION) {
    // Production: Hide errors from users
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL);
    
    // Log to file instead
    ini_set('log_errors', 1);
    ini_set('error_log', BASE_PATH . '/logs/php_errors.log');
} else {
    // Development: Show all errors
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// ========================================
// SESSION SECURITY
// ========================================
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', IS_PRODUCTION ? 1 : 0); // HTTPS only in production
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.gc_maxlifetime', 3600); // 1 hour

// Regenerate session ID periodically
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 1800) { // 30 minutes
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// ========================================
// CSRF PROTECTION HELPER
// ========================================
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

// ========================================
// RATE LIMITING
// ========================================
class RateLimiter {
    private $max_attempts = 5;
    private $time_window = 300; // 5 minutes
    
    public function check($identifier, $action = 'default') {
        $key = "ratelimit_{$action}_{$identifier}";
        $attempts = $_SESSION[$key] ?? ['count' => 0, 'timestamp' => time()];
        
        // Reset if time window expired
        if (time() - $attempts['timestamp'] > $this->time_window) {
            $attempts = ['count' => 0, 'timestamp' => time()];
        }
        
        $attempts['count']++;
        $_SESSION[$key] = $attempts;
        
        if ($attempts['count'] > $this->max_attempts) {
            http_response_code(429);
            die('Too many attempts. Please try again later.');
        }
        
        return true;
    }
    
    public function reset($identifier, $action = 'default') {
        $key = "ratelimit_{$action}_{$identifier}";
        unset($_SESSION[$key]);
    }
}

// ========================================
// SQL INJECTION PREVENTION
// ========================================
// Already handled by PDO with prepared statements
// Ensure all database queries use prepared statements!

// ========================================
// XSS PREVENTION
// ========================================
// Already handled by e() function (htmlspecialchars)
// Always use e() when outputting user data!

// ========================================
// SECURITY HEADERS
// ========================================
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

if (IS_PRODUCTION) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// Content Security Policy (adjust as needed)
$csp = [
    "default-src 'self'",
    "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://unpkg.com",
    "style-src 'self' 'unsafe-inline'",
    "img-src 'self' data: https:",
    "font-src 'self' data:",
    "connect-src 'self'",
    "frame-ancestors 'self'"
];
header('Content-Security-Policy: ' . implode('; ', $csp));

// ========================================
// FILE UPLOAD SECURITY
// ========================================
function validateUpload($file) {
    // Check file was actually uploaded
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'message' => 'Invalid file upload'];
    }
    
    // Check file size
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['success' => false, 'message' => 'File too large'];
    }
    
    // Validate MIME type (don't trust user input)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mime, $allowed_mimes)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }
    
    // Validate file extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowed_exts)) {
        return ['success' => false, 'message' => 'Invalid file extension'];
    }
    
    // Additional image validation
    $image_info = getimagesize($file['tmp_name']);
    if ($image_info === false) {
        return ['success' => false, 'message' => 'Not a valid image'];
    }
    
    return ['success' => true];
}

// ========================================
// PASSWORD HASHING (for user creation)
// ========================================
// IMPORTANT: Use this when creating admin users
// Never store plain text passwords!

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Example user creation (run once via deploy.php):
// INSERT INTO users (username, email, password) 
// VALUES ('admin', 'admin@example.com', '<?php echo hashPassword("your_secure_password"); ?>');

// ========================================
// DATABASE CREDENTIALS SECURITY
// ========================================
// CRITICAL: Move database credentials to environment variables
// Create a .env file (NOT tracked in git):
/*
DB_HOST=localhost
DB_NAME=kuplyuta_db
DB_USER=kuplyuta_admin
DB_PASS=your_secure_password
*/

// Then use:
// define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
// define('DB_NAME', getenv('DB_NAME') ?: 'kuplyuta_db');
// define('DB_USER', getenv('DB_USER') ?: 'kuplyuta_admin');
// define('DB_PASS', getenv('DB_PASS') ?: 'fallback_password');

// ========================================
// LOGGING HELPER
// ========================================
function securityLog($message, $level = 'INFO') {
    $log_file = BASE_PATH . '/logs/security.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user = $_SESSION['username'] ?? 'guest';
    
    $log_message = "[{$timestamp}] [{$level}] [{$user}@{$ip}] {$message}\n";
    
    // Create log directory if it doesn't exist
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0750, true);
    }
    
    file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
}

// ========================================
// AUTO-LOGOUT ON INACTIVITY
// ========================================
$timeout = 1800; // 30 minutes
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . '/admin/login?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();