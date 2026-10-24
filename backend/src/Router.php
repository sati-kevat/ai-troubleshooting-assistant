<?php

declare(strict_types=1);

/**
 * Minimal method + path router. Route patterns use {name} placeholders,
 * e.g. "/api/queries/{id}". Placeholders match a single path segment.
 */
final class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        $this->routes[] = [$method, $regex, $handler];
    }

    public function dispatch(string $method, string $path): void
    {
        $allowedMethods = [];
        foreach ($this->routes as [$routeMethod, $regex, $handler]) {
            if (!preg_match($regex, $path, $matches)) {
                continue;
            }
            if ($routeMethod !== $method) {
                $allowedMethods[] = $routeMethod;
                continue;
            }
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            Http::json($handler($params));
        }

        if ($allowedMethods) {
            header('Allow: ' . implode(', ', array_unique($allowedMethods)));
            throw new ApiException(405, 'method_not_allowed', 'This method is not allowed for this endpoint.');
        }
        throw ApiException::notFound('Unknown API endpoint.');
    }

    /** Extracts the "/api/..." part of the request path for both Apache and the PHP built-in server. */
    public static function currentPath(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $position = strpos($path, '/api/');
        if ($position === false) {
            return str_ends_with($path, '/api') ? '/api' : $path;
        }
        return rtrim(substr($path, $position), '/');
    }
}
