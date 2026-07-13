<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ProjectImage
{
    public static function forProject(int $projectId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM project_images WHERE project_id = :project_id ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([':project_id' => $projectId]);

        return $stmt->fetchAll();
    }

    public static function replaceAll(int $projectId, array $images): void
    {
        Database::transaction(static function (\PDO $pdo) use ($projectId, $images): void {
            $stmt = $pdo->prepare('DELETE FROM project_images WHERE project_id = :project_id');
            $stmt->execute([':project_id' => $projectId]);

            $insert = $pdo->prepare(
                'INSERT INTO project_images (project_id, file_path, caption, sort_order) VALUES (:project_id, :file_path, :caption, :sort_order)'
            );

            foreach (array_values($images) as $index => $image) {
                if (trim((string) $image['file_path']) === '') {
                    continue;
                }
                $insert->execute([
                    ':project_id' => $projectId,
                    ':file_path' => $image['file_path'],
                    ':caption' => $image['caption'] ?? null,
                    ':sort_order' => $index,
                ]);
            }

        });
    }
}
