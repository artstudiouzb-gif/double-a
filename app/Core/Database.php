<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $connection = null;

    public static function init(array $config): void
    {
        if (self::$connection !== null) {
            return;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        try {
            self::$connection = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            // Синхронизируем часовой пояс сессии MySQL со временем PHP. Иначе
            // NOW() в MySQL и published_at/created_at, записываемые из PHP,
            // расходятся на разницу поясов, и свежие новости/записи с фильтром
            // "published_at <= NOW()" прячутся до конца смещения (напр. на 3 часа).
            $offset = (new \DateTimeImmutable())->format('P'); // напр. +03:00
            self::$connection->exec("SET time_zone = '" . $offset . "'");
        } catch (PDOException $e) {
            // Бросаем исключение вместо exit — вызывающий код решает, что делать
            // (fail-safe 503 в рабочем режиме или продолжение в режиме установки).
            throw new \RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public static function isConnected(): bool
    {
        return self::$connection !== null;
    }

    public static function pdo(): PDO
    {
        if (self::$connection === null) {
            throw new \RuntimeException('Database is not initialized.');
        }

        return self::$connection;
    }

    /**
     * Выполняет единицу работы атомарно. Вложенный вызов присоединяется к уже
     * открытой транзакции, поэтому модели можно безопасно компоновать.
     *
     * @template T
     * @param callable(PDO): T $callback
     * @return T
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::pdo();
        $owner = !$pdo->inTransaction();
        if ($owner) {
            $pdo->beginTransaction();
        }
        try {
            $result = $callback($pdo);
            if ($owner) {
                $pdo->commit();
            }

            return $result;
        } catch (\Throwable $e) {
            if ($owner && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
