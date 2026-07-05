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
}
