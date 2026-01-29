<?php

namespace App\Kernel\Http;

/**
 * HTTP Request объект.
 */
final class Request
{
    public readonly string $method;
    public readonly string $path;
    /** @var array<string, mixed> */
    public readonly array $query;
    /** @var array<string, mixed> */
    public readonly array $post;
    /** @var array<string, UploadedFile> */
    public readonly array $files;
    /** @var array<string, string> */
    public readonly array $headers;
    public readonly string $rawBody;
    /** @var array<string, mixed> */
    public readonly array $server;

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, UploadedFile> $files
     * @param array<string, string> $headers
     * @param array<string, mixed> $server
     */
    public function __construct(
        string $method,
        string $path,
        array $query,
        array $post,
        array $files,
        array $headers,
        string $rawBody,
        array $server
    ) {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->query = $query;
        $this->post = $post;
        $this->files = $files;
        $this->headers = $headers;
        $this->rawBody = $rawBody;
        $this->server = $server;
    }

    public static function createFromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        $path = rtrim($path, '/') ?: '/';

        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (!is_string($v)) continue;
            if (str_starts_with($k, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($k, 5)));
                $headers[$name] = $v;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE']) && is_string($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }

        $files = [];
        foreach ($_FILES ?? [] as $field => $f) {
            if (is_array($f) && isset($f['name'])) {
                $files[$field] = UploadedFile::fromFilesArray($field, $f);
            }
        }

        $raw = file_get_contents('php://input');
        if ($raw === false) $raw = '';

        return new self(
            (string)$method,
            (string)$path,
            $_GET ?? [],
            $_POST ?? [],
            $files,
            $headers,
            $raw,
            $_SERVER ?? []
        );
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $key = strtolower($name);
        return $this->headers[$key] ?? $default;
    }

    public function queryInt(string $key, int $default = 0, ?int $min = null, ?int $max = null): int
    {
        $v = $this->query[$key] ?? null;
        if ($v === null || $v === '') return $default;
        $i = (int)$v;
        if ($min !== null && $i < $min) return $min;
        if ($max !== null && $i > $max) return $max;
        return $i;
    }

    public function queryString(string $key, ?string $default = null, int $maxLen = 2000): ?string
    {
        $v = $this->query[$key] ?? null;
        if ($v === null) return $default;
        $s = (string)$v;
        if (mb_strlen($s) > $maxLen) {
            $s = mb_substr($s, 0, $maxLen);
        }
        return $s;
    }

    public function file(string $field): ?UploadedFile
    {
        return $this->files[$field] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        $ct = $this->header('content-type', '') ?? '';
        if (!str_contains($ct, 'application/json')) return [];
        $data = json_decode($this->rawBody, true);
        return is_array($data) ? $data : [];
    }
}
