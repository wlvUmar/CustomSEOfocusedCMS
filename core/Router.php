<?php
class Router {
    private $routes = [];
    private $notFound;

    public function get($pattern, $callback) {
        $this->routes['GET'][$pattern] = $callback;
    }

    public function post($pattern, $callback) {
        $this->routes['POST'][$pattern] = $callback;
    }

    public function notFound($callback) {
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
            $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

            $base = dirname($_SERVER['SCRIPT_NAME']);
            if ($base !== '/' && strpos($uri, $base) === 0) {
                $uri = substr($uri, strlen($base));
            }

            $uri = '/' . trim($uri, '/');

            if (isset($this->routes[$method])) {

                uksort($this->routes[$method], function ($a, $b) {
                    return (strpos($a, '{') !== false) <=> (strpos($b, '{') !== false);
                });

                foreach ($this->routes[$method] as $pattern => $callback) {

                    $pattern = '/' . ltrim($pattern, '/');
                    $pattern = preg_replace(
                        '/\{([a-zA-Z0-9_]+)\}/',
                        '(?P<$1>[^/]+)',
                        $pattern
                    );

                    $pattern = '#^' . $pattern . '$#';

                    if (preg_match($pattern, $uri, $matches)) {
                        $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                        return call_user_func_array($callback, $params);
                    }
                }
            }

            $this->error(404);

        } catch (\Throwable $e) {
            error_log($e);
            $this->error(500);
        }
    }
}