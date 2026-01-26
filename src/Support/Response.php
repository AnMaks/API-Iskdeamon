<?php
// Этот файл отвечает за ответы API.
// Тут есть 2 функции:
// ok() — когда всё прошло успешно
// fail() — когда произошла ошибка

namespace App\Support;

final class Response
{
    public static function ok($data = [], int $code = 200): void
    {
        http_response_code($code);
        echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function fail(string $message, int $code = 400, $extra = null): void
    {
        http_response_code($code);
        echo json_encode(['ok' => false, 'error' => $message, 'extra' => $extra], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
