<?php


require_once __DIR__ . '/../config.php';

require_once __DIR__ . '/../src/Support/Response.php';
require_once __DIR__ . '/../src/Kernel/Router/Route.php';
require_once __DIR__ . '/../src/Kernel/Router/Router.php';

require_once __DIR__ . '/../src/Services/IskDaemonClient.php';
require_once __DIR__ . '/../src/Services/UploadStorage.php';

require_once __DIR__ . '/../src/Controllers/WebController.php';
require_once __DIR__ . '/../src/Controllers/ApiController.php';

use App\Kernal\Router\Router;
use App\Services\IskDaemonClient;
use App\Support\Response;

try {
    $client = new IskDaemonClient(ISK_DB_ID);

    $routes = require __DIR__ . '/../routes.php';
    $router = new Router($routes);

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $path = rtrim($path, '/') ?: '/';

    $router->dispatch($method, $path);

} catch (Throwable $e) {
    Response::fail("Ошибка: " . $e->getMessage(), 500);
}
