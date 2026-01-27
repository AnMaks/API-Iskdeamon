<?php
// Это контроллер для API.
// Здесь находятся методы, которые обрабатывают запросы (/api/health, /api/images и т.д.).
// Каждый метод вызывает нужные функции IskDaemonClient и возвращает JSON ответ через Response.

namespace App\Controllers;

use App\Support\Response;
use App\Services\UploadStorage;
use App\Repositories\ImageRepository;

final class ApiController
{
    public function health(array $params = []): void
    {
        global $client;
        $client->ensureDbReady();

        Response::ok([
            'daemon' => 'online',
            'dbId' => ISK_DB_ID,
            'imagesInDaemon' => $client->getDbImgCount(),
        ]);
    }

    public function init(array $params = []): void
    {
        global $client;
        $created = $client->createdb();
        $client->saveAllDbs();

        Response::ok(['dbId' => ISK_DB_ID, 'created' => (bool)$created]);
    }

    public function reset(array $params = []): void
    {
        global $client;
        $repo = new ImageRepository();

        $ok = $client->resetdb();
        $client->saveAllDbs();

        // метаданные тоже очищаем
        $repo->truncate();

        Response::ok(['dbId' => ISK_DB_ID, 'reset' => (bool)$ok, 'mysqlTruncated' => true]);
    }

    // 1) admin adds image -> save meta -> save thumb -> index in daemon
    public function addImage(array $params = []): void
    {
        global $client;
        $client->ensureDbReady();

        // сохраняем файл в thumbs (это и есть "миниатюра" для индексации)
        $saved = UploadStorage::saveUploadedImage('image', UPLOAD_SUBDIR);

        // пишем метаданные в MySQL и получаем id (именно он идёт в daemon)
        $repo = new ImageRepository();
        $id = $repo->insert($saved);

        // index
        $okAdd = false;
        $usedPath = null;

        try { $okAdd = $client->addImg($id, $saved['containerPath']); $usedPath = $saved['containerPath']; }
        catch (\Throwable $e) {}

        if (!$okAdd) {
            try { $okAdd = $client->addImg($id, $saved['relativePath']); $usedPath = $saved['relativePath']; }
            catch (\Throwable $e) {}
        }

        if (!$okAdd) {
            // откат метаданных
            $repo->delete($id);
            Response::fail("Daemon не смог обработать картинку", 502, [
                'imageId' => $id,
                'try1' => $saved['containerPath'],
                'try2' => $saved['relativePath'],
            ]);
        }

        $client->saveAllDbs();

        Response::ok([
            'imageId' => $id,
            'indexed' => true,
            'fileUrl' => "/api/images/{$id}/file",
            'usedPath' => $usedPath,
        ], 201);
    }

    // random images (step 7-10 из архитектуры)
    public function random(array $params = []): void
    {
        $limit = isset($_GET['count']) ? (int)$_GET['count'] : 1;
        $repo = new ImageRepository();

        $rows = $repo->random($limit);
        $out = array_map(fn($r) => [
            'id' => (int)$r['id'],
            'fileUrl' => "/api/images/{$r['id']}/file",
            'filename' => $r['filename'],
        ], $rows);

        Response::ok(['count' => count($out), 'items' => $out]);
    }

    // search by upload (temp)
    public function searchUpload(array $params = []): void
    {
        global $client;
        $client->ensureDbReady();

        $saved = UploadStorage::saveUploadedImage('image', UPLOAD_SUBDIR);

        $count = isset($_GET['count']) ? (int)$_GET['count'] : 10;
        if ($count <= 0) $count = 10;

        // tempId должен быть int32 (иначе будет "long int exceeds XML-RPC limits")
        $tempId = 1500000000 + random_int(0, 1000000);

        $okAdd = false;
        try { $okAdd = $client->addImg($tempId, $saved['containerPath']); } catch (\Throwable $e) {}
        if (!$okAdd) {
            try { $okAdd = $client->addImg($tempId, $saved['relativePath']); } catch (\Throwable $e) {}
        }

        if (!$okAdd) {
            @unlink($saved['hostPath']);
            Response::fail("Не удалось добавить временную картинку", 502, [
                'tempId' => $tempId,
                'try1' => $saved['containerPath'],
                'try2' => $saved['relativePath'],
            ]);
        }

        $matches = $client->queryImgID($tempId, $count);

        // remove temp + clean file
        try { $client->removeImg($tempId); } catch (\Throwable $e) {}
        $client->saveAllDbs();
        @unlink($saved['hostPath']);

        // enrich via MySQL
        $ids = array_map(fn($x) => (int)$x['id'], $matches);
        $repo = new ImageRepository();
        $meta = $repo->findManyByIds($ids);

        foreach ($matches as &$m) {
            $id = (int)$m['id'];
            $m['fileUrl'] = "/api/images/{$id}/file";
            $m['filename'] = $meta[$id]['filename'] ?? null;
        }
        unset($m);

        Response::ok(['tempId' => $tempId, 'count' => count($matches), 'matches' => $matches]);
    }

    // search similar by ID (step 11-16)
    public function matchesById(array $params): void
    {
        global $client;
        $client->ensureDbReady();

        $imgId = (int)($params['id'] ?? 0);
        if ($imgId <= 0) Response::fail("Неверный id", 400);

        $count = isset($_GET['count']) ? (int)$_GET['count'] : 10;
        if ($count <= 0) $count = 10;

        $matches = $client->queryImgID($imgId, $count);
        $matches = array_values(array_filter($matches, fn($x) => (int)$x['id'] !== $imgId));

        $ids = array_map(fn($x) => (int)$x['id'], $matches);
        $repo = new ImageRepository();
        $meta = $repo->findManyByIds($ids);

        foreach ($matches as &$m) {
            $id = (int)$m['id'];
            $m['fileUrl'] = "/api/images/{$id}/file";
            $m['filename'] = $meta[$id]['filename'] ?? null;
        }
        unset($m);

        Response::ok(['imageId' => $imgId, 'count' => count($matches), 'matches' => $matches]);
    }

    // отдача миниатюры/файла по id (чтобы показывать результаты)
    public function fileById(array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) Response::fail("Неверный id", 400);

        $repo = new ImageRepository();
        $row = $repo->find($id);
        if (!$row) Response::fail("Не найдено в MySQL", 404);

        $path = $row['host_path'];
        if (!is_file($path)) Response::fail("Файл не найден на диске", 404);

        header('Content-Type: image/jpeg');
        readfile($path);
        exit;
    }

    public function deleteById(array $params): void
    {
        global $client;
        $client->ensureDbReady();

        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) Response::fail("Неверный id", 400);

        // удаляем из daemon
        $deleted = false;
        try { $deleted = (bool)$client->removeImg($id); } catch (\Throwable $e) {}

        $client->saveAllDbs();

        // удаляем из MySQL (и можно удалить файл)
        $repo = new ImageRepository();
        $row = $repo->find($id);
        if ($row && is_file($row['host_path'])) @unlink($row['host_path']);
        $repo->delete($id);

        Response::ok(['imageId' => $id, 'deletedFromDaemon' => $deleted, 'deletedFromMysql' => true]);
    }
}
