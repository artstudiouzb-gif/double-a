<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Project
{
    public static function all(): array
    {
        $stmt = Database::pdo()->query('SELECT * FROM projects ORDER BY sort_order ASC, created_at DESC');

        return $stmt->fetchAll();
    }

    public static function published(): array
    {
        $stmt = Database::pdo()->query(
            "SELECT * FROM projects WHERE status = 'published' ORDER BY sort_order ASC, created_at DESC"
        );

        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM projects WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function findPublishedBySlug(string $slug): ?array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM projects WHERE slug = :slug AND status = 'published' LIMIT 1"
        );
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM projects WHERE slug = :slug';
        $params = [':slug' => $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id != :id';
            $params[':id'] = $excludeId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO projects (title, slug, description, cover_image, status, sort_order, created_at)
             VALUES (:title, :slug, :description, :cover_image, :status, :sort_order, NOW())'
        );
        $stmt->execute([
            ':title' => $data['title'],
            ':slug' => $data['slug'],
            ':description' => $data['description'],
            ':cover_image' => $data['cover_image'],
            ':status' => $data['status'],
            ':sort_order' => $data['sort_order'] ?? 0,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE projects SET title = :title, slug = :slug, description = :description,
             cover_image = :cover_image, status = :status, sort_order = :sort_order WHERE id = :id'
        );
        $stmt->execute([
            ':title' => $data['title'],
            ':slug' => $data['slug'],
            ':description' => $data['description'],
            ':cover_image' => $data['cover_image'],
            ':status' => $data['status'],
            ':sort_order' => $data['sort_order'] ?? 0,
            ':id' => $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM projects WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}
