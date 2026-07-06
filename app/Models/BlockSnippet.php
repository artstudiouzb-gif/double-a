<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Библиотека шаблонов блоков (задача 133): именованный набор блоков страницы
 * (type/title/data/custom_css) для повторного применения.
 */
final class BlockSnippet
{
    /** @return array<int, array<string, mixed>> */
    public static function all(): array
    {
        return Database::pdo()->query('SELECT id, name, created_at FROM block_snippets ORDER BY created_at DESC')->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM block_snippets WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * @param array<int, array{type:string, title:?string, data:array, custom_css:string}> $blocks
     */
    public static function create(string $name, array $blocks): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO block_snippets (name, blocks_json, created_at) VALUES (:name, :json, NOW())'
        );
        $stmt->execute([
            ':name' => $name,
            ':json' => json_encode($blocks, JSON_UNESCAPED_UNICODE),
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public static function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM block_snippets WHERE id = :id')->execute([':id' => $id]);
    }
}
