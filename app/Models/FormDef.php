<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class FormDef
{
    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM forms WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $row['fields'] = json_decode((string) $row['fields_json'], true) ?: [];

        return $row;
    }

    public static function findBySlug(string $slug): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM forms WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $row['fields'] = json_decode((string) $row['fields_json'], true) ?: [];

        return $row;
    }
}
