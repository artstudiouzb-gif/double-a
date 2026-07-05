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
}
