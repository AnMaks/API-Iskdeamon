# API-Iskdeamon
# isk-daemon REST API (PHP) + MySQL (метаданные) + Docker (isk-daemon)

> Важно: сборке `isk-daemon` корректно обрабатывает пути из папки `upload/`.
> Поэтому для индексации мы кладём изображения в `\iskdaemon_data\upload\...`
> и передаём в daemon путь `upload/filename.jpg` или абсолютный путь внутри контейнера.

---

## 1) Требования

### Обязательное
- Docker Desktop (запущен)
- PHP 8.x (или 7.4+)
- Composer
- MySQL (удобно через **Laragon** или **XAMPP**)

---

## 2) Подготовка папок для volume (Windows)

Создай папки в проекте:

```text
\iskdaemon_data\
\iskdaemon_data\upload\
\iskdaemon_data\thumbs\
```

> `upload/` — основная папка: оттуда daemon читает файлы для индексации.

---

## 3) Запуск isk-daemon в Docker (PowerShell)

Запуск контейнера:

```powershell
docker run -d --name iskdaemon -p 31128:31128 -v абсолютный путь\iskdaemon_data:/opt/iskdaemon/src/src/data iskdaemon:16
```

Проверка:

```powershell
docker ps
docker logs -f iskdaemon
```

---

## 4) MySQL (Laragon / XAMPP)

### Laragon 
1. Установи Laragon
2. Нажми **Start All**
3. Убедись что порт 3306 слушается:


### XAMPP
1. Установи XAMPP
2. В XAMPP Control Panel включи **MySQL**

---

## 5) Создание базы и таблиц

Открыть HeidiSQL/phpMyAdmin или MySQL консоль и выполнить:

```sql
CREATE DATABASE IF NOT EXISTS isk_api
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE isk_api;

CREATE TABLE IF NOT EXISTS images (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  filename VARCHAR(255) NOT NULL,
  mime VARCHAR(80) NOT NULL DEFAULT 'image/jpeg',
  width INT UNSIGNED NULL,
  height INT UNSIGNED NULL,

  host_path VARCHAR(1024) NOT NULL,
  container_path VARCHAR(1024) NOT NULL,
  relative_path VARCHAR(1024) NOT NULL,

  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX (created_at)
) ENGINE=InnoDB;

--  id как в тестах (200+)
ALTER TABLE images AUTO_INCREMENT = 200;
```

---

## 6) Установка зависимостей Composer

В корне проекта:

```terminal
composer install
```

Поставить XML-RPC библиотеку:

```terminal
composer require gggeek/phpxmlrpc
```

> Если `composer` не распознаётся — Composer не установлен или не добавлен в PATH.

---

## 7) Настройка `config.php`

Добавить информацию о настройках MySQL

```powershell
define('DB_DSN',  'mysql:host=127.0.0.1;port=3306;dbname=isk_api;charset=utf8mb4');
define('DB_USER', 'root');
define('DB_PASS', '');
```

---

## 8) Запуск API

В корне проекта:

```powershell
cd "Корень проекта"
php -S 127.0.0.1:8080 -t public
```

Открыть:
- `http://127.0.0.1:8080/api/health`
- тестовая страница: `http://127.0.0.1:8080/`

---

## 9) Основные эндпоинты API

### 9.1 Проверяет связь с `isk-daemon` и готовность db space.
`GET /api/health`


---

### 9.2 Создаёт db space в `isk-daemon`, если он ещё не создан.
`POST /api/init`


---

### 9.3 Создаёт db space в `isk-daemon`, если он ещё не создан.
`POST /api/reset`


---

### 9.4 Добавить изображение в индекс (навсегда)
`POST /api/images`


---

### 9.5 Поиск похожих по загруженной картинке (временно)
`POST /api/search?count=10`


---

### 9.6 Поиск похожих по ID
`GET /api/images/{id}/matches?count=10`

---

### 9.7 Получить файл по ID (для отображения)
`GET /api/images/{id}/file`

---

### 9.8 Удалить изображение по ID
`DELETE /api/images/{id}`


---

## 10) Порядок запуска (коротко)

1) Docker Desktop запущен
2) `docker run ...` (iskdaemon)
3) Запусти MySQL (Laragon/XAMPP)
4) Создай `isk_api` и таблицу `images`
5) `composer install`
6) `php -S 127.0.0.1:8080 -t public`
7) Открой `/api/health`
8) Добавь картинку через `/api/images`
9) Поиск через `/api/search` или `/api/images/{id}/matches`  
