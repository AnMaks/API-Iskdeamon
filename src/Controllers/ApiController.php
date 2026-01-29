<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Kernel\Http\Request;
use App\Repositories\ImageRepository;
use App\Services\IskDaemonClient;
use App\Services\UploadStorage;
use App\Support\Response;
use Random\RandomException;

final class ApiController
{
    public function __construct(
        private readonly IskDaemonClient $client,
        private readonly ImageRepository $repo,
    ) {}

    public function health(Request $request, array $params = []): void
    {
        $this->client->ensureDbReady();

        Response::ok([
            'daemon' => 'online',
            'dbId' => ISK_DB_ID,
            'imagesInDaemon' => $this->client->getDbImgCount(),
        ]);
    }

    public function init(Request $request, array $params = []): void
    {
        $created = $this->client->createdb();
        $this->client->saveAllDbs();

        Response::ok(['dbId' => ISK_DB_ID, 'created' => (bool)$created]);
    }

    public function reset(Request $request, array $params = []): void
    {
        $ok = $this->client->resetdb();
        $this->client->saveAllDbs();

        // метаданные тоже очищаем
        $this->repo->truncate();

        Response::ok(['dbId' => ISK_DB_ID, 'reset' => (bool)$ok, 'mysqlTruncated' => true]);
    }

    public function addImage(Request $request, array $params = []): void
    {
        $this->client->ensureDbReady();

        $file = $request->file('image');
        if (!$file) {
            Response::fail("Нет файла в поле 'image'", 400);
        }

        $saved = UploadStorage::saveUploadedImage($file, UPLOAD_SUBDIR);

        $id = $this->repo->insert($saved);

        $okAdd = false;
        $usedPath = null;

        try { $okAdd = $this->client->addImg($id, $saved['containerPath']); $usedPath = $saved['containerPath']; }
        catch (\Throwable $e) {}

        if (!$okAdd) {
            try { $okAdd = $this->client->addImg($id, $saved['relativePath']); $usedPath = $saved['relativePath']; }
            catch (\Throwable $e) {}
        }

        if (!$okAdd) {
            // откат метаданных + удаляем файл
            $this->repo->delete($id);
            @unlink($saved['hostPath']);

            Response::fail("Daemon не смог обработать картинку", 502, [
                'imageId' => $id,
                'try1' => $saved['containerPath'],
                'try2' => $saved['relativePath'],
            ]);
        }

        $this->client->saveAllDbs();

        Response::ok([
            'imageId' => $id,
            'indexed' => true,
            'fileUrl' => "/api/images/{$id}/file",
            'usedPath' => $usedPath,
        ], 201);
    }

    public function random(Request $request, array $params = []): void
    {
        $limit = $request->queryInt('count', 1, 1, 50);

        $rows = $this->repo->random($limit);
        $out = array_map(fn($r) => [
            'id' => (int)$r['id'],
            'fileUrl' => "/api/images/{$r['id']}/file",
            'filename' => $r['filename'],
        ], $rows);

        Response::ok(['count' => count($out), 'items' => $out]);
    }

    /**
     * @throws \Throwable
     * @throws RandomException
     */
    public function searchUpload(Request $request, array $params = []): void
    {
        $this->client->ensureDbReady();

        $file = $request->file('image');
        if (!$file) {
            Response::fail("Нет файла в поле 'image'", 400);
        }

        $saved = UploadStorage::saveUploadedImage($file, UPLOAD_SUBDIR);

        $count = $request->queryInt('count', 10, 1, 50);

        $tempId = 1500000000 + random_int(0, 1000000);

        $okAdd = false;
        try { $okAdd = $this->client->addImg($tempId, $saved['containerPath']); } catch (\Throwable $e) {}
        if (!$okAdd) {
            try { $okAdd = $this->client->addImg($tempId, $saved['relativePath']); } catch (\Throwable $e) {}
        }

        if (!$okAdd) {
            @unlink($saved['hostPath']);
            Response::fail("Не удалось добавить временную картинку", 502, [
                'tempId' => $tempId,
                'try1' => $saved['containerPath'],
                'try2' => $saved['relativePath'],
            ]);
        }

        $matches = $this->client->queryImgID($tempId, $count);

        // remove temp + clean file
        try { $this->client->removeImg($tempId); } catch (\Throwable $e) {}
        $this->client->saveAllDbs();
        @unlink($saved['hostPath']);

        // enrich via MySQL
        $ids = array_map(fn($x) => (int)$x['id'], $matches);
        $meta = $this->repo->findManyByIds($ids);

        foreach ($matches as &$m) {
            $id = (int)$m['id'];
            $m['fileUrl'] = "/api/images/{$id}/file";
            $m['filename'] = $meta[$id]['filename'] ?? null;
        }
        unset($m);

        Response::ok(['tempId' => $tempId, 'count' => count($matches), 'matches' => $matches]);
    }

    public function matchesById(Request $request, array $params): void
    {
        $this->client->ensureDbReady();

        $imgId = (int)($params['id'] ?? 0);
        if ($imgId <= 0) Response::fail("Неверный id", 400);

        $count = $request->queryInt('count', 10, 1, 50);

        $matches = $this->client->queryImgID($imgId, $count);
        $matches = array_values(array_filter($matches, fn($x) => (int)$x['id'] !== $imgId));

        $ids = array_map(fn($x) => (int)$x['id'], $matches);
        $meta = $this->repo->findManyByIds($ids);

        foreach ($matches as &$m) {
            $id = (int)$m['id'];
            $m['fileUrl'] = "/api/images/{$id}/file";
            $m['filename'] = $meta[$id]['filename'] ?? null;
        }
        unset($m);

        Response::ok(['imageId' => $imgId, 'count' => count($matches), 'matches' => $matches]);
    }

    // отдаёт сам файл по id
    public function fileById(Request $request, array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) Response::fail("Неверный id", 400);

        $row = $this->repo->find($id);
        if (!$row) Response::fail("Не найдено в MySQL", 404);

        $path = $row['host_path'];
        if (!is_file($path)) Response::fail("Файл не найден на диске", 404);

        $mime = $row['mime'] ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        readfile($path);
        exit;
    }

    public function deleteById(Request $request, array $params): void
    {
        $this->client->ensureDbReady();

        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) Response::fail("Неверный id", 400);

        // удаляем из daemon
        $deleted = false;
        try { $deleted = (bool)$this->client->removeImg($id); } catch (\Throwable $e) {}

        $this->client->saveAllDbs();

        // удаляем из MySQL + файл
        $row = $this->repo->find($id);
        if ($row && is_file($row['host_path'])) @unlink($row['host_path']);
        $this->repo->delete($id);

        Response::ok(['imageId' => $id, 'deletedFromDaemon' => $deleted, 'deletedFromMysql' => true]);
    }
}
