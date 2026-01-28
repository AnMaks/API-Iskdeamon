<?php
// Этот класс нужен, чтобы сохранять загруженные картинки на сервер.
// Мы берём файл из $_FILES, проверяем что это картинка и кладём его в нужную папку.
// Потом возвращаем пути к файлу.

namespace App\Services;

use Exception;

final class UploadStorage
{
    public static function saveUploadedImage(string $fieldName, string $subdir): array
    {
        if (!isset($_FILES[$fieldName])) {
            throw new Exception("Нет файла в поле '$fieldName'");
        }

        $f = $_FILES[$fieldName];

        if ($f['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Ошибка upload, code=" . $f['error']);
        }

        // 1) расширение для имени файла
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION) ?: '');

        // 2) разрешённые форматы
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (!in_array($ext, $allowedExt, true)) {
            throw new Exception("Картинки: " . implode(', ', $allowedExt) . ". Сейчас: .$ext");
        }

        // 3) папка на хосте
        $hostDir = rtrim(HOST_SHARED_DIR, '/\\') . '/' . $subdir;
        if (!is_dir($hostDir) && !mkdir($hostDir, 0777, true)) {
            throw new Exception("Не смог создать папку: $hostDir");
        }

        // 4) сохраняем 
        $base = 'img_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
        $filename = $base . '.' . $ext;

        $hostPath = $hostDir . '/' . $filename;

        if (!move_uploaded_file($f['tmp_name'], $hostPath)) {
            throw new Exception("Не удалось сохранить файл");
        }

        $mime = 'application/octet-stream';
        $w = $h = null;

        $info = @getimagesize($hostPath);
        if ($info) {
            $w = isset($info[0]) ? (int)$info[0] : null;
            $h = isset($info[1]) ? (int)$info[1] : null;
            $mime = isset($info['mime']) ? (string)$info['mime'] : $mime;
        }

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
