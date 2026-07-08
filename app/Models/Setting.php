<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Setting
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            $stmt = Database::pdo()->query('SELECT `key`, `value` FROM settings');
            self::$cache = [];
            foreach ($stmt->fetchAll() as $row) {
                self::$cache[$row['key']] = $row['value'];
            }
        }

        return self::$cache;
    }

    public static function get(string $key, string $default = ''): string
    {
        $all = self::all();

        return $all[$key] ?? $default;
    }

    public static function set(string $key, string $value): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO settings (`key`, `value`) VALUES (:key, :value)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
        );
        $stmt->execute([':key' => $key, ':value' => $value]);
        self::$cache = null;
    }

    /**
     * Переопределяет значение ТОЛЬКО в памяти текущего запроса (в БД не
     * пишется). Используется живым превью настроек дизайна: страница
     * рендерится с «примеренными» значениями без их сохранения.
     */
    public static function overrideInMemory(string $key, string $value): void
    {
        self::all(); // прогреваем кэш
        self::$cache[$key] = $value;
    }
}
