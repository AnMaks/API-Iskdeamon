<?php
// Это контроллер для API.
// Здесь находятся методы, которые обрабатывают запросы (/api/health, /api/images и т.д.).
// Каждый метод вызывает нужные функции IskDaemonClient и возвращает JSON ответ через Response.

namespace App\Controllers;

use App\Support\Response;

final class ApiController
{
    public function health(array $params = []): void
    {
        global $client;

        $client->ensureDbReady();
        $count = $client->getDbImgCount();

        Response::ok([
            'daemon' => 'online',
            'dbId' => ISK_DB_ID,
            'imagesInDb' => $count,
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

        $ok = $client->resetdb();
        $client->saveAllDbs();

        Response::ok(['dbId' => ISK_DB_ID, 'reset' => (bool)$ok]);
    }

    public function addImage(array $params = []): void
    {
        global $client;

        $client->ensureDbReady();

        $saved = \App\Services\UploadStorage::saveUploadedImage('image');
        $newId = $client->getDbImgCount() + 200; // безопасный int32

        $try1 = $saved['containerPath'];
        $try2 = $saved['relativePath'];

        $okAdd = false;
        $usedPath = null;

        try {
            $okAdd = $client->addImg($newId, $try1);
            if ($okAdd) $usedPath = $try1;
        } catch (\Throwable $e) {}

        if (!$okAdd) {
            $okAdd = $client->addImg($newId, $try2);
            if ($okAdd) $usedPath = $try2;
        }

        if (!$okAdd) {
            Response::fail("Daemon не смог обработать картинку", 502, [
                'imageId' => $newId,
                'try1' => $try1,
                'try2' => $try2
            ]);
        }

        $client->saveAllDbs();

        Response::ok([
            'imageId' => $newId,
            'indexed' => true,
            'usedPath' => $usedPath,
            'filename' => $saved['filename']
        ], 201);
    }

    public function searchUpload(array $params = []): void
    {
        global $client;

        $client->ensureDbReady();

        $saved = \App\Services\UploadStorage::saveUploadedImage('image');

        $tempId = $client->getDbImgCount() + 200; // тоже безопасно
        $count = isset($_GET['count']) ? (int)$_GET['count'] : 10;
        if ($count <= 0) $count = 10;

        $try1 = $saved['containerPath'];
        $try2 = $saved['relativePath'];

        $okAdd = false;
        try { $okAdd = $client->addImg($tempId, $try1); } catch (\Throwable $e) {}
        if (!$okAdd) $okAdd = $client->addImg($tempId, $try2);

        if (!$okAdd) {
            @unlink($saved['hostPath']);
            Response::fail("Не удалось добавить временную картинку", 502, [
                'tempId' => $tempId,
                'try1' => $try1,
                'try2' => $try2
            ]);
        }

        $matches = $client->queryImgID($tempId, $count);

        // убираем саму себя из выдачи
        $matches = array_values(array_filter($matches, fn($x) => (int)$x['id'] !== $tempId));

        $client->removeImg($tempId);
        $client->saveAllDbs();
        @unlink($saved['hostPath']);

        Response::ok([
            'tempId' => $tempId,
            'matches' => $matches,
            'count' => count($matches)
        ]);
    }

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

        Response::ok([
            'imageId' => $imgId,
            'matches' => $matches,
            'count' => count($matches),
        ]);
    }

    public function deleteById(array $params): void
    {
        global $client;

        $client->ensureDbReady();

        $imgId = (int)($params['id'] ?? 0);
        if ($imgId <= 0) Response::fail("Неверный id", 400);

        $deleted = $client->removeImg($imgId);
        $client->saveAllDbs();

        Response::ok([
            'imageId' => $imgId,
            'deletedFromDaemon' => (bool)$deleted,
        ]);
    }
}
