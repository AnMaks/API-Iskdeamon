<?php

// Router: ищет маршрут по (method + path) и вызывает action контроллера.

namespace App\Kernel\Router;

use App\Kernel\Container\Container;
use App\Kernel\Http\Request;
use App\Support\Response;

final class Router
{
    /** @var Route[] */
    private array $routes = [];
    private Container $container;

    public function __construct(array $routes, Container $container)
    {
        $this->routes = $routes;
        $this->container = $container;
    }

    public function dispatch(Request $request): void
    {
        $method = strtoupper($request->method);
        $uriPath = rtrim($request->path, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route->method !== $method) continue;

            $match = $this->match($route->path, $uriPath);
            if ($match === null) continue;

            [$class, $action] = $route->handler;

            if (!class_exists($class)) {
                Response::fail("Контроллер не найден: $class", 500);
            }

            $controller = $this->container->get($class);

            if (!method_exists($controller, $action)) {
                Response::fail("Метод не найден: $class::$action()", 500);
            }

            // action(Request $request, array $params)
            $controller->$action($request, $match);
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

        // /x/{id}/y -> regex
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
