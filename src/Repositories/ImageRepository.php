<?php
// Для запросов к базе данных

namespace App\Repositories;

use App\Support\Db;
use PDO;

final class ImageRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Db::pdo();
    }

    public function insert(array $row): int
    {
        $sql = "INSERT INTO images (filename, mime, width, height, host_path, container_path, relative_path)
                VALUES (:filename, :mime, :width, :height, :host_path, :container_path, :relative_path)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':filename' => $row['filename'],
            ':mime' => $row['mime'] ?? 'image/jpeg',
            ':width' => $row['width'] ?? null,
            ':height' => $row['height'] ?? null,
            ':host_path' => $row['hostPath'],
            ':container_path' => $row['containerPath'],
            ':relative_path' => $row['relativePath'],
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function find(int $id): ?array
    {
        $st = $this->db->prepare("SELECT * FROM images WHERE id = :id");
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function findManyByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) return [];

        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->db->prepare("SELECT * FROM images WHERE id IN ($in)");
        $st->execute($ids);

        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[(int)$row['id']] = $row;
        }
        return $out;
    }

    public function random(int $limit = 1): array
    {
        $limit = max(1, min(50, $limit));
        // Для небольших таблиц нормально:
        $st = $this->db->query("SELECT * FROM images ORDER BY RAND() LIMIT " . (int)$limit);
        return $st->fetchAll();
    }

    public function delete(int $id): void
    {
        $st = $this->db->prepare("DELETE FROM images WHERE id = :id");
        $st->execute([':id' => $id]);
    }

    public function truncate(): void
    {
        $this->db->exec("TRUNCATE TABLE images");
    }
}
