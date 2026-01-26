<?php

class IskDaemonClient
{
    private string $url;
    private int $dbId;

    public function __construct(int $dbId)
    {
        $this->dbId = $dbId;
        $this->url = "http://" . ISK_HOST . ":" . ISK_PORT . ISK_RPC_PATH;
    }

    // ✅ XML-RPC introspection
    public function listMethods(): array
    {
        $res = $this->call('system.listMethods', []);
        return is_array($res) ? $res : [];
    }

    // ✅ create db (fallback: createdb / createDb)
    public function createdb(): bool
    {
        // некоторые сборки называют метод по-разному
        $tryNames = ['createdb', 'createDb'];

        foreach ($tryNames as $name) {
            try {
                $res = $this->call($name, [$this->dbId]);
                return (bool)$res;
            } catch (Throwable $e) {
                // если метода нет - пробуем следующий
                if (strpos($e->getMessage(), 'procedure') !== false && strpos($e->getMessage(), 'not found') !== false) {
                    continue;
                }
                // другая ошибка - пробрасываем
                throw $e;
            }
        }

        throw new Exception("Не найден метод создания базы (createdb/createDb) в daemon");
    }

    public function ensureDbReady(): void
    {
        // 1) пробуем получить count
        try {
            $this->getDbImgCount();
            return;
        } catch (Throwable $e) {
            // 2) пробуем создать базу
            $created = $this->createdb();

            if (!$created) {
                throw new Exception("createdb() вернул false. DB {$this->dbId} не создана.");
            }

            $this->saveAllDbs();

            // 3) проверяем ещё раз
            $this->getDbImgCount();
        }
    }

    public function getDbImgCount(): int
    {
        $res = $this->call('getDbImgCount', [$this->dbId]);
        if (!is_int($res)) {
            throw new Exception("getDbImgCount вернул не int: " . print_r($res, true));
        }
        return $res;
    }

    public function resetdb(): bool
    {
        // в логах у тебя точно есть resetdb()
        $res = $this->call('resetdb', [$this->dbId]);
        return (bool)$res;
    }


    // ✅ УНИКАЛЬНЫЙ id БЕЗ isImgOnDb (чтобы не падать на unknown db)
    public function generateUniqueId(): int
    {
        // безопасный int32
        $base = $this->getDbImgCount(); // вернет число <= 2 млрд
        return $base + 200;
    }

    public function addImg(int $imgId, string $path): bool
    {
        $res = $this->call('addImg', [$this->dbId, $imgId, $path]);
        return (bool)$res;
    }

    public function removeImg(int $imgId): bool
    {
        $res = $this->call('removeImg', [$this->dbId, $imgId]);
        return (bool)$res;
    }

    public function saveAllDbs(): bool
    {
        // у тебя в контейнере лог: savealldbs()
        $tryNames = ['saveAllDbs', 'savealldbs'];

        foreach ($tryNames as $name) {
            try {
                $res = $this->call($name, []);
                return (bool)$res;
            } catch (Throwable $e) {
                // если метода нет — пробуем следующий
                if (strpos($e->getMessage(), 'procedure') !== false && strpos($e->getMessage(), 'not found') !== false) {
                    continue;
                }
                throw $e;
            }
        }

        throw new Exception("Метод сохранения базы не найден (saveAllDbs/savealldbs).");
    }


    public function queryImgID(int $imgId, int $count = 40): array
    {
        $res = $this->call('queryImgID', [$this->dbId, $imgId, $count]);

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

    // ========= XML-RPC core =========

    private function call(string $methodName, array $params)
    {
        $xml = $this->buildRequestXml($methodName, $params);

        $ch = curl_init($this->url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: text/xml'],
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_TIMEOUT => 20,
        ]);

        $resp = curl_exec($ch);
        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new Exception("curl error: $err");
        }

        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code >= 400) {
            throw new Exception("HTTP error: $code, response=" . substr($resp, 0, 300));
        }

        return $this->parseResponseXml($resp);
    }

    private function buildRequestXml(string $methodName, array $params): string
    {
        $xml = '<?xml version="1.0"?>';
        $xml .= '<methodCall>';
        $xml .= '<methodName>' . htmlspecialchars($methodName, ENT_XML1) . '</methodName>';
        $xml .= '<params>';

        foreach ($params as $p) {
            $xml .= '<param><value>' . $this->encodeValue($p) . '</value></param>';
        }

        $xml .= '</params></methodCall>';
        return $xml;
    }

    private function encodeValue($v): string
    {
        if (is_int($v)) return "<int>$v</int>";
        if (is_float($v)) return "<double>$v</double>";
        if (is_bool($v)) return "<boolean>" . ($v ? "1" : "0") . "</boolean>";
        if (is_string($v)) return "<string>" . htmlspecialchars($v, ENT_XML1) . "</string>";

        if (is_array($v)) {
            $isAssoc = array_keys($v) !== range(0, count($v) - 1);

            if ($isAssoc) {
                $out = "<struct>";
                foreach ($v as $k => $val) {
                    $out .= "<member>";
                    $out .= "<name>" . htmlspecialchars((string)$k, ENT_XML1) . "</name>";
                    $out .= "<value>" . $this->encodeValue($val) . "</value>";
                    $out .= "</member>";
                }
                $out .= "</struct>";
                return $out;
            }

            $out = "<array><data>";
            foreach ($v as $val) {
                $out .= "<value>" . $this->encodeValue($val) . "</value>";
            }
            $out .= "</data></array>";
            return $out;
        }

        return "<string>" . htmlspecialchars((string)$v, ENT_XML1) . "</string>";
    }

    private function parseResponseXml(string $xml)
    {
        $sx = @simplexml_load_string($xml);
        if (!$sx) {
            throw new Exception("Ответ daemon не XML-RPC: " . substr($xml, 0, 300));
        }

        if (isset($sx->fault)) {
            $fault = $this->decodeValue($sx->fault->value);
            throw new Exception("XML-RPC fault: " . print_r($fault, true));
        }

        $valueNode = $sx->params->param->value ?? null;
        if (!$valueNode) return null;

        return $this->decodeValue($valueNode);
    }

    private function decodeValue(\SimpleXMLElement $valueNode)
    {
        if (isset($valueNode->int)) return (int)$valueNode->int;
        if (isset($valueNode->i4)) return (int)$valueNode->i4;
        if (isset($valueNode->double)) return (float)$valueNode->double;
        if (isset($valueNode->boolean)) return ((string)$valueNode->boolean) === '1';
        if (isset($valueNode->string)) return (string)$valueNode->string;

        if (isset($valueNode->array)) {
            $arr = [];
            foreach ($valueNode->array->data->value as $v) {
                $arr[] = $this->decodeValue($v);
            }
            return $arr;
        }

        if (isset($valueNode->struct)) {
            $obj = [];
            foreach ($valueNode->struct->member as $m) {
                $name = (string)$m->name;
                $obj[$name] = $this->decodeValue($m->value);
            }
            return $obj;
        }

        return (string)$valueNode;
    }
}
