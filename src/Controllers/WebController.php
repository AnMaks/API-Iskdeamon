<?php
// Открытие тестовой страницы

namespace App\Controllers;

use App\Support\Response;

final class WebController
{
    public function test(): void
    {
        $file = __DIR__ . '/../../public/test.html';

        if (!is_file($file)) {
            Response::fail("Файл test.html не найден в public/", 404);
        }

        header('Content-Type: text/html; charset=utf-8');
        readfile($file);
        exit;
    }
}
