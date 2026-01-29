<?php
declare(strict_types=1);

namespace App\Services;

use App\Kernel\Http\UploadedFile;
use Exception;

/**
 * Сохраняет загруженные картинки на диск (volume).
 * Валидирует размер/формат/размеры изображения.
 */
final class UploadStorage
{
    /**
     * @param array{
     *   maxBytes?: int,
     *   maxWidth?: int,
     *   maxHeight?: int,
     *   allowedExt?: string[],
     *   allowedMime?: string[],
     * } $rules
     * @return array{
     *   filename: string,
     *   mime: string,
     *   width: ?int,
     *   height: ?int,
     *   hostPath: string,
     *   containerPath: string,
     *   relativePath: string,
     *   ext: string
     * }
     */
    public static function saveUploadedImage(UploadedFile $file, string $subdir, array $rules = []): array
    {
        if (!$file->isOk()) {
            throw new Exception("Ошибка upload, code=" . $file->error);
        }

        $maxBytes  = (int)($rules['maxBytes']  ?? 10 * 1024 * 1024); // 10 MB
        $maxWidth  = (int)($rules['maxWidth']  ?? 8000);
        $maxHeight = (int)($rules['maxHeight'] ?? 8000);


        $allowedExt = $rules['allowedExt'] ?? ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $allowedMime = $rules['allowedMime'] ?? ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

        if ($file->size <= 0) {
            throw new Exception("Файл пустой");
        }
        if ($file->size > $maxBytes) {
            throw new Exception("Слишком большой файл. Макс: {$maxBytes} bytes");
        }

        // расширение для имени файла
        $ext = strtolower($file->extension());

        if (!in_array($ext, $allowedExt, true)) {
            throw new Exception("Разрешены: " . implode(', ', $allowedExt) . ". Сейчас: .$ext");
        }

        // Проверяем, что это реально изображение (ещё до move)
        $info = @getimagesize($file->tmpName);
        if (!$info) {
            throw new Exception("Файл не похож на изображение");
        }

        $w = isset($info[0]) ? (int)$info[0] : null;
        $h = isset($info[1]) ? (int)$info[1] : null;
        $mime = isset($info['mime']) ? (string)$info['mime'] : 'application/octet-stream';

        if (!in_array($mime, $allowedMime, true)) {
            throw new Exception("Недопустимый MIME: $mime");
        }
        if ($w !== null && $w > $maxWidth) {
            throw new Exception("Слишком большая ширина: $w (макс $maxWidth)");
        }
        if ($h !== null && $h > $maxHeight) {
            throw new Exception("Слишком большая высота: $h (макс $maxHeight)");
        }

        // папка на хосте
        $hostDir = rtrim(HOST_SHARED_DIR, '/\\') . '/' . $subdir;
        if (!is_dir($hostDir) && !mkdir($hostDir, 0777, true)) {
            throw new Exception("Не смог создать папку: $hostDir");
        }

        // сохраняем
        $base = 'img_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
        $filename = $base . '.' . $ext;

        $hostPath = $hostDir . '/' . $filename;

        $file->moveTo($hostPath);

        // container path (как видит docker)
        $containerPath = rtrim(CONTAINER_SHARED_DIR, '/') . '/' . $subdir . '/' . $filename;

        return [
            'filename' => $filename,
            'mime' => $mime,
            'width' => $w,
            'height' => $h,
            'hostPath' => $hostPath,
            'containerPath' => $containerPath,
            'relativePath' => $subdir . '/' . $filename,
            'ext' => $ext,
        ];
    }
}
