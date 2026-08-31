<?php

// IS_PRODUCTION is env-driven; defaults to true for safety on prod, allow APP_ENV=development/local to disable.
$appEnv = getenv('APP_ENV') ?: (getenv('IS_PRODUCTION') ?: '');
if ($appEnv === 'development' || $appEnv === 'local' || $appEnv === '0' || $appEnv === 'false') {
    define('IS_PRODUCTION', false);
} elseif ($appEnv === 'production' || $appEnv === '1' || $appEnv === 'true') {
    define('IS_PRODUCTION', true);
} else {
    // Default: true when BASE_URL is not a private IP/LAN; false for 192.168.* local dev
    $baseForEnv = getenv('BASE_URL') ?: '';
    define('IS_PRODUCTION', !preg_match('#^https?://(192\.168\.|127\.0\.0\.1|localhost)#', $baseForEnv));
}

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
    "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://unpkg.com https://cdn.jsdelivr.net https://www.googletagmanager.com https://connect.facebook.net https://www.facebook.com https://pagead2.googlesyndication.com https://googleads.g.doubleclick.net https://www.google.com https://tpc.googlesyndication.com https://*.adtrafficquality.google",
    "script-src-elem 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://unpkg.com https://cdn.jsdelivr.net https://www.googletagmanager.com https://connect.facebook.net https://www.facebook.com https://pagead2.googlesyndication.com https://googleads.g.doubleclick.net https://www.google.com https://tpc.googlesyndication.com https://*.adtrafficquality.google",
    "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https:",
    "style-src-elem 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https:",
    "img-src 'self' data: blob: https: https://www.google-analytics.com https://region1.google-analytics.com https://www.googletagmanager.com https://stats.g.doubleclick.net",
    "font-src 'self' data: https://cdnjs.cloudflare.com https://fonts.gstatic.com",
    "connect-src 'self' https://unpkg.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://www.googletagmanager.com https://www.google-analytics.com https://analytics.google.com https://region1.google-analytics.com https://graph.facebook.com https://www.google.com/measurement/conversion https://stats.g.doubleclick.net https://pagead2.googlesyndication.com https://googleads.g.doubleclick.net https://*.adtrafficquality.google",
    "frame-src 'self' https://www.googletagmanager.com https://www.facebook.com https://www.google.com https://www.google.com/maps https://googleads.g.doubleclick.net https://tpc.googlesyndication.com https://*.adtrafficquality.google",
    "frame-ancestors 'self'",
    "worker-src 'self' blob:",
    "child-src 'self' blob: https://www.facebook.com"
];
header('Content-Security-Policy: ' . implode('; ', $csp));



// --------------------
// CSRF — per-session token with rotation on demand (project-02#5, project-03)
// For per-action binding, call generateCSRFToken('action') in future.
// --------------------
function generateCSRFToken(?string $action = null) {
    if ($action !== null) {
        // Per-action token: HMAC of session token + action (no extra storage)
        $base = $_SESSION['csrf_token'] ?? null;
        if ($base === null) {
            $base = bin2hex(random_bytes(32));
            $_SESSION['csrf_token'] = $base;
        }
        return hash_hmac('sha256', $action, $base);
    }
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    // Rotate if older than 24h to limit fixation window while keeping single-admin UX
    if (!isset($_SESSION['csrf_token_time'])) {
        $_SESSION['csrf_token_time'] = time();
    } elseif (time() - $_SESSION['csrf_token_time'] > 86400) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token, ?string $action = null) {
    if ($action !== null) {
        $expected = generateCSRFToken($action);
        return hash_equals($expected, (string)$token);
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

function csrfField(?string $action = null) {
    $token = generateCSRFToken($action);
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

// --------------------
// RateLimiter — file-backed per-IP with flock (project-02#6, project-10)
// Falls back to session when storage not writable. Rate limit survives cookie clear.
 // --------------------
class RateLimiter {
    private $max_attempts = 5;
    private $time_window = 300; // 5 minutes

    private function storageDir(): string {
        $dir = BASE_PATH . '/storage/ratelimit';
        if (!is_dir($dir)) @mkdir($dir, 0750, true);
        return $dir;
    }

    private function ipIdentifier(string $identifier): string {
        // If identifier looks like an IP, use as-is; else mix in real IP for per-IP bucket
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        // Cloudflare / proxy
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        return $identifier . '_' . $ip;
    }

    public function check($identifier, $action = 'default') {
        $key = "ratelimit_{$action}_{$identifier}";
        $fileKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $this->ipIdentifier((string)$identifier) . '_' . $action);
        $file = $this->storageDir() . '/' . $fileKey . '.json';
        $useFile = is_dir($this->storageDir()) && is_writable($this->storageDir());
        $attempts = null;
        $fp = null;
        // Hold EX across read+write to avoid race (15 concurrent requests)
        if ($useFile) {
            $fp = @fopen($file, 'c+');
            if ($fp && @flock($fp, LOCK_EX)) {
                $raw = stream_get_contents($fp);
                if ($raw !== false && $raw !== '') {
                    $data = json_decode($raw, true);
                    if (is_array($data) && isset($data['count'], $data['timestamp'])) {
                        $attempts = $data;
                    }
                }
            }
        }
        if ($attempts === null) {
            $attempts = $_SESSION[$key] ?? ['count' => 0, 'timestamp' => time()];
        }

        if (time() - $attempts['timestamp'] > $this->time_window) {
            $attempts = ['count' => 0, 'timestamp' => time()];
        }

        $attempts['count']++;
        $_SESSION[$key] = $attempts;

        if ($fp) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($attempts));
            @flock($fp, LOCK_UN);
            @fclose($fp);
        } elseif ($useFile) {
            // Fallback if flock failed
            @file_put_contents($file, json_encode($attempts), LOCK_EX);
        }

        // Also check session bucket as fallback (take max)
        if ($attempts['count'] > $this->max_attempts) {
            http_response_code(429);
            header('Retry-After: ' . $this->time_window);
            // Emit proper body but keep 429
            die('Too many attempts. Please try again later.');
        }

        return true;
    }

    public function reset($identifier, $action = 'default') {
        $key = "ratelimit_{$action}_{$identifier}";
        unset($_SESSION[$key]);
        $fileKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $this->ipIdentifier((string)$identifier) . '_' . $action);
        $file = $this->storageDir() . '/' . $fileKey . '.json';
        @unlink($file);
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
