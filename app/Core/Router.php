<?php

declare(strict_types=1);

namespace Sofiago\Core;

/**
 * Minimal regex router: register('GET', '/listings/{slug}', [Controller::class, 'show']).
 * No middleware pipeline — guards (requireAuth() etc. in app/Middleware/Guards.php) are called
 * as the first line of the controller action that needs them, which is plenty for this app's
 * size and keeps the request flow easy to follow top to bottom.
 */
final class Router
{
    /** @var array<int, array{method: string, path: string, handler: callable|array}> */
    private array $routes = [];

    public function get(string $path, callable|array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function put(string $path, callable|array $handler): void
    {
        $this->add('PUT', $path, $handler);
    }

    public function patch(string $path, callable|array $handler): void
    {
        $this->add('PATCH', $path, $handler);
    }

    public function delete(string $path, callable|array $handler): void
    {
        $this->add('DELETE', $path, $handler);
    }

    private function add(string $method, string $path, callable|array $handler): void
    {
        $this->routes[] = ['method' => $method, 'path' => $path, 'handler' => $handler];
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        // Browsers sending PUT/PATCH/DELETE from an HTML form use a hidden _method field.
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper((string) $_POST['_method']);
        }

        $path = (string) (parse_url($uri, PHP_URL_PATH) ?? '/');
        $path = '/' . trim(rawurldecode($path), '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $route['path']);

            if (preg_match('#^' . $pattern . '$#', $path, $matches) === 1) {
                $params = array_filter($matches, static fn ($key) => is_string($key), ARRAY_FILTER_USE_KEY);
                $this->invoke($route['handler'], $params);

                return;
            }
        }

        http_response_code(404);
        echo render('errors/404.tpl.php');
    }

    /** @param array<string, string> $params */
    private function invoke(callable|array $handler, array $params): void
    {
        if (is_array($handler) && is_string($handler[0])) {
            [$class, $action] = $handler;
            $controller = new $class();
            $controller->$action($params);

            return;
        }

        /** @var callable $handler */
        $handler($params);
    }
}
