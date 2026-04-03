<?php
class Router {
    private array $routes = [];

    // Now accepts an optional array of middleware
    public function add(string $method, string $path, callable $handler, array $middleware = []): void {
        $this->routes[] = [
            'method'     => strtoupper($method),
            'path'       => $path,
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }
public function dispatch(string $method, string $uri): void {
    $path = parse_url($uri, PHP_URL_PATH);
    $path = rtrim($path, '/') ?: '/';

    foreach ($this->routes as $route) {
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $route['path']);
        $pattern = "#^{$pattern}$#";

        if ($route['method'] === strtoupper($method)
            && preg_match($pattern, $path, $matches)
        ) {
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            echo json_encode(($route['handler'])($params));
            return;
        }
    }

    http_response_code(404);
    echo json_encode(['error' => 'Route not found']);
}
}