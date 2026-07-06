<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Очередь авто-публикаций новости в соцсети. Одна строка = одна публикация
 * в одну сеть. Обрабатывается CLI-воркером (app/Console/social_worker.php).
 */
final class SocialPost
{
    private const MAX_ATTEMPTS = 3;

    /** Ставит публикацию в очередь (idempotent по паре news_id+network). */
    public static function enqueue(int $newsId, string $network): void
    {
        $stmt = Database::pdo()->prepare(
            "INSERT INTO social_posts (news_id, network, status, created_at)
             VALUES (:nid, :net, 'pending', NOW())
             ON DUPLICATE KEY UPDATE
                status = IF(status = 'sent', 'sent', 'pending'),
                attempts = IF(status = 'sent', attempts, 0)"
        );
        $stmt->execute([':nid' => $newsId, ':net' => $network]);
    }

    /** @return array<int, array<string, mixed>> */
    public static function pendingBatch(int $limit = 20): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM social_posts WHERE status = 'pending' AND attempts < :max
             ORDER BY created_at ASC LIMIT :limit"
        );
        $stmt->bindValue(':max', self::MAX_ATTEMPTS, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function markSent(int $id, ?string $remoteId): void
    {
        $stmt = Database::pdo()->prepare(
            "UPDATE social_posts SET status = 'sent', sent_at = NOW(), attempts = attempts + 1,
                    remote_id = :rid, last_error = NULL WHERE id = :id"
        );
        $stmt->execute([':rid' => $remoteId, ':id' => $id]);
    }

    public static function markFailed(int $id, string $error): void
    {
        $stmt = Database::pdo()->prepare(
            "UPDATE social_posts
             SET attempts = attempts + 1,
                 last_error = :error,
                 status = IF(attempts + 1 >= :max, 'failed', 'pending')
             WHERE id = :id"
        );
        $stmt->bindValue(':error', mb_substr($error, 0, 500));
        $stmt->bindValue(':max', self::MAX_ATTEMPTS, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    /** @return array<int, array<string, mixed>> статусы публикаций новости */
    public static function forNews(int $newsId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM social_posts WHERE news_id = :nid ORDER BY network');
        $stmt->execute([':nid' => $newsId]);

        return $stmt->fetchAll();
    }
}
