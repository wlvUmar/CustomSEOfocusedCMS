<?php

define('IS_PRODUCTION', !in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', 'test.kuplyu-tashkent.uz']));

// --------------------
// Security Headers
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
    "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com https://unpkg.com", 
    "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https:",
    "style-src-elem 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https:",
    "img-src 'self' data: blob: https:", 
    "font-src 'self' data: https://cdnjs.cloudflare.com",
    "connect-src 'self' https://unpkg.com https://cdnjs.cloudflare.com",
    "frame-ancestors 'self'",
    "worker-src 'self' blob:", 
    "child-src 'self' blob:"
];

// header('Content-Security-Policy: ' . implode('; ', $csp));


// --------------------
// CSRF
// --------------------
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

// --------------------
// RateLimiter
// --------------------
class RateLimiter {
    private $max_attempts = 5;
    private $time_window = 300; // 5 minutes

    public function check($identifier, $action = 'default') {
        $key = "ratelimit_{$action}_{$identifier}";
        $attempts = $_SESSION[$key] ?? ['count' => 0, 'timestamp' => time()];

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

// --------------------
// Upload validation
// --------------------
function validateUpload($file) {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'message' => 'Invalid file upload'];
    }
    
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['success' => false, 'message' => 'File too large'];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mime, $allowed_mimes)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowed_exts)) {
        return ['success' => false, 'message' => 'Invalid file extension'];
    }
    
    $image_info = getimagesize($file['tmp_name']);
    if ($image_info === false) {
        return ['success' => false, 'message' => 'Not a valid image'];
    }
    
    return ['success' => true];
}

// --------------------
// Password hash
// --------------------
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// --------------------
// Security log
// --------------------
function securityLog($message, $level = 'INFO') {
    $log_file = BASE_PATH . '/logs/security.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user = $_SESSION['username'] ?? 'guest';

    $log_message = "[{$timestamp}] [{$level}] [{$user}@{$ip}] {$message}\n";

    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) mkdir($log_dir, 0750, true);

    file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
}
