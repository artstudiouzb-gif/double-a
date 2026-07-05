<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Rate limiting на попытках входа: считается по паре IP+идентификатор,
 * чтобы одновременно ограничивать и перебор по одному аккаунту с разных IP,
 * и перебор разных аккаунтов с одного IP.
 */
final class RateLimiter
{
    public static function tooManyAttempts(string $identifier): bool
    {
        $maxAttempts = (int) Config::get('security.login_max_attempts', 5);
        $windowMinutes = (int) Config::get('security.login_attempts_window_minutes', 15);

        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE identifier = :identifier
               AND success = 0
               AND attempted_at > (NOW() - INTERVAL :window MINUTE)'
        );
        $stmt->bindValue(':identifier', $identifier);
        $stmt->bindValue(':window', $windowMinutes, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn() >= $maxAttempts;
    }

    public static function secondsUntilRetry(string $identifier): int
    {
        $lockoutMinutes = (int) Config::get('security.login_lockout_minutes', 15);

        // Считаем разницу полностью на стороне MySQL (NOW() и attempted_at
        // в одной и той же временной зоне сервера БД), чтобы не зависеть от
        // того, совпадает ли часовой пояс PHP (date_default_timezone_set)
        // с часовым поясом MySQL.
        $stmt = Database::pdo()->prepare(
            'SELECT GREATEST(0, TIMESTAMPDIFF(
                SECOND, NOW(), DATE_ADD(MAX(attempted_at), INTERVAL :lockout MINUTE)
             )) AS remaining
             FROM login_attempts
             WHERE identifier = :identifier AND success = 0'
        );
        $stmt->bindValue(':lockout', $lockoutMinutes, PDO::PARAM_INT);
        $stmt->bindValue(':identifier', $identifier);
        $stmt->execute();

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    public static function recordAttempt(string $identifier, bool $success): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO login_attempts (identifier, ip_address, success, attempted_at) VALUES (:identifier, :ip, :success, NOW())'
        );
        $stmt->execute([
            ':identifier' => $identifier,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ':success' => $success ? 1 : 0,
        ]);
    }

    public static function clearAttempts(string $identifier): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM login_attempts WHERE identifier = :identifier');
        $stmt->execute([':identifier' => $identifier]);
    }

    /**
     * Универсальный сдвигающийся лимитер для любых действий за пределами
     * логина (отправка форм, перебор download-токенов, частота чанков).
     * Переиспользует таблицу login_attempts с namespace-идентификатором
     * (например, 'form|slug|1.2.3.4'). Записывает попытку и возвращает,
     * РАЗРЕШЕНО ли действие (true) или лимит превышен (false).
     */
    public static function throttle(string $namespace, string $key, int $maxAttempts, int $windowMinutes): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $identifier = $namespace . '|' . $key;

        try {
            $count = self::countRecent($identifier, $windowMinutes);
            self::recordAttempt($identifier, false);

            if ($count >= $maxAttempts) {
                Logger::error(sprintf(
                    'Rate limit exceeded: ns=%s key=%s ip=%s count=%d/%d',
                    $namespace, $key, $ip, $count + 1, $maxAttempts
                ));
                return false;
            }
        } catch (\Throwable $e) {
            // Отказ БД не должен ронять пользовательский сценарий — пропускаем.
            Logger::error('RateLimiter::throttle failed: ' . $e->getMessage());
            return true;
        }

        return true;
    }

    public static function countRecent(string $identifier, int $windowMinutes): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE identifier = :identifier
               AND attempted_at > (NOW() - INTERVAL :window MINUTE)'
        );
        $stmt->bindValue(':identifier', $identifier);
        $stmt->bindValue(':window', $windowMinutes, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Garbage collector: удаляет записи login_attempts старше суток и
     * ротирует лог-файлы. Вызывается вероятностно (не на каждый запрос),
     * чтобы не нагружать БД. Вероятность ~2% на успешный вход.
     */
    public static function garbageCollect(int $probabilityPercent = 2): void
    {
        if (random_int(1, 100) > $probabilityPercent) {
            return;
        }

        try {
            Database::pdo()->exec(
                'DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)'
            );
        } catch (\Throwable $e) {
            Logger::error('login_attempts GC failed: ' . $e->getMessage());
        }

        Logger::rotateAll();
    }
}
