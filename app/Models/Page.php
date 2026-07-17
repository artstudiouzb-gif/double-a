<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\ConcurrencyException;

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

    public static function adminList(array $filters): array
    {
        [$from, $params] = self::adminListFrom($filters);
        $orders = [
            'newest' => 'p.created_at DESC, p.id DESC',
            'oldest' => 'p.created_at ASC, p.id ASC',
            'title_asc' => 'p.title ASC, p.id ASC',
            'title_desc' => 'p.title DESC, p.id DESC',
        ];
        $order = $orders[$filters['sort'] ?? 'newest'] ?? $orders['newest'];
        $stmt = Database::pdo()->prepare("SELECT p.* {$from} ORDER BY {$order} LIMIT :limit OFFSET :offset");
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
        [$from, $params] = self::adminListFrom($filters);
        $stmt = Database::pdo()->prepare("SELECT COUNT(*) {$from}");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** @return array{0:string,1:array<string,string>} */
    private static function adminListFrom(array $filters): array
    {
        $from = 'FROM pages p';
        $params = [];
        if (($filters['lang'] ?? '') !== '' && $filters['lang'] !== Language::defaultCode()) {
            $from .= ' INNER JOIN page_translations pt ON pt.page_id = p.id AND pt.lang = :lang';
            $params[':lang'] = (string) $filters['lang'];
        }
        $from .= ' WHERE p.deleted_at IS NULL';
        if (in_array($filters['status'] ?? '', ['published', 'draft'], true)) {
            $from .= ' AND p.status = :status';
            $params[':status'] = (string) $filters['status'];
        }
        if (($filters['q'] ?? '') !== '') {
            $from .= ' AND (p.title LIKE :q_title OR p.slug LIKE :q_slug'
                . ' OR EXISTS (SELECT 1 FROM page_translations pqs WHERE pqs.page_id = p.id AND pqs.title LIKE :q_translation))';
            $like = '%' . (string) $filters['q'] . '%';
            $params[':q_title'] = $like;
            $params[':q_slug'] = $like;
            $params[':q_translation'] = $like;
        }

        return [$from, $params];
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
        ContentRevision::deleteForEntity('page', $id);
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
        if (isset($translation['lead']) && trim((string) $translation['lead']) !== '') {
            $row['lead'] = $translation['lead'];
        }

        return $row;
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM pages WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Языки, на которых страница реально наполнена: язык по умолчанию (базовая
     * строка) + языки с переводом заголовка или собственным стеком блоков.
     * Используется переключателем языков и hreflang (roadmap 1.2).
     *
     * @return string[]
     */
    /**
     * Языки контента для набора страниц одним запросом (без N+1).
     * Контент на языке = перевод заголовка ИЛИ блоки на этом языке.
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
            "SELECT page_id, lang FROM page_translations
             WHERE page_id IN ($in) AND TRIM(COALESCE(title, '')) <> ''
             UNION
             SELECT DISTINCT page_id, lang FROM blocks WHERE page_id IN ($in)"
        );
        $stmt->execute(array_merge($ids, $ids));
        foreach ($stmt->fetchAll() as $row) {
            $id = (int) $row['page_id'];
            $lang = (string) $row['lang'];
            if (isset($map[$id]) && !in_array($lang, $map[$id], true)) {
                $map[$id][] = $lang;
            }
        }

        return $map;
    }

    public static function availableLangs(int $id): array
    {
        $langs = [Language::defaultCode()];

        $stmt = Database::pdo()->prepare(
            "SELECT lang FROM page_translations WHERE page_id = :id AND TRIM(COALESCE(title, '')) <> ''
             UNION SELECT DISTINCT lang FROM blocks WHERE page_id = :id2"
        );
        $stmt->execute([':id' => $id, ':id2' => $id]);
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $lang) {
            $langs[] = (string) $lang;
        }

        return array_values(array_unique($langs));
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
        return Database::transaction(static function (\PDO $pdo) use ($data): int {
            if (!empty($data['is_home'])) {
                $pdo->exec('UPDATE pages SET is_home = 0');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO pages (title, slug, meta_title, meta_description, lead, status, is_home, layout_type, hide_chrome, transparent_header, created_at)
                 VALUES (:title, :slug, :meta_title, :meta_description, :lead, :status, :is_home, :layout_type, :hide_chrome, :transparent_header, NOW())'
            );
            $stmt->execute([
                ':title' => $data['title'],
                ':slug' => $data['slug'],
                ':meta_title' => $data['meta_title'],
                ':meta_description' => $data['meta_description'],
                ':lead' => $data['lead'] ?? null,
                ':status' => $data['status'],
                ':is_home' => !empty($data['is_home']) ? 1 : 0,
                ':layout_type' => $data['layout_type'] ?? 'no_sidebar',
                ':hide_chrome' => !empty($data['hide_chrome']) ? 1 : 0,
                ':transparent_header' => !empty($data['transparent_header']) ? 1 : 0,
            ]);
            $id = (int) $pdo->lastInsertId();

            return $id;
        });
    }

    public static function update(int $id, array $data, ?int $expectedLockVersion = null): void
    {
        Database::transaction(static function (\PDO $pdo) use ($id, $data, $expectedLockVersion): void {
            if (!empty($data['is_home'])) {
                $pdo->exec('UPDATE pages SET is_home = 0');
            }

            $stmt = $pdo->prepare(
                'UPDATE pages SET title = :title, slug = :slug, meta_title = :meta_title,
                 meta_description = :meta_description, lead = :lead, status = :status, is_home = :is_home,
                 layout_type = :layout_type, hide_chrome = :hide_chrome,
                 transparent_header = :transparent_header, lock_version = lock_version + 1
                 WHERE id = :id' . ($expectedLockVersion !== null ? ' AND lock_version = :expected_lock_version' : '')
            );
            $params = [
                ':title' => $data['title'],
                ':slug' => $data['slug'],
                ':meta_title' => $data['meta_title'],
                ':meta_description' => $data['meta_description'],
                ':lead' => $data['lead'] ?? null,
                ':status' => $data['status'],
                ':is_home' => !empty($data['is_home']) ? 1 : 0,
                ':layout_type' => $data['layout_type'] ?? 'no_sidebar',
                ':hide_chrome' => !empty($data['hide_chrome']) ? 1 : 0,
                ':transparent_header' => !empty($data['transparent_header']) ? 1 : 0,
                ':id' => $id,
            ];
            if ($expectedLockVersion !== null) {
                $params[':expected_lock_version'] = $expectedLockVersion;
            }
            $stmt->execute($params);
            if ($expectedLockVersion !== null && $stmt->rowCount() !== 1) {
                throw new ConcurrencyException('Страница была изменена другим пользователем.');
            }
        });
    }

    public static function delete(int $id): void
    {
        // Мягкое удаление: страница отправляется в корзину (блоки сохраняются).
        $stmt = Database::pdo()->prepare('UPDATE pages SET deleted_at = NOW(), is_home = 0 WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}
