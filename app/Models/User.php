<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class User
{
    public static function all(): array
    {
        $stmt = Database::pdo()->query('SELECT id, username, email, phone, role, last_login_at, created_at FROM users ORDER BY id ASC');

        return $stmt->fetchAll();
    }

    public static function count(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public static function emailExists(string $email): bool
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function findByUsername(string $username): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $username]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function updatePassword(int $id, string $newPassword): void
    {
        $stmt = Database::pdo()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $stmt->execute([
            ':hash' => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]),
            ':id' => $id,
        ]);
    }

    public static function enableTotp(int $id, string $secret): void
    {
        $stmt = Database::pdo()->prepare('UPDATE users SET totp_secret = :secret, totp_enabled = 1 WHERE id = :id');
        $stmt->execute([':secret' => $secret, ':id' => $id]);
    }

    /** Телефон (E.164) для кода входа через Telegram; null — вход без кода. */
    public static function updatePhone(int $id, ?string $phone): void
    {
        $stmt = Database::pdo()->prepare('UPDATE users SET phone = :phone WHERE id = :id');
        $stmt->execute([':phone' => $phone, ':id' => $id]);
    }

    /** chat_id Telegram-бота для кодов входа; null — отвязать. */
    public static function updateTelegramChatId(int $id, ?int $chatId): void
    {
        $stmt = Database::pdo()->prepare('UPDATE users SET telegram_chat_id = :cid WHERE id = :id');
        $stmt->execute([':cid' => $chatId, ':id' => $id]);
    }

    public static function touchLastLogin(int $id): void
    {
        $stmt = Database::pdo()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public static function create(string $username, string $email, string $password, string $role = 'admin', ?string $phone = null): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO users (username, email, phone, password_hash, role, created_at)
             VALUES (:username, :email, :phone, :password, :role, NOW())'
        );
        $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':phone' => $phone,
            ':password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            ':role' => $role,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }
}
