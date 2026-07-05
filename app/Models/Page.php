<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Page
{
    public static function all(): array
    {
        $stmt = Database::pdo()->query('SELECT * FROM pages WHERE deleted_at IS NULL ORDER BY created_at DESC');

        return $stmt->fetchAll();
    }

    /**
     * Список с фильтрами админки (задача 91). deleted_at IS NULL всегда.
     * $lang (не-дефолтный) ограничивает страницами, имеющими перевод.
     */
    public static function filter(?string $status = null, ?string $lang = null): array
    {
        $sql = 'SELECT p.* FROM pages p';
        $params = [];
        if ($lang !== null && $lang !== '' && $lang !== Language::defaultCode()) {
            $sql .= ' INNER JOIN page_translations pt ON pt.page_id = p.id AND pt.lang = :lang';
            $params[':lang'] = $lang;
        }
        $sql .= ' WHERE p.deleted_at IS NULL';
        if ($status === 'published' || $status === 'draft') {
            $sql .= ' AND p.status = :status';
            $params[':status'] = $status;
        }
        $sql .= ' ORDER BY p.created_at DESC';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function setStatus(int $id, string $status): void
    {
        if (!in_array($status, ['draft', 'published'], true)) {
            return;
        }
        $stmt = Database::pdo()->prepare('UPDATE pages SET status = :s WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute([':s' => $status, ':id' => $id]);
    }

    /** Полная копия страницы с блоками и переводами (черновик, slug -copy). */
    public static function duplicate(int $id): ?int
    {
        $page = self::findById($id);
        if (!$page) {
            return null;
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $newSlug = \App\Core\Duplicator::uniqueCopySlug(
                (string) $page['slug'],
                static fn (string $s) => self::slugExists($s)
            );
            $newId = \App\Core\Duplicator::copyRow('pages', $page, [
                'slug' => $newSlug,
                'status' => 'draft',
                'is_home' => 0,
                'deleted_at' => null,
            ]);
            \App\Core\Duplicator::copyChildren('blocks', 'page_id', $id, $newId);
            \App\Core\Duplicator::copyChildren('page_translations', 'page_id', $id, $newId);

            $pdo->commit();

            return $newId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function trashed(): array
    {
        $stmt = Database::pdo()->query('SELECT * FROM pages WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC');

        return $stmt->fetchAll();
    }

    public static function restore(int $id): void
    {
        $stmt = Database::pdo()->prepare('UPDATE pages SET deleted_at = NULL WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public static function forceDelete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM pages WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public static function findBySlug(string $slug, ?string $lang = null): ?array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM pages WHERE slug = :slug AND status = 'published' AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return $lang !== null ? self::localize($row, $lang) : $row;
    }

    public static function findHome(?string $lang = null): ?array
    {
        $stmt = Database::pdo()->query(
            "SELECT * FROM pages WHERE is_home = 1 AND status = 'published' AND deleted_at IS NULL LIMIT 1"
        );
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return $lang !== null ? self::localize($row, $lang) : $row;
    }

    /**
     * Накладывает перевод (title/meta) на базовую строку страницы.
     */
    public static function localize(array $row, string $lang): array
    {
        if ($lang === Language::defaultCode()) {
            return $row;
        }

        $translation = PageTranslation::find((int) $row['id'], $lang);
        if ($translation === null) {
            return $row;
        }

        if (isset($translation['title']) && trim((string) $translation['title']) !== '') {
            $row['title'] = $translation['title'];
        }
        $row['meta_title'] = $translation['meta_title'] ?? null;
        $row['meta_description'] = $translation['meta_description'] ?? null;

        return $row;
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
                'INSERT INTO pages (title, slug, meta_title, meta_description, status, is_home, layout_type, created_at)
                 VALUES (:title, :slug, :meta_title, :meta_description, :status, :is_home, :layout_type, NOW())'
            );
            $stmt->execute([
                ':title' => $data['title'],
                ':slug' => $data['slug'],
                ':meta_title' => $data['meta_title'],
                ':meta_description' => $data['meta_description'],
                ':status' => $data['status'],
                ':is_home' => !empty($data['is_home']) ? 1 : 0,
                ':layout_type' => $data['layout_type'] ?? 'no_sidebar',
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
                 meta_description = :meta_description, status = :status, is_home = :is_home,
                 layout_type = :layout_type WHERE id = :id'
            );
            $stmt->execute([
                ':title' => $data['title'],
                ':slug' => $data['slug'],
                ':meta_title' => $data['meta_title'],
                ':meta_description' => $data['meta_description'],
                ':status' => $data['status'],
                ':is_home' => !empty($data['is_home']) ? 1 : 0,
                ':layout_type' => $data['layout_type'] ?? 'no_sidebar',
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
        // Мягкое удаление: страница отправляется в корзину (блоки сохраняются).
        $stmt = Database::pdo()->prepare('UPDATE pages SET deleted_at = NOW(), is_home = 0 WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}
