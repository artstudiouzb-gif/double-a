<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Slug;

/**
 * Видеозаписи: обложка + ссылка на видео (YouTube/внешнее). Блок «Медиа» на
 * главной собирает отмеченные (is_featured) автоматически.
 */
final class Video
{
    /** @return array<int, array<string, mixed>> */
    public static function all(bool $publishedOnly = false, ?string $lang = null): array
    {
        $sql = 'SELECT * FROM videos';
        if ($publishedOnly) {
            $sql .= ' WHERE is_published = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, created_at DESC, id DESC';

        $rows = Database::pdo()->query($sql)->fetchAll();

        if ($lang === null || $lang === Language::defaultCode()) {
            return $rows;
        }

        return self::localizeRows($rows, $lang);
    }

    /**
     * Накладывает перевод указанного языка на базовую строку. Пустые поля
     * перевода откатываются к значению основного языка (graceful fallback).
     */
    public static function localize(array $row, string $lang): array
    {
        return self::applyTranslation($row, VideoTranslation::find((int) $row['id'], $lang));
    }

    /** @param array<int, array<string, mixed>> $rows @return array<int, array<string, mixed>> */
    private static function localizeRows(array $rows, string $lang): array
    {
        $translations = VideoTranslation::forVideoIds(
            array_map(static fn (array $row): int => (int) $row['id'], $rows),
            $lang
        );
        return array_map(
            static fn (array $row): array => self::applyTranslation($row, $translations[(int) $row['id']] ?? null),
            $rows
        );
    }

    private static function applyTranslation(array $row, ?array $translation): array
    {
        if ($translation === null) {
            return $row;
        }
        foreach (['title', 'description'] as $field) {
            if (isset($translation[$field]) && trim((string) $translation[$field]) !== '') {
                $row[$field] = $translation[$field];
            }
        }

        return $row;
    }

    /**
     * Языки контента для набора видео одним запросом (без N+1).
     * Контент на языке = непустой перевод заголовка или описания.
     *
     * @param array<int|string> $ids
     * @return array<int, array<int, string>>
     */
    public static function availableLangsForIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $default = Language::defaultCode();
        $map = [];
        foreach ($ids as $id) {
            $map[$id] = [$default];
        }
        if ($ids === []) {
            return $map;
        }

        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::pdo()->prepare(
            "SELECT video_id, lang FROM video_translations
             WHERE video_id IN ($in)
               AND (TRIM(COALESCE(title, '')) <> '' OR TRIM(COALESCE(description, '')) <> '')"
        );
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() as $row) {
            $id = (int) $row['video_id'];
            $lang = (string) $row['lang'];
            if (isset($map[$id]) && !in_array($lang, $map[$id], true)) {
                $map[$id][] = $lang;
            }
        }

        return $map;
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM videos WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public static function create(string $title): ?int
    {
        $title = trim($title);
        if ($title === '') {
            return null;
        }
        $base = Slug::make($title) ?: 'video';
        $slug = $base;
        $n = 2;
        while (self::slugExists($slug)) {
            $slug = $base . '-' . $n++;
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO videos (title, slug, created_at) VALUES (:t, :s, NOW())'
        );
        $stmt->execute([':t' => mb_substr($title, 0, 255), ':s' => mb_substr($slug, 0, 255)]);
        $id = (int) Database::pdo()->lastInsertId();
        self::bustPageCache();

        return $id;
    }

    public static function update(
        int $id,
        string $title,
        string $description,
        string $coverUrl,
        string $videoUrl,
        string $duration,
        bool $published,
        bool $featured,
        int $sortOrder
    ): void {
        $stmt = Database::pdo()->prepare(
            'UPDATE videos SET title = :t, description = :d, cover_url = :c, video_url = :v,
             duration = :dur, is_published = :p, is_featured = :f, sort_order = :o WHERE id = :id'
        );
        $stmt->execute([
            ':t' => mb_substr(trim($title), 0, 255),
            ':d' => trim($description),
            ':c' => mb_substr(trim($coverUrl), 0, 500),
            ':v' => mb_substr(trim($videoUrl), 0, 500),
            ':dur' => mb_substr(trim($duration), 0, 20),
            ':p' => $published ? 1 : 0,
            ':f' => $featured ? 1 : 0,
            ':o' => $sortOrder,
            ':id' => $id,
        ]);
        self::bustPageCache();
    }

    public static function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM videos WHERE id = :id')->execute([':id' => $id]);
        self::bustPageCache();
    }

    public static function slugExists(string $slug): bool
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM videos WHERE slug = :s LIMIT 1');
        $stmt->execute([':s' => $slug]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Видео для блока «Медиа» на главной: отмеченные «показать на главном»;
     * если ни одного — откат на последние опубликованные.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forHome(int $limit = 8, ?string $lang = null): array
    {
        $limit = max(1, min(24, $limit));
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM videos WHERE is_published = 1 AND is_featured = 1
             ORDER BY sort_order ASC, created_at DESC, id DESC LIMIT ' . $limit
        );
        $stmt->execute();
        $rows = $stmt->fetchAll();
        if (empty($rows)) {
            $stmt = Database::pdo()->prepare(
                'SELECT * FROM videos WHERE is_published = 1
                 ORDER BY sort_order ASC, created_at DESC, id DESC LIMIT ' . $limit
            );
            $stmt->execute();
            $rows = $stmt->fetchAll();
        }

        if ($lang === null || $lang === Language::defaultCode()) {
            return $rows;
        }

        return self::localizeRows($rows, $lang);
    }

    private static function bustPageCache(): void
    {
        \App\Core\Cache::forgetPrefix('page:');
    }
}
