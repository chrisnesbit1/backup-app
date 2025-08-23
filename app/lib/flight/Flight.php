<?php
/**
 * Minimal subset of Flight framework.
 * This placeholder is provided to allow development without the official library.
 * For production, replace with https://github.com/mikecao/flight.
 */
class Flight
{
    private static array $routes = [];
    private static array $vars = [];

    public static function route(string $method, string $path, callable $handler): void
    {
        self::$routes[] = [$method, $path, $handler];
    }

    public static function set(string $key, $value): void
    {
        self::$vars[$key] = $value;
    }

    public static function get(string $key)
    {
        return self::$vars[$key] ?? null;
    }

    public static function json($data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    public static function start(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        foreach (self::$routes as [$m, $path, $handler]) {
            $pattern = '@^' . preg_replace('@:[^/]+@', '([^/]+)', $path) . '$@';
            if (strcasecmp($method, $m) === 0 && preg_match($pattern, $uri, $matches)) {
                array_shift($matches);
                $handler(...$matches);
                return;
            }
        }
        http_response_code(404);
        echo 'Not Found';
    }
}
