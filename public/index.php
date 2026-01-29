<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../local/vendor/autoload.php';

use App\Kernel\Container\Container;
use App\Support\Response;

try {
    $routes = require __DIR__ . '/../routes.php';

    $container = new Container($routes);
    $container->router->dispatch($container->request);

} catch (Throwable $e) {
    Response::fail("Ошибка: " . $e->getMessage(), 500);
}

