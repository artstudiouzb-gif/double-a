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
        // Гонко-безопасная выборка: FOR UPDATE SKIP LOCKED + аренда строк,
        // чтобы параллельные воркеры не дублировали обработку (QueueClaim).
        return \App\Core\QueueClaim::batch('social_posts', self::MAX_ATTEMPTS, $limit);
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

        // Dead-letter (группа 2.2): переход в failed после исчерпания ретраев —
        // алертим один раз.
        $check = Database::pdo()->prepare('SELECT news_id, network, status FROM social_posts WHERE id = :id LIMIT 1');
        $check->execute([':id' => $id]);
        $row = $check->fetch();
        if ($row && (string) $row['status'] === 'failed') {
            \App\Core\Logger::warning('Автопубликация в соцсеть не удалась после всех попыток (dead-letter)', [
                'social_post_id' => $id,
                'news_id' => (int) ($row['news_id'] ?? 0),
                'network' => (string) ($row['network'] ?? ''),
                'error' => mb_substr($error, 0, 200),
            ]);
        }
    }

    /** @return array<int, array<string, mixed>> статусы публикаций новости */
    public static function forNews(int $newsId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM social_posts WHERE news_id = :nid ORDER BY network');
        $stmt->execute([':nid' => $newsId]);

        return $stmt->fetchAll();
    }

    /**
     * Публикации, «застрявшие» в failed после исчерпания ретраев (группа 2.2).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function recentFailed(int $limit = 30): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT sp.*, n.title AS news_title FROM social_posts sp
             LEFT JOIN news n ON n.id = sp.news_id
             WHERE sp.status = 'failed'
             ORDER BY sp.created_at DESC, sp.id DESC LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
