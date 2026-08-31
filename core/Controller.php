<?php
class Controller {
    protected $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    protected function view($file, $data = []) {
        $data['pageName'] = $data['pageName'] ?? null;
        // Prevent variable-variable overwrite of critical locals (project-02#1)
        $blocked = ['db', 'router', 'this', '_SESSION', '_GET', '_POST', '_COOKIE', 'GLOBALS'];
        foreach ($data as $key => $value) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) continue;
            if (in_array($key, $blocked, true)) continue;
            $$key = $value;
        }
        require BASE_PATH . '/views/' . $file . '.php';
    }

    protected function redirect($url) {
        // Sanitize Location to prevent CRLF and open-redirect via //evil.com (project-02#2)
        $url = trim((string)$url);
        // Block protocol-relative and absolute URLs
        if (preg_match('#^\s*(//|https?:)#i', $url)) {
            $url = '/';
        }
        // Strip CRLF
        $url = str_replace(["\r", "\n"], '', $url);
        // Ensure leading slash
        if ($url === '' || $url[0] !== '/') {
            $url = '/' . ltrim($url, '/');
        }
        header("Location: " . BASE_URL . $url);
        exit;
    }

    protected function json($data, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function requireAuth() {
        if (!isset($_SESSION['user_id'])) {
            $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')
                || strpos($accept, 'application/json') !== false;
            if ($isAjax) {
                $this->json(['success' => false, 'message' => 'Authentication required'], 401);
            }
            // Sanitize redirect_after_login to prevent open-redirect via //evil.com (project-02#2)
            $uri = $_SERVER['REQUEST_URI'] ?? '/admin/dashboard';
            $uri = str_replace(["\r", "\n"], '', $uri);
            // Only allow same-origin path; reject protocol-relative or absolute URLs
            if (preg_match('#^\s*(//|https?:)#i', $uri)) {
                $uri = '/admin/dashboard';
            } else {
                // Strip host if present, keep path+query only
                $parsed = parse_url($uri);
                $uri = ($parsed['path'] ?? '/admin/dashboard');
                if (!empty($parsed['query'])) $uri .= '?' . $parsed['query'];
            }
            // Ensure leading slash and no // prefix
            $uri = '/' . ltrim($uri, '/');
            $uri = preg_replace('#^//+#', '/', $uri);
            $_SESSION['redirect_after_login'] = $uri;
            $this->redirect('/admin/login');
        }
    }

    protected function requireCsrf(?string $action = null): void {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!validateCSRFToken($token, $action)) {
            $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && str_contains($accept, 'application/json'));
            if ($isAjax || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                $this->json(['success' => false, 'message' => 'CSRF token validation failed'], 403);
            }
            http_response_code(403);
            $_SESSION['error'] = 'CSRF token validation failed. Please refresh and try again.';
            $this->redirect('/admin/dashboard');
        }
    }
}
