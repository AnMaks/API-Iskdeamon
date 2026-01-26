<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/IskDaemonClient.php';
require_once __DIR__ . '/../src/UploadStorage.php';

function ok($data = [], int $code = 200): void {
    http_response_code($code);
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function fail(string $message, int $code = 400, $extra = null): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message, 'extra' => $extra], JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = rtrim($path, '/');

try {
    $client = new IskDaemonClient(ISK_DB_ID);

    // ✅ Init DB (создать базу вручную)
    if ($method === 'POST' && $path === '/api/init') {
        $created = $client->createdb();
        $client->saveAllDbs();

        ok([
            'dbId' => ISK_DB_ID,
            'created' => (bool)$created
        ]);
    }

    // ✅ Для всех остальных запросов гарантируем, что база есть
    $client->ensureDbReady();

    // ✅ Health
    if ($method === 'GET' && $path === '/api/health') {
        $count = $client->getDbImgCount();
        ok([
            'daemon' => 'online',
            'dbId' => ISK_DB_ID,
            'imagesInDb' => $count,
            'rpcUrl' => 'http://' . ISK_HOST . ':' . ISK_PORT . ISK_RPC_PATH
        ]);
    }

    // ✅ Add image to index permanently
    if ($method === 'POST' && $path === '/api/images') {
        $saved = UploadStorage::saveUploadedImage('image');

        // ✅ ID уникальный
        $newId = $client->generateUniqueId();

        // пробуем 2 пути (абсолютный и относительный)
        $try1 = $saved['containerPath'];   // /opt/.../upload/file.jpg
        $try2 = $saved['relativePath'];    // upload/file.jpg

        $okAdd = false;
        $usedPath = null;

        try {
            $okAdd = $client->addImg($newId, $try1);
            if ($okAdd) $usedPath = $try1;
        } catch (Throwable $e) {
            // ignore, попробуем второй
        }

        if (!$okAdd) {
            $okAdd = $client->addImg($newId, $try2);
            if ($okAdd) $usedPath = $try2;
        }

        if (!$okAdd) {
            fail("Daemon не смог обработать картинку (addImg fault 8002).", 502, [
                'imageId' => $newId,
                'hostPath' => $saved['hostPath'],
                'try1' => $try1,
                'try2' => $try2
            ]);
        }

        $client->saveAllDbs();

        ok([
            'imageId' => $newId,
            'indexed' => true,
            'usedPath' => $usedPath,
            'file' => [
                'filename' => $saved['filename'],
                'containerPath' => $saved['containerPath']
            ]
        ], 201);
    }

    // ✅ Search by upload (temporary)
    if ($method === 'POST' && $path === '/api/search') {
        $saved = UploadStorage::saveUploadedImage('image');

        $tempId = $client->generateUniqueId();

        // try add temp image (also 2 paths)
        $try1 = $saved['containerPath'];
        $try2 = $saved['relativePath'];

        $okAdd = false;
        try { $okAdd = $client->addImg($tempId, $try1); } catch(Throwable $e) {}
        if (!$okAdd) $okAdd = $client->addImg($tempId, $try2);

        if (!$okAdd) {
            @unlink($saved['hostPath']);
            fail("Не удалось добавить временную картинку (daemon error).", 502, [
                'tempId' => $tempId,
                'try1' => $try1,
                'try2' => $try2
            ]);
        }

        $count = isset($_GET['count']) ? (int)$_GET['count'] : 40;
        if ($count <= 0) $count = 40;

        $matches = $client->queryImgID($tempId, $count);
        // ✅ убрать "сам себя" из выдачи
        $matches = array_values(array_filter($matches, fn($x) => (int)$x['id'] !== $tempId));

        // remove temp
        $client->removeImg($tempId);
        $client->saveAllDbs();

        // delete local file
        @unlink($saved['hostPath']);

        ok([
            'tempId' => $tempId,
            'matches' => $matches,
            'count' => count($matches),
        ]);
    }

    // ✅ Reset DB (очистить базу полностью)
    if ($method === 'POST' && $path === '/api/reset') {
        $ok = $client->resetdb();
        $client->saveAllDbs();

        ok([
            'dbId' => ISK_DB_ID,
            'reset' => (bool)$ok
        ]);
    }


    // ✅ Search by ID
    if ($method === 'GET' && preg_match('#^/api/images/(\d+)/matches$#', $path, $m)) {
        $imgId = (int)$m[1];
        $count = isset($_GET['count']) ? (int)$_GET['count'] : 40;
        if ($count <= 0) $count = 40;

        $matches = $client->queryImgID($imgId, $count);

        // remove itself from list
        $matches = array_values(array_filter($matches, fn($x) => (int)$x['id'] !== $imgId));

        ok([
            'imageId' => $imgId,
            'matches' => $matches,
            'count' => count($matches),
        ]);
    }

    // ✅ Delete by ID
    if ($method === 'DELETE' && preg_match('#^/api/images/(\d+)$#', $path, $m)) {
        $imgId = (int)$m[1];

        $deleted = $client->removeImg($imgId);
        $client->saveAllDbs();

        ok([
            'imageId' => $imgId,
            'deletedFromDaemon' => $deleted,
        ]);
    }

    if ($method === 'GET' && $path === '/api/debug/methods') {
        ok([
            'methods' => $client->listMethods()
        ]);
    }


    fail("Маршрут не найден: $method $path", 404);

} catch (Throwable $e) {
    fail("Ошибка: " . $e->getMessage(), 500);
}
