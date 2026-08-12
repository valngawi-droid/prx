<?php

declare(strict_types=1);

namespace ChiperX\Core;

/**
 * Router ringan dengan parameter {nama} dan middleware.
 * Mendukung GET / POST / PUT / PATCH / DELETE.
 */
final class Router
{
    /** @var array<int, array{method:string, pattern:string, regex:string, handler:callable|array, middleware:array<int, class-string>}> */
    private array $routes = [];

    /** @param class-string ...$middleware */
    public function get(string $pattern, callable|array $handler, string ...$middleware): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, callable|array $handler, string ...$middleware): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    public function put(string $pattern, callable|array $handler, string ...$middleware): void
    {
        $this->add('PUT', $pattern, $handler, $middleware);
    }

    public function delete(string $pattern, callable|array $handler, string ...$middleware): void
    {
        $this->add('DELETE', $pattern, $handler, $middleware);
    }

    /** @param array<int, class-string> $middleware */
    private function add(string $method, string $pattern, callable|array $handler, array $middleware): void
    {
        $regex = preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $pattern) ?? $pattern;
        $this->routes[] = [
            'method'     => $method,
            'pattern'    => $pattern,
            'regex'      => '#^' . $regex . '$#',
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method) {
                continue;
            }
            if (!preg_match($route['regex'], $request->path, $matches)) {
                continue;
            }
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            // Jalankan middleware berurutan; false = hentikan
            foreach ($route['middleware'] as $mw) {
                if ((new $mw())->handle($request) === false) {
                    return;
                }
            }

            [$class, $action] = $route['handler'];
            $controller = new $class();
            $result = $controller->$action($request, $params);

            if ($result instanceof Response) {
                $result->send();
            } elseif (is_array($result)) {
                Response::json($result)->send();
            } elseif (is_string($result)) {
                Response::html($result)->send();
            }
            return;
        }
        $this->notFound();
    }

    private function notFound(): void
    {
        try {
            Response::html(View::render('errors/404', [], 'layouts/main'), 404)->send();
        } catch (\Throwable) {
            Response::html('<h1>404 Not Found</h1>', 404)->send();
        }
    }
}
