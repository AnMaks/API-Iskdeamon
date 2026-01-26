<?php
// Этот класс нужен, чтобы сохранять загруженные картинки на сервер.
// Мы берём файл из $_FILES, проверяем что это JPG и кладём его в папку upload.
// Потом возвращаем пути к файлу.

namespace App\Services;
class UploadStorage
{
    public static function saveUploadedImage(string $fieldName = 'image'): array
    {
        if (!isset($_FILES[$fieldName])) {
            throw new Exception("Нет файла в поле '$fieldName'");
        }

        $f = $_FILES[$fieldName];

        if ($f['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Ошибка upload, code=" . $f['error']);
        }

        $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
        $ext = $ext ? strtolower($ext) : '';

        //Только JPG/JPEG
        if (!in_array($ext, ['jpg', 'jpeg'], true)) {
            throw new Exception("Загрузи только JPG/JPEG. Сейчас: .$ext");
        }

        $uploadDirHost = rtrim(HOST_SHARED_DIR, '/\\') . '/' . UPLOAD_SUBDIR;

        if (!is_dir($uploadDirHost)) {
            if (!mkdir($uploadDirHost, 0777, true)) {
                throw new Exception("Не смог создать папку: $uploadDirHost");
            }
        }

        $base = 'img_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
        $filename = $base . '_full.jpg';

        $hostPath = $uploadDirHost . '/' . $filename;

        if (!move_uploaded_file($f['tmp_name'], $hostPath)) {
            throw new Exception("Не удалось сохранить файл");
        }

        $containerPath = rtrim(CONTAINER_SHARED_DIR, '/') . '/' . UPLOAD_SUBDIR . '/' . $filename;

        return [
            'filename' => $filename,
            'hostPath' => $hostPath,
            'containerPath' => $containerPath,
            'relativePath' => UPLOAD_SUBDIR . '/' . $filename,
        ];
    }
}
