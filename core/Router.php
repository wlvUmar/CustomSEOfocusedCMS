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
            if ($method === 'HEAD') {
                $method = 'GET';
            }
            $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $base = dirname($_SERVER['SCRIPT_NAME']);
            
            if ($base !== '/' && strpos($uri, $base) === 0) {
                $uri = substr($uri, strlen($base));
            }

            $uri = '/' . trim($uri, '/');

            if (!isset($this->routes[$method])) {
                return $this->error(404);
            }

            uksort($this->routes[$method], fn($a, $b) => (strpos($a, '{') !== false) <=> (strpos($b, '{') !== false));

            foreach ($this->routes[$method] as $pattern => $callback) {
                $regex = $this->convertPatternToRegex($pattern);

                if (preg_match($regex, $uri, $matches)) {
                    $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                    return call_user_func_array($callback, $params);
                }
            }

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
