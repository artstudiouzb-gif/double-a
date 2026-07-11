<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Сквозной поиск по админке (задача 92): страницы, новости, проекты, файлы.
 * Возвращает плоский список результатов со ссылками на редактирование.
 */
final class Search
{
    /**
     * @return array<int, array{type: string, title: string, url: string}>
     */
    public static function query(string $term, int $perType = 5): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return [];
        }
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
        $pdo = Database::pdo();
        $results = [];

        // Один плейсхолдер :q через CONCAT_WS — переносимо при native prepares
        // (повтор именованного плейсхолдера в SQL недопустим без эмуляции).
        $sources = [
            ['label' => 'Страница', 'sql' => "SELECT id, title FROM pages WHERE deleted_at IS NULL AND CONCAT_WS(' ', title, slug) LIKE :q ORDER BY created_at DESC LIMIT :n", 'url' => '/admin/pages/%d/edit'],
            ['label' => 'Новость', 'sql' => "SELECT id, title FROM news WHERE deleted_at IS NULL AND CONCAT_WS(' ', title, slug) LIKE :q ORDER BY created_at DESC LIMIT :n", 'url' => '/admin/news/%d/edit'],
            ['label' => 'Проект', 'sql' => "SELECT id, title FROM projects WHERE deleted_at IS NULL AND CONCAT_WS(' ', title, slug) LIKE :q ORDER BY created_at DESC LIMIT :n", 'url' => '/admin/projects/%d/edit'],
            ['label' => 'Файл', 'sql' => "SELECT id, original_name AS title FROM files WHERE original_name LIKE :q ORDER BY created_at DESC LIMIT :n", 'url' => '/admin/files'],
        ];

        foreach ($sources as $src) {
            try {
                $stmt = $pdo->prepare($src['sql']);
                $stmt->bindValue(':q', $like);
                $stmt->bindValue(':n', $perType, \PDO::PARAM_INT);
                $stmt->execute();
                foreach ($stmt->fetchAll() as $row) {
                    $results[] = [
                        'type' => $src['label'],
                        'title' => (string) $row['title'],
                        'url' => str_contains($src['url'], '%d') ? sprintf($src['url'], (int) $row['id']) : $src['url'],
                    ];
                }
            } catch (\Throwable $e) {
                Logger::error('Search failed for ' . $src['label'] . ': ' . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Публичный поиск по сайту: только опубликованный контент, ссылки ведут на
     * страницы фронтенда. Ищет по страницам, новостям и записям публичных
     * пользовательских типов контента. URL локализуются под текущий язык.
     *
     * @return array<int, array{type: string, title: string, url: string, excerpt: string}>
     */
    public static function site(string $term, int $limit = 40): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return [];
        }
        $likes = self::likeWords($term);
        $pdo = Database::pdo();
        $lang = Locale::current();
        $results = [];

        // Условие «все слова запроса встречаются» по выражению $expr.
        $cond = static fn (string $expr): string => '(' . implode(' AND ', array_fill(0, count($likes), $expr . ' LIKE ?')) . ')';

        try {
            // Страницы — с учётом перевода текущего языка (title/lead/meta).
            $expr = "CONCAT_WS(' ', p.title, p.slug, p.meta_description, t.title, t.meta_description, t.lead)";
            $stmt = $pdo->prepare(
                "SELECT p.slug, COALESCE(NULLIF(t.title, ''), p.title) AS title, t.lead AS lead
                 FROM pages p
                 LEFT JOIN page_translations t ON t.page_id = p.id AND t.lang = ?
                 WHERE p.deleted_at IS NULL AND p.status = 'published' AND p.is_home = 0
                   AND " . $cond($expr) . "
                 ORDER BY p.updated_at DESC LIMIT ?"
            );
            self::bindSeq($stmt, array_merge([$lang], $likes, [$limit]));
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                $results[] = [
                    'type' => 'Страница',
                    'title' => (string) $row['title'],
                    'url' => Locale::url((string) $row['slug'], $lang),
                    'excerpt' => mb_substr(trim(strip_tags((string) ($row['lead'] ?? ''))), 0, 160),
                ];
            }

            // Новости — с переводом текущего языка (title/excerpt/content).
            $expr = "CONCAT_WS(' ', n.title, n.slug, n.excerpt, n.content, t.title, t.excerpt, t.content)";
            $stmt = $pdo->prepare(
                "SELECT n.slug, COALESCE(NULLIF(t.title, ''), n.title) AS title,
                        COALESCE(NULLIF(t.excerpt, ''), n.excerpt) AS excerpt
                 FROM news n
                 LEFT JOIN news_translations t ON t.news_id = n.id AND t.lang = ?
                 WHERE n.deleted_at IS NULL AND n.status = 'published'
                   AND " . $cond($expr) . "
                 ORDER BY n.published_at DESC LIMIT ?"
            );
            self::bindSeq($stmt, array_merge([$lang], $likes, [$limit]));
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                $results[] = [
                    'type' => 'Новость',
                    'title' => (string) $row['title'],
                    'url' => Locale::url('news/' . $row['slug'], $lang),
                    'excerpt' => mb_substr(trim(strip_tags((string) ($row['excerpt'] ?? ''))), 0, 160),
                ];
            }

            // Проекты.
            $expr = "CONCAT_WS(' ', title, slug, description)";
            $stmt = $pdo->prepare(
                "SELECT title, slug FROM projects
                 WHERE deleted_at IS NULL AND status = 'published'
                   AND " . $cond($expr) . "
                 ORDER BY sort_order ASC, created_at DESC LIMIT ?"
            );
            self::bindSeq($stmt, array_merge($likes, [$limit]));
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                $results[] = [
                    'type' => 'Проект',
                    'title' => (string) $row['title'],
                    'url' => Locale::url('projects/' . $row['slug'], $lang),
                    'excerpt' => '',
                ];
            }

            // Записи публичных типов контента (каталог).
            $expr = "CONCAT_WS(' ', ce.title, ce.slug, ce.data)";
            $stmt = $pdo->prepare(
                "SELECT ce.title, ce.slug, ct.slug AS type_slug, ct.name AS type_name
                 FROM content_entries ce
                 JOIN content_types ct ON ct.id = ce.type_id
                 WHERE ce.deleted_at IS NULL AND ce.status = 'published' AND ct.is_public = 1
                   AND " . $cond($expr) . "
                 ORDER BY ce.created_at DESC LIMIT ?"
            );
            self::bindSeq($stmt, array_merge($likes, [$limit]));
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                $results[] = [
                    'type' => (string) $row['type_name'],
                    'title' => (string) $row['title'],
                    'url' => Locale::url('catalog/' . $row['type_slug'] . '/' . $row['slug'], $lang),
                    'excerpt' => '',
                ];
            }
        } catch (\Throwable $e) {
            Logger::error('Site search failed: ' . $e->getMessage());
        }

        return $results;
    }

    /**
     * Разбивает запрос на слова (каждое ≥2 символов) и превращает в LIKE-шаблоны
     * с экранированием. Многословный поиск: каждое слово должно встретиться
     * (порядок и соседство не важны). Если разумных слов нет — весь запрос целиком.
     *
     * @return array<int, string>
     */
    private static function likeWords(string $term): array
    {
        $words = array_values(array_filter(
            preg_split('/\s+/', $term) ?: [],
            static fn (string $w): bool => mb_strlen($w) >= 2
        ));
        if ($words === []) {
            $words = [$term];
        }
        // Не больше 6 слов — защита от чрезмерно длинных запросов.
        $words = array_slice($words, 0, 6);

        return array_map(
            static fn (string $w): string => '%' . str_replace(['%', '_'], ['\%', '\_'], $w) . '%',
            $words
        );
    }

    /** Позиционная привязка параметров: строки как есть, целые — как INT. */
    private static function bindSeq(\PDOStatement $stmt, array $params): void
    {
        $i = 1;
        foreach ($params as $p) {
            if (is_int($p)) {
                $stmt->bindValue($i, $p, \PDO::PARAM_INT);
            } else {
                $stmt->bindValue($i, $p);
            }
            $i++;
        }
    }
}
