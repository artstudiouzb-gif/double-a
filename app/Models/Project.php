<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Project
{
    public static function all(): array
    {
        $stmt = Database::pdo()->query('SELECT * FROM projects WHERE deleted_at IS NULL ORDER BY sort_order ASC, created_at DESC');

        return $stmt->fetchAll();
    }

    public static function trashed(): array
    {
        $stmt = Database::pdo()->query('SELECT * FROM projects WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC');

        return $stmt->fetchAll();
    }

    /** Список с фильтром по статусу (задача 91). */
    public static function filter(?string $status = null, ?string $lang = null): array
    {
        $sql = 'SELECT * FROM projects WHERE deleted_at IS NULL';
        $params = [];
        if ($status === 'published' || $status === 'draft') {
            $sql .= ' AND status = :status';
            $params[':status'] = $status;
        }
        $sql .= ' ORDER BY sort_order ASC, created_at DESC';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function setStatus(int $id, string $status): void
    {
        if (!in_array($status, ['draft', 'published'], true)) {
            return;
        }
        $stmt = Database::pdo()->prepare('UPDATE projects SET status = :s WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute([':s' => $status, ':id' => $id]);
    }

    /** Полная копия проекта с изображениями и полями (черновик, slug -copy). */
    public static function duplicate(int $id): ?int
    {
        $project = self::findById($id);
        if (!$project) {
            return null;
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $newSlug = \App\Core\Duplicator::uniqueCopySlug(
                (string) $project['slug'],
                static fn (string $s) => self::slugExists($s)
            );
            $newId = \App\Core\Duplicator::copyRow('projects', $project, [
                'slug' => $newSlug,
                'status' => 'draft',
                'deleted_at' => null,
            ]);
            \App\Core\Duplicator::copyChildren('project_images', 'project_id', $id, $newId);
            \App\Core\Duplicator::copyChildren('project_fields', 'project_id', $id, $newId);

            $pdo->commit();

            return $newId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function restore(int $id): void
    {
        $stmt = Database::pdo()->prepare('UPDATE projects SET deleted_at = NULL WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public static function forceDelete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM projects WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public static function published(): array
    {
        $stmt = Database::pdo()->query(
            "SELECT * FROM projects WHERE status = 'published' AND deleted_at IS NULL ORDER BY sort_order ASC, created_at DESC"
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
            "SELECT * FROM projects WHERE slug = :slug AND status = 'published' AND deleted_at IS NULL LIMIT 1"
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
        // Мягкое удаление: проект отправляется в корзину.
        $stmt = Database::pdo()->prepare('UPDATE projects SET deleted_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}
