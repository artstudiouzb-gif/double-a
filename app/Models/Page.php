<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Page
{
    public static function all(): array
    {
        $stmt = Database::pdo()->query('SELECT * FROM pages ORDER BY created_at DESC');

        return $stmt->fetchAll();
    }

    public static function findBySlug(string $slug): ?array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM pages WHERE slug = :slug AND status = 'published' LIMIT 1"
        );
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function findHome(): ?array
    {
        $stmt = Database::pdo()->query(
            "SELECT * FROM pages WHERE is_home = 1 AND status = 'published' LIMIT 1"
        );
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM pages WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM pages WHERE slug = :slug';
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
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            if (!empty($data['is_home'])) {
                $pdo->exec('UPDATE pages SET is_home = 0');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO pages (title, slug, meta_title, meta_description, status, is_home, created_at)
                 VALUES (:title, :slug, :meta_title, :meta_description, :status, :is_home, NOW())'
            );
            $stmt->execute([
                ':title' => $data['title'],
                ':slug' => $data['slug'],
                ':meta_title' => $data['meta_title'],
                ':meta_description' => $data['meta_description'],
                ':status' => $data['status'],
                ':is_home' => !empty($data['is_home']) ? 1 : 0,
            ]);
            $id = (int) $pdo->lastInsertId();

            $pdo->commit();

            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function update(int $id, array $data): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            if (!empty($data['is_home'])) {
                $pdo->exec('UPDATE pages SET is_home = 0');
            }

            $stmt = $pdo->prepare(
                'UPDATE pages SET title = :title, slug = :slug, meta_title = :meta_title,
                 meta_description = :meta_description, status = :status, is_home = :is_home
                 WHERE id = :id'
            );
            $stmt->execute([
                ':title' => $data['title'],
                ':slug' => $data['slug'],
                ':meta_title' => $data['meta_title'],
                ':meta_description' => $data['meta_description'],
                ':status' => $data['status'],
                ':is_home' => !empty($data['is_home']) ? 1 : 0,
                ':id' => $id,
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM pages WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}
