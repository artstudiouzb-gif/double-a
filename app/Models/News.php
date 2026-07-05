<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Video;

final class News
{
    public const LAYOUTS = ['standard', 'gallery', 'video', 'side_image'];

    public static function normalizeLayout(mixed $layout): string
    {
        $layout = is_string($layout) ? $layout : 'standard';
        return in_array($layout, self::LAYOUTS, true) ? $layout : 'standard';
    }

    /**
     * Централизованный выбор обложки новости (задача 68). Приоритет:
     *   1) явно заданное изображение (news.image),
     *   2) обложка YouTube-видео (news.video_url),
     *   3) первое фото из галереи (news_images),
     *   4) логотип сайта (settings.logo_url).
     * Возвращает URL или null, если ничего нет.
     */
    public static function getCoverImage(array $row): ?string
    {
        $image = trim((string) ($row['image'] ?? ''));
        if ($image !== '') {
            return $image;
        }

        $ytId = Video::youtubeId($row['video_url'] ?? null);
        if ($ytId !== null) {
            return Video::youtubeThumbnail($ytId);
        }

        if (!empty($row['id'])) {
            $galleryPath = NewsImage::firstPath((int) $row['id']);
            if ($galleryPath !== null) {
                return $galleryPath;
            }
        }

        $logo = trim((string) Setting::get('logo_url', ''));
        return $logo !== '' ? $logo : null;
    }
    public static function all(): array
    {
        $stmt = Database::pdo()->query('SELECT * FROM news WHERE deleted_at IS NULL ORDER BY created_at DESC');

        return $stmt->fetchAll();
    }

    public static function trashed(): array
    {
        $stmt = Database::pdo()->query('SELECT * FROM news WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC');

        return $stmt->fetchAll();
    }

    /** Список с фильтрами админки (задача 91). */
    public static function filter(?string $status = null, ?string $lang = null): array
    {
        $sql = 'SELECT n.* FROM news n';
        $params = [];
        if ($lang !== null && $lang !== '' && $lang !== Language::defaultCode()) {
            $sql .= ' INNER JOIN news_translations nt ON nt.news_id = n.id AND nt.lang = :lang';
            $params[':lang'] = $lang;
        }
        $sql .= ' WHERE n.deleted_at IS NULL';
        if ($status === 'published' || $status === 'draft') {
            $sql .= ' AND n.status = :status';
            $params[':status'] = $status;
        }
        $sql .= ' ORDER BY n.created_at DESC';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function setStatus(int $id, string $status): void
    {
        if (!in_array($status, ['draft', 'published'], true)) {
            return;
        }
        $stmt = Database::pdo()->prepare('UPDATE news SET status = :s WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute([':s' => $status, ':id' => $id]);
    }

    /** Полная копия новости с переводами и галереей (черновик, slug -copy). */
    public static function duplicate(int $id): ?int
    {
        $news = self::findById($id);
        if (!$news) {
            return null;
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $newSlug = \App\Core\Duplicator::uniqueCopySlug(
                (string) $news['slug'],
                static fn (string $s) => self::slugExists($s)
            );
            $newId = \App\Core\Duplicator::copyRow('news', $news, [
                'slug' => $newSlug,
                'status' => 'draft',
                'deleted_at' => null,
            ]);
            \App\Core\Duplicator::copyChildren('news_translations', 'news_id', $id, $newId);
            \App\Core\Duplicator::copyChildren('news_images', 'news_id', $id, $newId);

            $pdo->commit();

            return $newId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function restore(int $id): void
    {
        $stmt = Database::pdo()->prepare('UPDATE news SET deleted_at = NULL WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public static function forceDelete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM news WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /**
     * Опубликованные новости, локализованные под указанный язык.
     */
    public static function published(int $limit = 20, int $offset = 0, ?string $lang = null): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM news WHERE status = 'published' AND published_at <= NOW() AND deleted_at IS NULL
             ORDER BY published_at DESC LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        if ($lang === null || $lang === Language::defaultCode()) {
            return $rows;
        }

        return array_map(static fn (array $row) => self::localize($row, $lang), $rows);
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM news WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Ищет опубликованную новость по слагу и локализует под язык.
     */
    public static function findPublishedBySlug(string $slug, ?string $lang = null): ?array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM news WHERE slug = :slug AND status = 'published' AND published_at <= NOW() AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        if ($lang === null || $lang === Language::defaultCode()) {
            return $row;
        }

        return self::localize($row, $lang);
    }

    /**
     * Накладывает перевод указанного языка на базовую строку. Пустые поля
     * перевода откатываются к значению языка по умолчанию (graceful fallback).
     */
    public static function localize(array $row, string $lang): array
    {
        $translation = NewsTranslation::find((int) $row['id'], $lang);
        if ($translation === null) {
            return $row;
        }

        foreach (['title', 'excerpt', 'content'] as $field) {
            if (isset($translation[$field]) && trim((string) $translation[$field]) !== '') {
                $row[$field] = $translation[$field];
            }
        }
        $row['meta_title'] = $translation['meta_title'] ?? null;
        $row['meta_description'] = $translation['meta_description'] ?? null;

        return $row;
    }

    public static function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM news WHERE slug = :slug';
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
            'INSERT INTO news (title, slug, excerpt, content, image, video_url, layout_type, focal_x, focal_y, meta_title, meta_description, status, published_at, author_id, created_at)
             VALUES (:title, :slug, :excerpt, :content, :image, :video_url, :layout_type, :focal_x, :focal_y, :meta_title, :meta_description, :status, :published_at, :author_id, NOW())'
        );
        $stmt->execute([
            ':title' => $data['title'],
            ':slug' => $data['slug'],
            ':excerpt' => $data['excerpt'],
            ':content' => $data['content'],
            ':image' => $data['image'],
            ':video_url' => $data['video_url'] ?? null,
            ':layout_type' => self::normalizeLayout($data['layout_type'] ?? 'standard'),
            ':focal_x' => $data['focal_x'] ?? null,
            ':focal_y' => $data['focal_y'] ?? null,
            ':meta_title' => $data['meta_title'] ?? null,
            ':meta_description' => $data['meta_description'] ?? null,
            ':status' => $data['status'],
            ':published_at' => $data['published_at'],
            ':author_id' => $data['author_id'],
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE news SET title = :title, slug = :slug, excerpt = :excerpt, content = :content,
             image = :image, video_url = :video_url, layout_type = :layout_type,
             focal_x = :focal_x, focal_y = :focal_y,
             meta_title = :meta_title, meta_description = :meta_description,
             status = :status, published_at = :published_at WHERE id = :id'
        );
        $stmt->execute([
            ':title' => $data['title'],
            ':slug' => $data['slug'],
            ':excerpt' => $data['excerpt'],
            ':content' => $data['content'],
            ':image' => $data['image'],
            ':video_url' => $data['video_url'] ?? null,
            ':layout_type' => self::normalizeLayout($data['layout_type'] ?? 'standard'),
            ':focal_x' => $data['focal_x'] ?? null,
            ':focal_y' => $data['focal_y'] ?? null,
            ':meta_title' => $data['meta_title'] ?? null,
            ':meta_description' => $data['meta_description'] ?? null,
            ':status' => $data['status'],
            ':published_at' => $data['published_at'],
            ':id' => $id,
        ]);
    }

    public static function delete(int $id): void
    {
        // Мягкое удаление: запись отправляется в корзину.
        $stmt = Database::pdo()->prepare('UPDATE news SET deleted_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}
