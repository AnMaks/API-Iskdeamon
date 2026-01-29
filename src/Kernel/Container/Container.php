<?php
/**
 * Контейнер и автосборка через Reflection.
 * Создаёт Request.
 */
namespace App\Kernel\Container;

use App\Kernel\Http\Request;
use App\Kernel\Router\Router;
use App\Repositories\ImageRepository;
use App\Services\IskDaemonClient;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

final class Container
{

    private array $instances = [];

    public readonly Request $request;
    public readonly Router $router;

    /**
     * @param array $routes Route[]
     */
    public function __construct(array $routes)
    {
        $this->request = Request::createFromGlobals();
        $this->instances[Request::class] = $this->request;

        $this->instances[IskDaemonClient::class] = new IskDaemonClient(ISK_DB_ID);
        $this->instances[ImageRepository::class] = new ImageRepository();

        $this->router = new Router($routes, $this);
        $this->instances[Router::class] = $this->router;
    }

    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // автосборка классов
        if (!class_exists($id)) {
            throw new RuntimeException("Класс не найден: $id");
        }

        $obj = $this->make($id);
        $this->instances[$id] = $obj; // по умолчанию кешируем
        return $obj;
    }

    /**
     * @throws \ReflectionException
     */
    public function make(string $class): object
    {
        $ref = new ReflectionClass($class);

        if (!$ref->isInstantiable()) {
            throw new RuntimeException("Не может быть создан экземпляр: $class");
        }

        $ctor = $ref->getConstructor();
        if ($ctor === null || $ctor->getNumberOfParameters() === 0) {
            return new $class();
        }

        $args = [];
        foreach ($ctor->getParameters() as $p) {
            $type = $p->getType();
            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                if ($p->isDefaultValueAvailable()) {
                    $args[] = $p->getDefaultValue();
                    continue;
                }
                throw new RuntimeException("Не удалось присвоить параметры \${$p->getName()} for $class");
            }
            $dep = $type->getName();
            $args[] = $this->get($dep);
        }

        return $ref->newInstanceArgs($args);
    }
}
