<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\ConcurrencyException;
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

    public static function adminList(array $filters): array
    {
        [$where, $params] = self::adminListWhere($filters);
        $orders = [
            'manual' => 'sort_order ASC, created_at DESC, id DESC',
            'newest' => 'created_at DESC, id DESC',
            'oldest' => 'created_at ASC, id ASC',
            'title_asc' => 'title ASC, id ASC',
            'title_desc' => 'title DESC, id DESC',
        ];
        $order = $orders[$filters['sort'] ?? 'manual'] ?? $orders['manual'];
        $stmt = Database::pdo()->prepare("SELECT * FROM projects {$where} ORDER BY {$order} LIMIT :limit OFFSET :offset");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', (int) $filters['per_page'], \PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $filters['offset'], \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function adminCount(array $filters): int
    {
        [$where, $params] = self::adminListWhere($filters);
        $stmt = Database::pdo()->prepare("SELECT COUNT(*) FROM projects {$where}");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** @return array{0:string,1:array<string,string>} */
    private static function adminListWhere(array $filters): array
    {
        $where = 'WHERE deleted_at IS NULL';
        $params = [];
        if (in_array($filters['status'] ?? '', ['published', 'draft'], true)) {
            $where .= ' AND status = :status';
            $params[':status'] = (string) $filters['status'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where .= ' AND (title LIKE :q_title OR slug LIKE :q_slug OR description LIKE :q_description)';
            $like = '%' . (string) $filters['q'] . '%';
            $params[':q_title'] = $like;
            $params[':q_slug'] = $like;
            $params[':q_description'] = $like;
        }

        return [$where, $params];
    }

    public static function setStatus(int $id, string $status): void
    {
        if (!in_array($status, ['draft', 'published'], true)) {
            return;
        }
        $stmt = Database::pdo()->prepare('UPDATE projects SET status = :s WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute([':s' => $status, ':id' => $id]);
        self::bustPageCache();
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
        self::bustPageCache();
    }

    public static function forceDelete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM projects WHERE id = :id');
        $stmt->execute([':id' => $id]);
        ContentRevision::deleteForEntity('project', $id);
        self::bustPageCache();
    }

    public static function published(): array
    {
        $stmt = Database::pdo()->query(
            "SELECT * FROM projects WHERE status = 'published' AND deleted_at IS NULL ORDER BY sort_order ASC, created_at DESC"
        );

        return $stmt->fetchAll();
    }

    /**
     * Проекты для блока главной: только помеченные «показать на главном».
     * Если ни один не отмечен — откат на последние опубликованные (чтобы блок
     * не был пустым при первом включении источника).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forHome(int $limit = 6): array
    {
        $limit = max(1, min(24, $limit));
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM projects WHERE status = 'published' AND deleted_at IS NULL AND is_featured = 1
             ORDER BY sort_order ASC, created_at DESC LIMIT {$limit}"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll();
        if (!empty($rows)) {
            return $rows;
        }
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM projects WHERE status = 'published' AND deleted_at IS NULL
             ORDER BY sort_order ASC, created_at DESC LIMIT {$limit}"
        );
        $stmt->execute();

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
            'INSERT INTO projects (title, slug, description, cover_image, status, is_featured, sort_order, created_at)
             VALUES (:title, :slug, :description, :cover_image, :status, :is_featured, :sort_order, NOW())'
        );
        $stmt->execute([
            ':title' => $data['title'],
            ':slug' => $data['slug'],
            ':description' => $data['description'],
            ':cover_image' => $data['cover_image'],
            ':status' => $data['status'],
            ':is_featured' => !empty($data['is_featured']) ? 1 : 0,
            ':sort_order' => $data['sort_order'] ?? 0,
        ]);

        // ВАЖНО: id читаем ДО сброса кэша — bustPageCache() выполняет запрос к
        // settings (проверка CDN/Cloudflare), который обнуляет lastInsertId().
        $id = (int) Database::pdo()->lastInsertId();
        self::bustPageCache();

        return $id;
    }

    public static function update(int $id, array $data, ?int $expectedLockVersion = null): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE projects SET title = :title, slug = :slug, description = :description,
             cover_image = :cover_image, status = :status, is_featured = :is_featured,
             sort_order = :sort_order, lock_version = lock_version + 1
             WHERE id = :id' . ($expectedLockVersion !== null ? ' AND lock_version = :expected_lock_version' : '')
        );
        $params = [
            ':title' => $data['title'],
            ':slug' => $data['slug'],
            ':description' => $data['description'],
            ':cover_image' => $data['cover_image'],
            ':status' => $data['status'],
            ':is_featured' => !empty($data['is_featured']) ? 1 : 0,
            ':sort_order' => $data['sort_order'] ?? 0,
            ':id' => $id,
        ];
        if ($expectedLockVersion !== null) {
            $params[':expected_lock_version'] = $expectedLockVersion;
        }
        $stmt->execute($params);
        if ($expectedLockVersion !== null && $stmt->rowCount() !== 1) {
            throw new ConcurrencyException('Проект был изменён другим пользователем.');
        }
        self::bustPageCache();
    }

    public static function delete(int $id): void
    {
        // Мягкое удаление: проект отправляется в корзину.
        $stmt = Database::pdo()->prepare('UPDATE projects SET deleted_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $id]);
        self::bustPageCache();
    }

    private static function bustPageCache(): void
    {
        \App\Core\Cache::forgetPrefix('page:');
    }
}
