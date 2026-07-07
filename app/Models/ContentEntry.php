<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Запись пользовательского типа контента (задача 131). Значения полей хранятся
 * в JSON-колонке data; переводы — в content_entry_translations.
 */
final class ContentEntry
{
    /** @return array<int, array<string, mixed>> */
    public static function forType(int $typeId, ?string $status = null): array
    {
        $sql = 'SELECT * FROM content_entries WHERE type_id = :t AND deleted_at IS NULL';
        $params = [':t' => $typeId];
        if ($status === 'published' || $status === 'draft') {
            $sql .= ' AND status = :s';
            $params[':s'] = $status;
        }
        $sql .= ' ORDER BY sort_order ASC, created_at DESC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Опубликованные записи типа с поиском/сортировкой/пагинацией (фронтенд).
     * Поиск — по заголовку и JSON-значениям полей (data). Сортировка:
     * new (новые), old (старые), title (по алфавиту).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forTypePublic(int $typeId, string $q = '', string $sort = 'new', int $limit = 12, int $offset = 0): array
    {
        [$where, $params] = self::publicWhere($typeId, $q);
        $order = match ($sort) {
            'old' => 'created_at ASC',
            'title' => 'title ASC',
            default => 'created_at DESC',
        };
        $sql = "SELECT * FROM content_entries WHERE {$where} ORDER BY {$order}, id DESC LIMIT :lim OFFSET :off";
        $stmt = Database::pdo()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function countTypePublic(int $typeId, string $q = ''): int
    {
        [$where, $params] = self::publicWhere($typeId, $q);
        $stmt = Database::pdo()->prepare("SELECT COUNT(*) FROM content_entries WHERE {$where}");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private static function publicWhere(int $typeId, string $q): array
    {
        $where = "type_id = :t AND status = 'published' AND deleted_at IS NULL";
        $params = [':t' => (string) $typeId];
        $q = trim($q);
        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
            $where .= ' AND (title LIKE :q1 OR data LIKE :q2)';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
        }

        return [$where, $params];
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM content_entries WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $row['data'] = json_decode((string) $row['data'], true) ?: [];

        return $row;
    }

    public static function findPublishedBySlug(int $typeId, string $slug): ?array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM content_entries WHERE type_id = :t AND slug = :s AND status = 'published' AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute([':t' => $typeId, ':s' => $slug]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $row['data'] = json_decode((string) $row['data'], true) ?: [];

        return $row;
    }

    public static function slugExists(int $typeId, string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM content_entries WHERE type_id = :t AND slug = :s';
        $params = [':t' => $typeId, ':s' => $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id != :id';
            $params[':id'] = $excludeId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function create(int $typeId, string $title, string $slug, string $status, array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO content_entries (type_id, title, slug, status, data, created_at)
             VALUES (:t, :ti, :sl, :st, :d, NOW())'
        );
        $stmt->execute([
            ':t' => $typeId,
            ':ti' => $title,
            ':sl' => $slug,
            ':st' => $status === 'published' ? 'published' : 'draft',
            ':d' => json_encode($data, JSON_UNESCAPED_UNICODE),
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public static function update(int $id, string $title, string $slug, string $status, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE content_entries SET title = :ti, slug = :sl, status = :st, data = :d WHERE id = :id'
        );
        $stmt->execute([
            ':ti' => $title,
            ':sl' => $slug,
            ':st' => $status === 'published' ? 'published' : 'draft',
            ':d' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ':id' => $id,
        ]);
    }

    /** Мягкое удаление. */
    public static function delete(int $id): void
    {
        Database::pdo()->prepare('UPDATE content_entries SET deleted_at = NOW() WHERE id = :id')->execute([':id' => $id]);
    }

    // --- Переводы ---

    /** @return array<string, array<string, mixed>> перевод по языку */
    public static function translations(int $entryId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM content_entry_translations WHERE entry_id = :e');
        $stmt->execute([':e' => $entryId]);
        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[(string) $r['lang']] = [
                'title' => $r['title'],
                'data' => $r['data'] ? (json_decode((string) $r['data'], true) ?: []) : [],
            ];
        }

        return $out;
    }

    public static function upsertTranslation(int $entryId, string $lang, ?string $title, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO content_entry_translations (entry_id, lang, title, data)
             VALUES (:e, :l, :ti, :d)
             ON DUPLICATE KEY UPDATE title = VALUES(title), data = VALUES(data)'
        );
        $stmt->execute([
            ':e' => $entryId,
            ':l' => $lang,
            ':ti' => $title,
            ':d' => json_encode($data, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
