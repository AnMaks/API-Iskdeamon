<?php
declare(strict_types=1);

namespace App\Services;

use Exception;
use Throwable;

/**
 * Высокоуровневый клиент isk-daemon.
 * Тут логика: создать базу, добавить картинку, поиск похожих.
 */
final class IskDaemonClient
{
    private int $dbId;
    private XmlRpcTransport $rpc;

    public function __construct(int $dbId)
    {
        $this->dbId = $dbId;
        $this->rpc = new XmlRpcTransport(ISK_HOST, ISK_PORT, ISK_RPC_PATH, 20);
    }

    public function listMethods(): array
    {
        $res = $this->rpc->call('system.listMethods', []);
        return is_array($res) ? $res : [];
    }

    public function createdb(): bool
    {
        $tryNames = ['createdb', 'createDb'];

        foreach ($tryNames as $name) {
            try {
                $res = $this->rpc->call($name, [$this->dbId]);
                return (bool)$res;
            } catch (Throwable $e) {
                $msg = $e->getMessage();
                if (stripos($msg, 'procedure') !== false && stripos($msg, 'not found') !== false) {
                    continue;
                }
                throw $e;
            }
        }

        throw new Exception("Не найден метод создания базы (createdb/createDb) в приложении");
    }

    public function ensureDbReady(): void
    {
        try {
            $this->getDbImgCount();
            return;
        } catch (Throwable $e) {
            $created = $this->createdb();

            if (!$created) {
                throw new Exception("createdb() вернул false. DB {$this->dbId} не создана.");
            }

            $this->saveAllDbs();
            $this->getDbImgCount();
        }
    }

    public function getDbImgCount(): int
    {
        $res = $this->rpc->call('getDbImgCount', [$this->dbId]);

        // иногда int может прийти строкой
        if (is_string($res) && ctype_digit($res)) $res = (int)$res;

        if (!is_int($res)) {
            throw new Exception("getDbImgCount вернул не int: " . print_r($res, true));
        }

        return $res;
    }

    public function resetdb(): bool
    {
        return (bool)$this->rpc->call('resetdb', [$this->dbId]);
    }

    public function generateUniqueId(): int
    {
        return $this->getDbImgCount() + 200; // int32 safe
    }

    public function addImg(int $imgId, string $path): bool
    {
        return (bool)$this->rpc->call('addImg', [$this->dbId, $imgId, $path]);
    }

    public function removeImg(int $imgId): bool
    {
        return (bool)$this->rpc->call('removeImg', [$this->dbId, $imgId]);
    }

    public function saveAllDbs(): bool
    {
        $tryNames = ['saveAllDbs', 'savealldbs'];

        foreach ($tryNames as $name) {
            try {
                return (bool)$this->rpc->call($name, []);
            } catch (Throwable $e) {
                $msg = $e->getMessage();
                if (stripos($msg, 'procedure') !== false && stripos($msg, 'not found') !== false) {
                    continue;
                }
                throw $e;
            }
        }

        throw new Exception("Метод сохранения базы не найден (saveAllDbs/savealldbs).");
    }

    public function queryImgID(int $imgId, int $count = 40): array
    {
        $res = $this->rpc->call('queryImgID', [$this->dbId, $imgId, $count]);

        if (!is_array($res)) {
            throw new Exception("queryImgID вернул не array: " . print_r($res, true));
        }

        $out = [];
        foreach ($res as $pair) {
            if (is_array($pair) && count($pair) >= 2) {
                $out[] = [
                    'id' => (int)$pair[0],
                    'perc' => (float)$pair[1],
                ];
            }
        }
        return $out;
    }
}
