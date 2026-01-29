<?php

namespace App\Kernel\Http;

use RuntimeException;

final class UploadedFile
{
    public readonly string $field;
    public readonly string $originalName;
    public readonly string $tmpName;
    public readonly int $size;
    public readonly int $error;
    public readonly string $clientMime;

    public function __construct(
        string $field,
        string $originalName,
        string $tmpName,
        int $size,
        int $error,
        string $clientMime
    ) {
        $this->field = $field;
        $this->originalName = $originalName;
        $this->tmpName = $tmpName;
        $this->size = $size;
        $this->error = $error;
        $this->clientMime = $clientMime;
    }

    /**
     * @param array<string, mixed> $f
     */
    public static function fromFilesArray(string $field, array $f): self
    {
        return new self(
            $field,
            (string)($f['name'] ?? ''),
            (string)($f['tmp_name'] ?? ''),
            (int)($f['size'] ?? 0),
            (int)($f['error'] ?? UPLOAD_ERR_NO_FILE),
            (string)($f['type'] ?? '')
        );
    }

    public function isOk(): bool
    {
        return $this->error === UPLOAD_ERR_OK
            && $this->tmpName !== ''
            && is_uploaded_file($this->tmpName);
    }

    public function extension(): string
    {
        return  strtolower(pathinfo($this->originalName, PATHINFO_EXTENSION) ?: '');
    }

    public function moveTo(string $destinationPath): void
    {
        $dir = dirname($destinationPath);
        if (!is_dir($dir) && !mkdir($dir, 0777, true)) {
            throw new RuntimeException("Не возможно создать папку: $dir");
        }
        if (!move_uploaded_file($this->tmpName, $destinationPath)) {
            throw new RuntimeException("Не удалось сохранить файл");
        }
    }
}
