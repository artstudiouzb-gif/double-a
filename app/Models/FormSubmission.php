<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class FormSubmission
{
    public static function forForm(int $formId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM form_submissions WHERE form_id = :form_id ORDER BY created_at DESC'
        );
        $stmt->execute([':form_id' => $formId]);

        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['data'] = json_decode((string) $row['data_json'], true) ?: [];
        }

        return $rows;
    }

    public static function countUnread(int $formId): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM form_submissions WHERE form_id = :form_id AND is_read = 0'
        );
        $stmt->execute([':form_id' => $formId]);

        return (int) $stmt->fetchColumn();
    }

    public static function create(int $formId, array $data, ?string $ip, ?string $userAgent): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO form_submissions (form_id, data_json, ip_address, user_agent, created_at)
             VALUES (:form_id, :data_json, :ip, :user_agent, NOW())'
        );
        $stmt->execute([
            ':form_id' => $formId,
            ':data_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ':ip' => $ip,
            ':user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 500) : null,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public static function markRead(int $id): void
    {
        $stmt = Database::pdo()->prepare('UPDATE form_submissions SET is_read = 1 WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM form_submissions WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}
