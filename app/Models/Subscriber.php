<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Подписчики email-дайджеста новостей. Токен — для ссылки отписки в письме
 * (без входа в аккаунт). Рассылку выполняет app/Console/digest_worker.php.
 */
final class Subscriber
{
    /**
     * Подписывает адрес. Возвращает: 'ok' — добавлен, 'exists' — уже был,
     * 'invalid' — некорректный email.
     */
    public static function subscribe(string $email): string
    {
        $email = mb_strtolower(trim($email));
        if ($email === '' || mb_strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'invalid';
        }

        try {
            $stmt = Database::pdo()->prepare(
                'INSERT INTO subscribers (email, token, created_at) VALUES (:e, :t, NOW())'
            );
            $stmt->execute([':e' => $email, ':t' => bin2hex(random_bytes(24))]);
        } catch (\PDOException) {
            return 'exists'; // уникальный ключ по email
        }

        return 'ok';
    }

    public static function unsubscribeByToken(string $token): bool
    {
        $token = trim($token);
        if ($token === '' || preg_match('/^[0-9a-f]{48}$/', $token) !== 1) {
            return false;
        }
        $stmt = Database::pdo()->prepare('DELETE FROM subscribers WHERE token = :t');
        $stmt->execute([':t' => $token]);

        return $stmt->rowCount() > 0;
    }

    /** @return array<int, array<string, mixed>> */
    public static function all(): array
    {
        return Database::pdo()->query(
            'SELECT * FROM subscribers ORDER BY created_at DESC, id DESC'
        )->fetchAll();
    }

    public static function count(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM subscribers')->fetchColumn();
    }

    public static function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM subscribers WHERE id = :id')->execute([':id' => $id]);
    }
}
