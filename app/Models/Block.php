<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Block
{
    public static function forPage(int $pageId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM blocks WHERE page_id = :page_id ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([':page_id' => $pageId]);

        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM blocks WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function create(int $pageId, string $type, ?string $title, array $data, string $customCss): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM blocks WHERE page_id = :page_id'
        );
        $stmt->execute([':page_id' => $pageId]);
        $nextOrder = (int) $stmt->fetchColumn();

        $stmt = Database::pdo()->prepare(
            'INSERT INTO blocks (page_id, type, title, data, custom_css, sort_order, created_at)
             VALUES (:page_id, :type, :title, :data, :custom_css, :sort_order, NOW())'
        );
        $stmt->execute([
            ':page_id' => $pageId,
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

    public static function moveUp(int $id, int $pageId): void
    {
        self::swap($id, $pageId, 'up');
    }

    public static function moveDown(int $id, int $pageId): void
    {
        self::swap($id, $pageId, 'down');
    }

    private static function swap(int $id, int $pageId, string $direction): void
    {
        $blocks = self::forPage($pageId);
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
