<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use App\Core\SecretBox;

final class Setting
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            $stmt = Database::pdo()->query('SELECT `key`, `value` FROM settings');
            self::$cache = [];
            foreach ($stmt->fetchAll() as $row) {
                $key = (string) $row['key'];
                $value = (string) $row['value'];
                if (self::isSecret($key)) {
                    try {
                        $value = SecretBox::decrypt($value, 'settings.' . $key) ?? '';
                    } catch (\Throwable $e) {
                        Logger::error('Не удалось расшифровать настройку ' . $key . ': ' . $e->getMessage());
                        $value = '';
                    }
                }
                self::$cache[$key] = $value;
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
        $stored = self::isSecret($key) && $value !== ''
            ? SecretBox::encrypt($value, 'settings.' . $key)
            : $value;
        $stmt = Database::pdo()->prepare(
            'INSERT INTO settings (`key`, `value`) VALUES (:key, :value)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
        );
        $stmt->execute([':key' => $key, ':value' => $stored]);
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

    public static function isSecret(string $key): bool
    {
        return in_array($key, [
            'cf_api_token',
            'telegram_gateway_token',
            'telegram_bot_token',
            'webpush_vapid_private',
        ], true) || preg_match('/^social_(telegram|facebook|linkedin|instagram)_token$/', $key) === 1;
    }
}
