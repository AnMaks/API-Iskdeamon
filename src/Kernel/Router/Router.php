<?php
// Он принимает список маршрутов (GET/POST/DELETE) и ищет нужный по URL.
// Если маршрут найден — вызывает нужный метод контроллера.
// Также умеет доставать параметры из URL.

namespace App\Kernal\Router;

use App\Support\Response;

final class Router
{
    /** @var Route[] */
    private array $routes = [];

    public function __construct(array $routes)
    {
        $this->routes = $routes;
    }

    public function dispatch(string $method, string $uriPath): void
    {
        $method = strtoupper($method);
        $uriPath = rtrim($uriPath, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route->method !== $method) continue;

            $match = $this->match($route->path, $uriPath);
            if ($match === null) continue;

            [$class, $action] = $route->handler;

            if (!class_exists($class)) {
                Response::fail("Контроллер не найден: $class", 500);
            }

            $controller = new $class();

            if (!method_exists($controller, $action)) {
                Response::fail("Метод не найден: $class::$action()", 500);
            }

            // передаем params (например id)
            $controller->$action($match);
            return;
        }

        Response::fail("Маршрут не найден: $method $uriPath", 404);
    }

    private function match(string $routePath, string $uriPath): ?array
    {
        $routePath = rtrim($routePath, '/') ?: '/';

        // если без параметров
        if (!str_contains($routePath, '{')) {
            return $routePath === $uriPath ? [] : null;
        }

        // превращаем /x/{id}/y -> regex
        $regex = preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $routePath);
        $regex = '#^' . $regex . '$#';

        if (!preg_match($regex, $uriPath, $m)) return null;

        $params = [];
        foreach ($m as $k => $v) {
            if (!is_int($k)) $params[$k] = $v;
        }
        return $params;
    }
}
