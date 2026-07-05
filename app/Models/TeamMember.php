<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class TeamMember
{
    public static function all(): array
    {
        $stmt = Database::pdo()->query('SELECT * FROM team_members ORDER BY sort_order ASC, id ASC');

        return $stmt->fetchAll();
    }

    public static function published(): array
    {
        $stmt = Database::pdo()->query(
            "SELECT * FROM team_members WHERE status = 'published' ORDER BY sort_order ASC, id ASC"
        );

        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM team_members WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO team_members (name, position, photo, email, phone, socials_json, status, sort_order, created_at)
             VALUES (:name, :position, :photo, :email, :phone, :socials_json, :status, :sort_order, NOW())'
        );
        $stmt->execute([
            ':name' => $data['name'],
            ':position' => $data['position'],
            ':photo' => $data['photo'],
            ':email' => $data['email'],
            ':phone' => $data['phone'],
            ':socials_json' => json_encode($data['socials'] ?? [], JSON_UNESCAPED_UNICODE),
            ':status' => $data['status'],
            ':sort_order' => $data['sort_order'] ?? 0,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE team_members SET name = :name, position = :position, photo = :photo, email = :email,
             phone = :phone, socials_json = :socials_json, status = :status, sort_order = :sort_order WHERE id = :id'
        );
        $stmt->execute([
            ':name' => $data['name'],
            ':position' => $data['position'],
            ':photo' => $data['photo'],
            ':email' => $data['email'],
            ':phone' => $data['phone'],
            ':socials_json' => json_encode($data['socials'] ?? [], JSON_UNESCAPED_UNICODE),
            ':status' => $data['status'],
            ':sort_order' => $data['sort_order'] ?? 0,
            ':id' => $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM team_members WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}
