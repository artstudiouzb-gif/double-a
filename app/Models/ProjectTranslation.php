<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ProjectTranslation
{
    /**
     * @return array<string, array<string, mixed>> переводы по коду языка
     */
    public static function forProject(int $projectId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM project_translations WHERE project_id = :id');
        $stmt->execute([':id' => $projectId]);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(string) $row['lang']] = $row;
        }

        return $result;
    }

    public static function find(int $projectId, string $lang): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM project_translations WHERE project_id = :id AND lang = :lang LIMIT 1'
        );
        $stmt->execute([':id' => $projectId, ':lang' => $lang]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Пакетная загрузка одного перевода для списка проектов — устраняет N+1.
     * @param list<int> $projectIds
     * @return array<int, array<string, mixed>>
     */
    public static function forProjectIds(array $projectIds, string $lang): array
    {
        $projectIds = array_values(array_unique(array_filter(array_map('intval', $projectIds), static fn (int $id): bool => $id > 0)));
        if ($projectIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM project_translations WHERE project_id IN ({$placeholders}) AND lang = ?"
        );
        $stmt->execute([...$projectIds, $lang]);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(int) $row['project_id']] = $row;
        }
        return $result;
    }

    public static function upsert(int $projectId, string $lang, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO project_translations (project_id, lang, title, description)
             VALUES (:project_id, :lang, :title, :description)
             ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description)'
        );
        $stmt->execute([
            ':project_id' => $projectId,
            ':lang' => $lang,
            ':title' => $data['title'] ?? null,
            ':description' => $data['description'] ?? null,
        ]);
    }
}
