<?php

declare(strict_types=1);

namespace App\Core;

final class Search
{
    /** @return array<int,array{type:string,title:string,url:string}> */
    public static function query(string $term, int $perType = 5): array
    {
        $groups = SearchQuery::groups($term);
        if (mb_strlen(trim($term)) < 2 || $groups === []) {
            return [];
        }
        $pdo = Database::pdo();
        $results = [];
        $sources = [
            ['label' => 'Страница', 'table' => 'pages', 'expr' => "CONCAT_WS(' ', title, slug)", 'title' => 'title', 'where' => 'deleted_at IS NULL', 'url' => '/admin/pages/%d/edit'],
            ['label' => 'Новость', 'table' => 'news', 'expr' => "CONCAT_WS(' ', title, slug)", 'title' => 'title', 'where' => 'deleted_at IS NULL', 'url' => '/admin/news/%d/edit'],
            ['label' => 'Проект', 'table' => 'projects', 'expr' => "CONCAT_WS(' ', title, slug)", 'title' => 'title', 'where' => 'deleted_at IS NULL', 'url' => '/admin/projects/%d/edit'],
            ['label' => 'Файл', 'table' => 'files', 'expr' => 'original_name', 'title' => 'original_name', 'where' => '1=1', 'url' => '/admin/files'],
        ];

        foreach ($sources as $source) {
            try {
                [$condition, $params] = self::condition($source['expr'], $groups);
                $sql = "SELECT id, {$source['title']} AS title FROM {$source['table']} WHERE {$source['where']} AND {$condition} ORDER BY created_at DESC LIMIT ?";
                $stmt = $pdo->prepare($sql);
                self::bindSeq($stmt, [...$params, $perType]);
                $stmt->execute();
                foreach ($stmt->fetchAll() as $row) {
                    $results[] = [
                        'type' => $source['label'],
                        'title' => (string) $row['title'],
                        'url' => str_contains($source['url'], '%d') ? sprintf($source['url'], (int) $row['id']) : $source['url'],
                    ];
                }
            } catch (\Throwable $e) {
                Logger::error('Search failed for ' . $source['label'] . ': ' . $e->getMessage());
            }
        }

        return $results;
    }

    /** @return array<int,array{type:string,title:string,url:string,excerpt:string}> */
    public static function site(string $term, int $limit = 40): array
    {
        $groups = SearchQuery::groups($term);
        if (mb_strlen(trim($term)) < 2 || $groups === []) {
            return [];
        }
        $pdo = Database::pdo();
        $lang = Locale::current();
        $candidateLimit = max(10, min(120, $limit * 3));
        $results = [];

        try {
            $expr = "CONCAT_WS(' ', p.title, p.slug, p.meta_description, p.lead, t.title, t.meta_description, t.lead)";
            [$condition, $params] = self::condition($expr, $groups);
            $stmt = $pdo->prepare(
                "SELECT p.slug, COALESCE(NULLIF(t.title, ''), p.title) AS title,
                        COALESCE(NULLIF(t.lead, ''), p.lead, '') AS excerpt, p.updated_at AS sort_date
                 FROM pages p LEFT JOIN page_translations t ON t.page_id = p.id AND t.lang = ?
                 WHERE p.deleted_at IS NULL AND p.status = 'published' AND p.is_home = 0 AND {$condition}
                 ORDER BY p.updated_at DESC LIMIT ?"
            );
            self::bindSeq($stmt, [$lang, ...$params, $candidateLimit]);
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                self::append($results, $term, 'Страница', $row, Locale::url((string) $row['slug'], $lang));
            }

            $expr = "CONCAT_WS(' ', n.title, n.slug, n.excerpt, n.content, t.title, t.excerpt, t.content)";
            [$condition, $params] = self::condition($expr, $groups);
            $stmt = $pdo->prepare(
                "SELECT n.slug, COALESCE(NULLIF(t.title, ''), n.title) AS title,
                        COALESCE(NULLIF(t.excerpt, ''), n.excerpt, '') AS excerpt,
                        COALESCE(NULLIF(t.content, ''), n.content, '') AS body, n.published_at AS sort_date
                 FROM news n LEFT JOIN news_translations t ON t.news_id = n.id AND t.lang = ?
                 WHERE n.deleted_at IS NULL AND n.status = 'published' AND n.published_at <= NOW() AND {$condition}
                 ORDER BY n.published_at DESC LIMIT ?"
            );
            self::bindSeq($stmt, [$lang, ...$params, $candidateLimit]);
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                self::append($results, $term, 'Новость', $row, Locale::url('news/' . $row['slug'], $lang));
            }

            $expr = "CONCAT_WS(' ', title, slug, description)";
            [$condition, $params] = self::condition($expr, $groups);
            $stmt = $pdo->prepare(
                "SELECT title, slug, COALESCE(description, '') AS body, '' AS excerpt, created_at AS sort_date
                 FROM projects WHERE deleted_at IS NULL AND status = 'published' AND {$condition}
                 ORDER BY sort_order ASC, created_at DESC LIMIT ?"
            );
            self::bindSeq($stmt, [...$params, $candidateLimit]);
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                self::append($results, $term, 'Проект', $row, Locale::url('projects/' . $row['slug'], $lang));
            }

            $expr = "CONCAT_WS(' ', ce.title, ce.slug, ce.data, tr.title, tr.data)";
            [$condition, $params] = self::condition($expr, $groups);
            $stmt = $pdo->prepare(
                "SELECT COALESCE(NULLIF(tr.title, ''), ce.title) AS title, ce.slug,
                        CONCAT_WS(' ', ce.data, tr.data) AS body, '' AS excerpt,
                        ct.slug AS type_slug, ct.name AS type_name, ce.created_at AS sort_date
                 FROM content_entries ce JOIN content_types ct ON ct.id = ce.type_id
                 LEFT JOIN content_entry_translations tr ON tr.entry_id = ce.id AND tr.lang = ?
                 WHERE ce.deleted_at IS NULL AND ce.status = 'published' AND ct.is_public = 1 AND {$condition}
                 ORDER BY ce.created_at DESC LIMIT ?"
            );
            self::bindSeq($stmt, [$lang, ...$params, $candidateLimit]);
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                self::append($results, $term, (string) $row['type_name'], $row, Locale::url('catalog/' . $row['type_slug'] . '/' . $row['slug'], $lang));
            }
        } catch (\Throwable $e) {
            Logger::error('Site search failed: ' . $e->getMessage());
        }

        usort($results, static fn (array $a, array $b): int => ($b['_score'] <=> $a['_score']) ?: strcmp((string) $b['_date'], (string) $a['_date']));

        return array_map(static function (array $row): array {
            unset($row['_score'], $row['_date']);
            return $row;
        }, array_slice($results, 0, $limit));
    }

    /** @param list<list<string>> $groups @return array{string,list<string>} */
    private static function condition(string $expression, array $groups): array
    {
        $parts = [];
        $params = [];
        foreach ($groups as $variants) {
            $parts[] = '(' . implode(' OR ', array_fill(0, count($variants), $expression . ' LIKE ?')) . ')';
            foreach ($variants as $variant) {
                $params[] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $variant) . '%';
            }
        }

        return [implode(' AND ', $parts), $params];
    }

    /** @param array<int,array<string,mixed>> $results @param array<string,mixed> $row */
    private static function append(array &$results, string $term, string $type, array $row, string $url): void
    {
        $title = (string) ($row['title'] ?? '');
        $body = (string) ($row['body'] ?? $row['excerpt'] ?? '');
        $results[] = [
            'type' => $type,
            'title' => $title,
            'url' => $url,
            'excerpt' => mb_substr(trim(strip_tags((string) ($row['excerpt'] ?? $body))), 0, 160),
            '_score' => SearchQuery::score($term, $title, $body, (string) ($row['slug'] ?? '')),
            '_date' => (string) ($row['sort_date'] ?? ''),
        ];
    }

    private static function bindSeq(\PDOStatement $stmt, array $params): void
    {
        foreach (array_values($params) as $index => $value) {
            $stmt->bindValue($index + 1, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
    }
}
