<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Block
{
    /**
     * Блоки страницы для конкретного языкового стека.
     */
    public static function forPage(int $pageId, ?string $lang = null): array
    {
        $lang = $lang ?? Language::defaultCode();
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM blocks WHERE page_id = :page_id AND lang = :lang ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([':page_id' => $pageId, ':lang' => $lang]);

        return $stmt->fetchAll();
    }

    /**
     * Блоки для вывода на сайте: язык -> при отсутствии откат на язык по умолчанию.
     */
    public static function forPageLocalized(int $pageId, string $lang): array
    {
        $blocks = self::forPage($pageId, $lang);
        if (!empty($blocks) || $lang === Language::defaultCode()) {
            return $blocks;
        }

        return self::forPage($pageId, Language::defaultCode());
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM blocks WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function create(int $pageId, string $lang, string $type, ?string $title, array $data, string $customCss): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM blocks WHERE page_id = :page_id AND lang = :lang'
        );
        $stmt->execute([':page_id' => $pageId, ':lang' => $lang]);
        $nextOrder = (int) $stmt->fetchColumn();

        $stmt = Database::pdo()->prepare(
            'INSERT INTO blocks (page_id, lang, type, title, data, custom_css, sort_order, created_at)
             VALUES (:page_id, :lang, :type, :title, :data, :custom_css, :sort_order, NOW())'
        );
        $stmt->execute([
            ':page_id' => $pageId,
            ':lang' => $lang,
            ':type' => $type,
            ':title' => $title,
            ':data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ':custom_css' => $customCss,
            ':sort_order' => $nextOrder,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public static function update(int $id, ?string $title, array $data, string $customCss): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE blocks SET title = :title, data = :data, custom_css = :custom_css WHERE id = :id'
        );
        $stmt->execute([
            ':title' => $title,
            ':data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ':custom_css' => $customCss,
            ':id' => $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM blocks WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /**
     * Устанавливает порядок блоков по заданному списку id (drag-and-drop,
     * задача 134). Учитываются только блоки, реально принадлежащие странице
     * и языку; посторонние id игнорируются.
     *
     * @param array<int, int> $orderedIds
     */
    public static function reorder(int $pageId, string $lang, array $orderedIds): void
    {
        $valid = [];
        foreach (self::forPage($pageId, $lang) as $block) {
            $valid[(int) $block['id']] = true;
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE blocks SET sort_order = :order WHERE id = :id AND page_id = :pid AND lang = :lang');
            $order = 1;
            foreach ($orderedIds as $id) {
                $id = (int) $id;
                if (!isset($valid[$id])) {
                    continue;
                }
                $stmt->execute([':order' => $order++, ':id' => $id, ':pid' => $pageId, ':lang' => $lang]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function moveUp(int $id, int $pageId, string $lang): void
    {
        self::swap($id, $pageId, $lang, 'up');
    }

    public static function moveDown(int $id, int $pageId, string $lang): void
    {
        self::swap($id, $pageId, $lang, 'down');
    }

    private static function swap(int $id, int $pageId, string $lang, string $direction): void
    {
        $blocks = self::forPage($pageId, $lang);
        $index = null;
        foreach ($blocks as $i => $block) {
            if ((int) $block['id'] === $id) {
                $index = $i;
                break;
            }
        }

        if ($index === null) {
            return;
        }

        $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;
        if ($swapIndex < 0 || $swapIndex >= count($blocks)) {
            return;
        }

        $current = $blocks[$index];
        $target = $blocks[$swapIndex];

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE blocks SET sort_order = :order WHERE id = :id');
            $stmt->execute([':order' => $target['sort_order'], ':id' => $current['id']]);
            $stmt->execute([':order' => $current['sort_order'], ':id' => $target['id']]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
