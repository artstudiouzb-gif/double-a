<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;

final class FileEntry
{
    public static function all(): array
    {
        $stmt = Database::pdo()->query('SELECT * FROM files ORDER BY created_at DESC');

        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM files WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO files (original_name, stored_name, mime_type, size, access_type, access_token, uploaded_by, created_at)
             VALUES (:original_name, :stored_name, :mime_type, :size, :access_type, :access_token, :uploaded_by, NOW())'
        );
        $stmt->execute([
            ':original_name' => $data['original_name'],
            ':stored_name' => $data['stored_name'],
            ':mime_type' => $data['mime_type'],
            ':size' => $data['size'],
            ':access_type' => $data['access_type'],
            ':access_token' => $data['access_token'],
            ':uploaded_by' => $data['uploaded_by'],
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public static function regenerateToken(int $id): string
    {
        $token = bin2hex(random_bytes(32));
        $stmt = Database::pdo()->prepare('UPDATE files SET access_token = :token WHERE id = :id');
        $stmt->execute([':token' => $token, ':id' => $id]);

        return $token;
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM files WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public static function publicUrl(array $file): string
    {
        return rtrim((string) Config::get('paths.public_uploads_url'), '/') . '/' . $file['stored_name'];
    }
}
