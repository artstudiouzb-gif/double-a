<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Простое файловое кеширование в storage/cache/. Ключи вида "page:5:ru"
 * отображаются в путь storage/cache/page/5/ru.cache, что позволяет
 * инвалидировать целые группы (например, все языки одной страницы) удалением
 * поддиректории.
 */
final class Cache
{
    private static function dir(): string
    {
        return APP_ROOT . '/storage/cache';
    }

    private static function pathFor(string $key): string
    {
        $segments = array_map(
            static fn ($s) => preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $s) ?? '_',
            explode(':', $key)
        );
        $file = array_pop($segments);
        $sub = $segments === [] ? '' : '/' . implode('/', $segments);

        return self::dir() . $sub . '/' . $file . '.cache';
    }

    /**
     * Чтение с учётом TTL: если $ttl > 0 и файл старше — считаем промахом.
     */
    private static function getFresh(string $key, int $ttl): mixed
    {
        if ($ttl > 0) {
            $path = self::pathFor($key);
            if (is_file($path) && (time() - (int) @filemtime($path)) > $ttl) {
                return null;
            }
        }

        return self::get($key);
    }

    public static function get(string $key): mixed
    {
        $path = self::pathFor($key);
        if (!is_file($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $data = @unserialize($raw, ['allowed_classes' => false]);

        return $data === false && $raw !== serialize(false) ? null : $data;
    }

    public static function put(string $key, mixed $value): void
    {
        $path = self::pathFor($key);
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return;
        }
        @file_put_contents($path, serialize($value), LOCK_EX);
    }

    /** Анти-stampede: сколько раз и с каким шагом ждать чужую генерацию. */
    private const LOCK_WAIT_ATTEMPTS = 30;
    private const LOCK_WAIT_MICROSECONDS = 100_000; // суммарно ~3 секунды

    /**
     * Ленивая генерация с защитой от cache stampede: после сброса кэша
     * значение генерирует только первый пришедший поток (flock на lock-файле),
     * остальные одновременные запросы ждут готовый кэш (usleep) вместо того,
     * чтобы лавиной нагружать MySQL и CssScoper.
     *
     * @param callable():mixed $callback
     * @param int $ttl если > 0 — максимальный возраст записи в секундах (по
     *                 истечении кэш считается устаревшим и пересобирается).
     */
    public static function remember(string $key, callable $callback, int $ttl = 0): mixed
    {
        $cached = self::getFresh($key, $ttl);
        if ($cached !== null) {
            return $cached;
        }

        $lockPath = self::pathFor($key) . '.lock';
        $dir = dirname($lockPath);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return $callback(); // ФС недоступна — работаем без кэша
        }
        $lock = @fopen($lockPath, 'c');
        if ($lock === false) {
            return $callback();
        }

        try {
            if (flock($lock, LOCK_EX | LOCK_NB)) {
                // Мы — генератор. Перепроверка: кэш мог появиться, пока брали lock.
                $cached = self::getFresh($key, $ttl);
                if ($cached !== null) {
                    return $cached;
                }
                $value = $callback();
                self::put($key, $value);

                return $value;
            }

            // Генерирует другой поток — ждём готовый кэш.
            for ($i = 0; $i < self::LOCK_WAIT_ATTEMPTS; $i++) {
                usleep(self::LOCK_WAIT_MICROSECONDS);
                $cached = self::get($key);
                if ($cached !== null) {
                    return $cached;
                }
            }

            // Генератор завис/упал — не блокируем посетителя, считаем сами.
            $value = $callback();
            self::put($key, $value);

            return $value;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public static function forget(string $key): void
    {
        $path = self::pathFor($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Инвалидация группы: удаляет поддиректорию, соответствующую префиксу
     * (например, "page:5" -> storage/cache/page/5).
     */
    public static function forgetPrefix(string $prefix): void
    {
        $segments = array_map(
            static fn ($s) => preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $s) ?? '_',
            explode(':', $prefix)
        );
        $target = self::dir() . '/' . implode('/', $segments);
        self::removeRecursive($target);

        // Контент страниц изменился — очищаем и внешний CDN-кэш (Cloudflare),
        // если интеграция включена. Безопасно: не чаще раза за запрос и no-op,
        // когда выключено.
        if (str_starts_with($prefix, 'page:')) {
            Cloudflare::purgeSite();
        }
    }

    public static function flush(): void
    {
        self::removeRecursive(self::dir());
    }

    private static function removeRecursive(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        $items = scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            self::removeRecursive($path . '/' . $item);
        }
        @rmdir($path);
    }
}
