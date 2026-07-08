<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Менеджер 301/302-редиректов: сохраняет ссылки при переезде со старого сайта.
 * Совпадение по пути (без query-строки); проверяется в Router::dispatch до
 * сопоставления маршрутов, поэтому работает и для «занятых» адресов.
 */
final class Redirect
{
    /**
     * Нормализует исходный путь: принимает и полный URL со старого домена
     * («https://old.site/page?x=1»), и относительный путь. Возвращает путь
     * с ведущим «/» без хвостового «/», либо null для непригодных значений
     * (пусто, корень, /admin — панель редиректить нельзя).
     */
    public static function normalizePath(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $raw) === 1) {
            $raw = (string) (parse_url($raw, PHP_URL_PATH) ?: '/');
        }
        // Отбрасываем query-строку/якорь, если вставили путь с ними.
        $raw = (string) (parse_url($raw, PHP_URL_PATH) ?? '');
        if ($raw === '' || $raw[0] !== '/') {
            $raw = '/' . $raw;
        }
        $raw = rtrim($raw, '/');
        if ($raw === '' || $raw === '/') {
            return null; // корень не редиректим
        }
        if (str_starts_with($raw, '/admin')) {
            return null;
        }

        return mb_substr($raw, 0, 255);
    }

    /**
     * Валидирует целевой адрес: относительный путь («/new-page») или
     * абсолютный http(s)-URL. Иное — null.
     */
    public static function normalizeTarget(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '' || mb_strlen($raw) > 500) {
            return null;
        }
        if ($raw[0] === '/') {
            // Защита от «//evil.site» (протокол-относительный URL).
            return str_starts_with($raw, '//') ? null : $raw;
        }
        if (preg_match('#^https?://[^\s]+$#i', $raw) === 1) {
            return $raw;
        }

        return null;
    }

    /**
     * Целевой URL с переносом query-строки исходного запроса (если у цели
     * нет собственной). Чистая функция.
     */
    public static function buildTarget(string $toUrl, string $queryString): string
    {
        if ($queryString !== '' && !str_contains($toUrl, '?')) {
            return $toUrl . '?' . $queryString;
        }

        return $toUrl;
    }

    /**
     * Разбирает строку импорта: «/старый /новый [код]» или «/старый -> /новый».
     * Возвращает [from, to, code] либо null. Чистая функция.
     *
     * @return array{0: string, 1: string, 2: int}|null
     */
    public static function parseImportLine(string $line): ?array
    {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            return null;
        }
        $parts = preg_split('/\s*->\s*|\s+/', $line) ?: [];
        $parts = array_values(array_filter($parts, static fn (string $p): bool => $p !== ''));
        if (count($parts) < 2) {
            return null;
        }

        $from = self::normalizePath($parts[0]);
        $to = self::normalizeTarget($parts[1]);
        $code = isset($parts[2]) && (int) $parts[2] === 302 ? 302 : 301;
        if ($from === null || $to === null || $from === $to) {
            return null;
        }

        return [$from, $to, $code];
    }

    /** @return array<int, array<string, mixed>> */
    public static function all(): array
    {
        return Database::pdo()->query(
            'SELECT * FROM redirects ORDER BY from_path ASC'
        )->fetchAll();
    }

    /** Активный редирект для пути (или null). */
    public static function findByPath(string $path): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM redirects WHERE from_path = :p AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([':p' => $path]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Создаёт редирект; false при дубликате from_path или невалидных данных.
     */
    public static function create(string $fromRaw, string $toRaw, int $code = 301): bool
    {
        $from = self::normalizePath($fromRaw);
        $to = self::normalizeTarget($toRaw);
        if ($from === null || $to === null || $from === $to) {
            return false;
        }
        $code = $code === 302 ? 302 : 301;

        try {
            $stmt = Database::pdo()->prepare(
                'INSERT INTO redirects (from_path, to_url, code, created_at) VALUES (:f, :t, :c, NOW())'
            );
            $stmt->execute([':f' => $from, ':t' => $to, ':c' => $code]);
        } catch (\PDOException) {
            return false; // дубликат from_path (уникальный ключ)
        }

        return true;
    }

    /**
     * Массовый импорт списком строк. Возвращает [добавлено, пропущено].
     *
     * @return array{0: int, 1: int}
     */
    public static function import(string $text): array
    {
        $added = 0;
        $skipped = 0;
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            if (trim($line) === '' || str_starts_with(trim($line), '#')) {
                continue;
            }
            $parsed = self::parseImportLine($line);
            if ($parsed !== null && self::create($parsed[0], $parsed[1], $parsed[2])) {
                $added++;
            } else {
                $skipped++;
            }
        }

        return [$added, $skipped];
    }

    public static function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM redirects WHERE id = :id')->execute([':id' => $id]);
    }

    public static function setActive(int $id, bool $active): void
    {
        Database::pdo()->prepare('UPDATE redirects SET is_active = :a WHERE id = :id')
            ->execute([':a' => $active ? 1 : 0, ':id' => $id]);
    }

    /** Счётчик срабатываний (для оценки, какие старые ссылки ещё живут). */
    public static function recordHit(int $id): void
    {
        try {
            Database::pdo()->prepare(
                'UPDATE redirects SET hits = hits + 1, last_hit_at = NOW() WHERE id = :id'
            )->execute([':id' => $id]);
        } catch (\Throwable) {
            // Счётчик не должен ломать редирект.
        }
    }
}
