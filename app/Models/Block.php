<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Block
{
    /**
     * Блоки страницы для конкретного языкового стека. По умолчанию — только
     * верхнего уровня (parent_block_id IS NULL); дочерние блоки колонок
     * (группа 4.1) выбираются отдельно через childrenOf().
     */
    public static function forPage(int $pageId, ?string $lang = null, bool $topLevelOnly = true): array
    {
        $lang = $lang ?? Language::defaultCode();
        $sql = 'SELECT * FROM blocks WHERE page_id = :page_id AND lang = :lang';
        if ($topLevelOnly) {
            $sql .= ' AND parent_block_id IS NULL';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([':page_id' => $pageId, ':lang' => $lang]);

        return $stmt->fetchAll();
    }

    /**
     * Дочерние блоки родителя-колонок, сгруппированные по номеру колонки
     * (группа 4.1). Порядок: колонка, затем позиция внутри неё.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function childrenOf(int $parentBlockId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM blocks WHERE parent_block_id = :pid ORDER BY column_index ASC, sort_order ASC, id ASC'
        );
        $stmt->execute([':pid' => $parentBlockId]);

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

    public static function create(
        int $pageId,
        string $lang,
        string $type,
        ?string $title,
        array $data,
        string $customCss,
        ?int $parentBlockId = null,
        int $columnIndex = 0
    ): int {
        // Порядок считаем в пределах одного родителя (или верхнего уровня) и колонки.
        $stmt = Database::pdo()->prepare(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM blocks
             WHERE page_id = :page_id AND lang = :lang AND parent_block_id <=> :parent AND column_index = :col'
        );
        $stmt->execute([':page_id' => $pageId, ':lang' => $lang, ':parent' => $parentBlockId, ':col' => $columnIndex]);
        $nextOrder = (int) $stmt->fetchColumn();

        $stmt = Database::pdo()->prepare(
            'INSERT INTO blocks (page_id, parent_block_id, column_index, lang, type, title, data, custom_css, sort_order, created_at)
             VALUES (:page_id, :parent, :col, :lang, :type, :title, :data, :custom_css, :sort_order, NOW())'
        );
        $stmt->execute([
            ':page_id' => $pageId,
            ':parent' => $parentBlockId,
            ':col' => $columnIndex,
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
