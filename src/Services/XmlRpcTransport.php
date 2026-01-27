<?php
/**
 * Низкоуровневый клиент isk-daemon.
 * Тут логика: запросов к приложению.
 */
namespace App\Services;

use Exception;
use PhpXmlRpc\Client;
use PhpXmlRpc\Request;
use PhpXmlRpc\Value;
use PhpXmlRpc\Encoder;

final class XmlRpcTransport
{
    private Client $client;
    private Encoder $encoder;

    public function __construct(string $host, int $port, string $path, int $timeoutSec = 20)
    {
        $this->client = new Client($path, $host, $port);
        $this->client->timeout = $timeoutSec;
        $this->encoder = new Encoder();
    }

    public function call(string $methodName, array $params = [])
    {
        $rpcParams = array_map([$this, 'toValue'], $params);
        $req = new Request($methodName, $rpcParams);

        $resp = $this->client->send($req);

        if (!$resp) {
            throw new Exception("Нет ответа от daemon (timeout/сеть).");
        }

        if ($resp->faultCode()) {
            throw new Exception("XML-RPC fault: " . $resp->faultCode() . " | " . $resp->faultString());
        }

        return $this->encoder->decode($resp->value());
    }

    private function toValue($v): Value
    {
        if (is_int($v)) return new Value($v, 'int');
        if (is_bool($v)) return new Value($v ? 1 : 0, 'boolean');
        if (is_float($v)) return new Value($v, 'double');
        if (is_string($v)) return new Value($v, 'string');

        if (is_array($v)) {
            $isAssoc = array_keys($v) !== range(0, count($v) - 1);

            if ($isAssoc) {
                $struct = [];
                foreach ($v as $k => $val) $struct[(string)$k] = $this->toValue($val);
                return new Value($struct, 'struct');
            }

            $arr = [];
            foreach ($v as $val) $arr[] = $this->toValue($val);
            return new Value($arr, 'array');
        }

        return new Value((string)$v, 'string');
    }
}
