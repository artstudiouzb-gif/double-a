<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ProjectField
{
    public static function forProject(int $projectId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM project_fields WHERE project_id = :project_id ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([':project_id' => $projectId]);

        return $stmt->fetchAll();
    }

    public static function replaceAll(int $projectId, array $fields): void
    {
        Database::transaction(static function (\PDO $pdo) use ($projectId, $fields): void {
            $stmt = $pdo->prepare('DELETE FROM project_fields WHERE project_id = :project_id');
            $stmt->execute([':project_id' => $projectId]);

            $insert = $pdo->prepare(
                'INSERT INTO project_fields (project_id, field_key, field_value, sort_order) VALUES (:project_id, :field_key, :field_value, :sort_order)'
            );

            foreach (array_values($fields) as $index => $field) {
                if (trim((string) $field['field_key']) === '') {
                    continue;
                }
                $insert->execute([
                    ':project_id' => $projectId,
                    ':field_key' => $field['field_key'],
                    ':field_value' => $field['field_value'] ?? '',
                    ':sort_order' => $index,
                ]);
            }

        });
    }
}
