<?php
// Этот файл нужен для создания маршрутов (роутов).
// Route хранит метод (GET/POST/DELETE), путь (/api/health) и контроллер с методом.
// Потом Router использует эти Route, чтобы понять куда направить запрос.

namespace App\Kernel\Router;

final class Route
{
    public string $method;
    public string $path;
    /** @var array{0:string,1:string} */
    public array $handler;

    private function __construct(string $method, string $path, array $handler)
    {
        $this->method  = strtoupper($method);
        $this->path    = rtrim($path, '/') ?: '/';
        $this->handler = $handler;
    }

    public static function get(string $path, array $handler): self
    {
        return new self('GET', $path, $handler);
    }

    public static function post(string $path, array $handler): self
    {
        return new self('POST', $path, $handler);
    }

    public static function delete(string $path, array $handler): self
    {
        return new self('DELETE', $path, $handler);
    }
}
