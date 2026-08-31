<?php
class Router {
    private array $routes = [];
    private $notFound;

    public function get(string $pattern, callable $callback) {
        $this->routes['GET'][$pattern] = $callback;
    }

    public function post(string $pattern, callable $callback) {
        $this->routes['POST'][$pattern] = $callback;
    }


    public function notFound(callable $callback) {
        $this->notFound = $callback;
    }

    public function error(int $code = 500) {
        http_response_code($code);
        $_GET['code'] = $code;
        require BASE_PATH . '/views/error.php';
        exit;
    }

    public function dispatch() {
        try {
            $method = $_SERVER['REQUEST_METHOD'];
            $isHead = ($method === 'HEAD');
            if ($isHead) {
                $method = 'GET';
            }
            // Preserve original URI when ErrorDocument triggers (REDIRECT_URL) — remaining-bugs #12
            $rawUri = $_SERVER['REDIRECT_URL'] ?? $_SERVER['REQUEST_URI'];
            // If ErrorDocument routed to /error.php?code=404, prefer REDIRECT_URI if available
            if (isset($_SERVER['REDIRECT_URI'])) $rawUri = $_SERVER['REDIRECT_URI'];
            elseif (isset($_SERVER['REQUEST_URI']) && str_starts_with($_SERVER['REQUEST_URI'], '/error.php')) {
                $rawUri = $_SERVER['REDIRECT_URL'] ?? $rawUri;
            }
            $uri = parse_url($rawUri, PHP_URL_PATH);
            $base = dirname($_SERVER['SCRIPT_NAME']);
            if ($base !== '/' && strpos($uri, $base) === 0) {
                $uri = substr($uri, strlen($base));
            }
            $uri = '/' . trim($uri, '/');
            // Sort routes by specificity before any matching (remaining-bugs #7)
            foreach ($this->routes as $m => $_) {
                uksort($this->routes[$m], fn($a, $b) => (strpos($a, '{') !== false) <=> (strpos($b, '{') !== false));
            }
            if (!isset($this->routes[$method])) {
                // 405 vs 404 — exclude catch-all public pages from Allow (remaining-bugs #7)
                $allowed = [];
                $catchAll = ['/{slug}', '/{slug}/{lang}'];
                foreach ($this->routes as $m => $routes) {
                    foreach ($routes as $pattern => $cb) {
                        if (in_array($pattern, $catchAll, true)) continue;
                        if (preg_match($this->convertPatternToRegex($pattern), $uri)) {
                            $allowed[] = $m;
                            break;
                        }
                    }
                }
                if (!empty($allowed)) {
                    http_response_code(405);
                    header('Allow: ' . implode(', ', array_unique($allowed)));
                    return $this->error(405);
                }
                return $this->error(404);
            }

            foreach ($this->routes[$method] as $pattern => $callback) {
                $regex = $this->convertPatternToRegex($pattern);
                if (preg_match($regex, $uri, $matches)) {
                    $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                    if ($isHead) {
                        ob_start();
                        $result = call_user_func_array($callback, $params);
                        ob_end_clean();
                        // HEAD must not return body — send headers only
                        if (!headers_sent()) header('Content-Length: 0');
                        return $result;
                    }
                    return call_user_func_array($callback, $params);
                }
            }

            error_log("Router 404: $method $uri");
            $this->error(404);

        } catch (\Throwable $e) {
            error_log($e);
            $this->error(500);
        }
    }
    
    private function convertPatternToRegex(string $pattern): string {
        $pattern = '/' . ltrim($pattern, '/');
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $pattern);
        return '#^' . $pattern . '$#';
    }

    public function group(string $prefix, callable $callback) {
        $groupRouter = new self();
        $callback($groupRouter);

        $prefix = rtrim($prefix, '/'); // remove trailing slash
        foreach ($groupRouter->routes as $method => $routes) {
            foreach ($routes as $pattern => $handler) {
                $pattern = trim($pattern, '/'); // remove leading/trailing slash
                $pattern = $pattern === '' ? $prefix : $prefix . '/' . $pattern;
                $this->routes[$method][$pattern] = $handler;
            }
        }
    }
}
