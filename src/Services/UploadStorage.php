<?php
// Этот класс нужен, чтобы сохранять загруженные картинки на сервер.
// Мы берём файл из $_FILES, проверяем что это картинка и кладём его в папку upload.
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

        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION) ?: '');

        if (!in_array($ext, ['jpg', 'jpeg'], true)) {
            throw new Exception("Загрузи только JPG/JPEG. Сейчас: .$ext");
        }

        $hostDir = rtrim(HOST_SHARED_DIR, '/\\') . '/' . $subdir;
        if (!is_dir($hostDir) && !mkdir($hostDir, 0777, true)) {
            throw new Exception("Не смог создать папку: $hostDir");
        }

        $base = 'img_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
        $filename = $base . '.jpg';

        $hostPath = $hostDir . '/' . $filename;

        if (!move_uploaded_file($f['tmp_name'], $hostPath)) {
            throw new Exception("Не удалось сохранить файл");
        }

        // размеры (если GD нет — оставим null)
        $w = $h = null;
        $info = @getimagesize($hostPath);
        if ($info && isset($info[0], $info[1])) {
            $w = (int)$info[0];
            $h = (int)$info[1];
        }

        $containerPath = rtrim(CONTAINER_SHARED_DIR, '/') . '/' . $subdir . '/' . $filename;

        return [
            'filename' => $filename,
            'mime' => 'image/jpeg',
            'width' => $w,
            'height' => $h,
            'hostPath' => $hostPath,
            'containerPath' => $containerPath,
            'relativePath' => $subdir . '/' . $filename,
        ];
    }
}
