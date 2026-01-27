<?php


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

    public function ensureDbReady(): void
    {
        try {
            $this->getDbImgCount();
            return;
        } catch (Throwable $e) {
            $created = $this->createdb();
            if (!$created) throw new Exception("createdb() вернул false. DB {$this->dbId} не создана.");

            $this->saveAllDbs();
            $this->getDbImgCount();
        }
    }

    public function createdb(): bool
    {
        foreach (['createdb', 'createDb'] as $name) {
            try {
                return (bool)$this->rpc->call($name, [$this->dbId]);
            } catch (Throwable $e) {
                $msg = $e->getMessage();
                if (stripos($msg, 'procedure') !== false && stripos($msg, 'not found') !== false) continue;
                throw $e;
            }
        }
        throw new Exception("Не найден метод создания базы (createdb/createDb).");
    }

    public function resetdb(): bool
    {
        return (bool)$this->rpc->call('resetdb', [$this->dbId]);
    }

    public function saveAllDbs(): bool
    {
        foreach (['saveAllDbs', 'savealldbs'] as $name) {
            try {
                return (bool)$this->rpc->call($name, []);
            } catch (Throwable $e) {
                $msg = $e->getMessage();
                if (stripos($msg, 'procedure') !== false && stripos($msg, 'not found') !== false) continue;
                throw $e;
            }
        }
        throw new Exception("Не найден метод сохранения базы (saveAllDbs/savealldbs).");
    }

    public function getDbImgCount(): int
    {
        $res = $this->rpc->call('getDbImgCount', [$this->dbId]);
        if (is_string($res) && ctype_digit($res)) $res = (int)$res;
        if (!is_int($res)) throw new Exception("getDbImgCount вернул не int: " . print_r($res, true));
        return $res;
    }

    public function addImg(int $imgId, string $path): bool
    {
        return (bool)$this->rpc->call('addImg', [$this->dbId, $imgId, $path]);
    }

    public function removeImg(int $imgId): bool
    {
        return (bool)$this->rpc->call('removeImg', [$this->dbId, $imgId]);
    }

    public function queryImgID(int $imgId, int $count = 10): array
    {
        $res = $this->rpc->call('queryImgID', [$this->dbId, $imgId, $count]);
        if (!is_array($res)) throw new Exception("queryImgID вернул не array: " . print_r($res, true));

        $out = [];
        foreach ($res as $pair) {
            if (is_array($pair) && count($pair) >= 2) {
                $out[] = ['id' => (int)$pair[0], 'perc' => (float)$pair[1]];
            }
        }
        return $out;
    }
}
