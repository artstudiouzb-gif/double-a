<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Каскадная очистка файлов-сирот. При окончательном (физическом) удалении
 * страницы или новости собирает привязанные медиафайлы и удаляет их с диска —
 * но только если файл больше нигде не используется (в других блоках, новостях,
 * проектах, команде или настройках).
 */
final class MediaCleaner
{
    /**
     * Медиа, на которые ссылается страница (через её блоки, все языки).
     * @return array<int, string> публичные URL файлов
     */
    public static function collectForPage(int $pageId): array
    {
        $refs = [];
        $stmt = Database::pdo()->prepare('SELECT data FROM blocks WHERE page_id = :id');
        $stmt->execute([':id' => $pageId]);
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $json) {
            foreach (self::extractPaths((string) $json) as $path) {
                $refs[$path] = true;
            }
        }

        return array_keys($refs);
    }

    /**
     * @return array<int, string>
     */
    public static function collectForNews(array $news): array
    {
        $paths = [];
        if (!empty($news['image'])) {
            foreach (self::extractPaths((string) $news['image']) as $p) {
                $paths[$p] = true;
            }
        }

        // Фотографии галереи новости (этап 12.1). Вызывать ДО force-delete,
        // пока строки news_images ещё существуют.
        if (!empty($news['id'])) {
            foreach (\App\Models\NewsImage::forNews((int) $news['id']) as $img) {
                foreach (self::extractPaths((string) $img['path']) as $p) {
                    $paths[$p] = true;
                }
            }
        }

        return array_keys($paths);
    }

    /**
     * Удаляет файлы, если они не используются больше нигде. Вызывать ПОСЛЕ
     * удаления самой сущности (её блоки уже удалены каскадом), чтобы проверка
     * ссылок не учитывала удаляемый объект.
     *
     * @param array<int, string> $candidatePaths
     */
    public static function purgeUnreferenced(array $candidatePaths): void
    {
        foreach ($candidatePaths as $publicUrl) {
            if (self::isReferenced($publicUrl)) {
                continue;
            }
            self::deletePhysical($publicUrl);
        }
    }

    public static function isReferenced(string $publicUrl): bool
    {
        return self::referenceCount($publicUrl) > 0;
    }

    /**
     * Число упоминаний файла во всех таблицах системы (задача 90 —
     * переиспользование файлов). Файл удаляется с диска только при нуле.
     */
    public static function referenceCount(string $publicUrl): int
    {
        if ($publicUrl === '') {
            return 0;
        }
        $pdo = Database::pdo();
        // Для JSON/HTML-полей ищем по имени файла: в JSON слэши экранируются
        // (\/uploads\/...), поэтому поиск по полному пути даёт ложные нули.
        // Имена файлов — случайные 32-hex, коллизии исключены.
        $like = '%' . basename($publicUrl) . '%';
        $total = 0;

        // LIKE — для полей, где путь встречается внутри JSON/HTML.
        $likeQueries = [
            'SELECT COUNT(*) FROM blocks WHERE data LIKE :v',
            'SELECT COUNT(*) FROM news WHERE content LIKE :v',
            'SELECT COUNT(*) FROM news_translations WHERE content LIKE :v',
        ];
        // Точное совпадение — для отдельных полей-ссылок.
        $exactQueries = [
            'SELECT COUNT(*) FROM news WHERE image = :exact',
            'SELECT COUNT(*) FROM news_images WHERE path = :exact',
            'SELECT COUNT(*) FROM projects WHERE cover_image = :exact',
            'SELECT COUNT(*) FROM project_images WHERE file_path = :exact',
            'SELECT COUNT(*) FROM team_members WHERE photo = :exact',
            'SELECT COUNT(*) FROM settings WHERE `value` = :exact',
        ];

        foreach ($likeQueries as $sql) {
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':v', $like);
                $stmt->execute();
                $total += (int) $stmt->fetchColumn();
            } catch (\Throwable $e) {
                Logger::error('referenceCount (like) failed: ' . $e->getMessage());
            }
        }
        foreach ($exactQueries as $sql) {
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':exact', $publicUrl);
                $stmt->execute();
                $total += (int) $stmt->fetchColumn();
            } catch (\Throwable $e) {
                Logger::error('referenceCount (exact) failed: ' . $e->getMessage());
            }
        }

        return $total;
    }

    private static function deletePhysical(string $publicUrl): void
    {
        $baseUrl = rtrim((string) Config::get('paths.public_uploads_url'), '/');
        $baseDir = (string) Config::get('paths.public_uploads');

        // Только файлы из публичной папки загрузок.
        if (!str_starts_with($publicUrl, $baseUrl . '/')) {
            return;
        }
        $relative = ltrim(substr($publicUrl, strlen($baseUrl)), '/');
        // Защита от path traversal.
        if ($relative === '' || str_contains($relative, '..')) {
            return;
        }

        $expectedBase = realpath($baseDir);
        $fullPath = $expectedBase !== false ? realpath($baseDir . '/' . $relative) : false;
        if ($fullPath === false || $expectedBase === false || !str_starts_with($fullPath, $expectedBase)) {
            return;
        }

        @unlink($fullPath);

        // Удаляем сгенерированные WebP-варианты (name.webp, name-1600.webp, name-800.webp).
        $base = preg_replace('/\.[^.]+$/', '', $fullPath) ?? $fullPath;
        foreach (['.webp', '-1600.webp', '-800.webp'] as $suffix) {
            $variant = $base . $suffix;
            if (is_file($variant)) {
                @unlink($variant);
            }
        }
    }

    /**
     * Извлекает публичные пути к загрузкам из произвольной строки/JSON.
     * @return array<int, string>
     */
    private static function extractPaths(string $haystack): array
    {
        // В JSON слэши экранируются (\/uploads\/public\/...) — нормализуем.
        $haystack = str_replace('\\/', '/', $haystack);
        $baseUrl = preg_quote(rtrim((string) Config::get('paths.public_uploads_url'), '/'), '#');
        if (preg_match_all('#' . $baseUrl . '/[A-Za-z0-9_./-]+#', $haystack, $m)) {
            return array_values(array_unique($m[0]));
        }

        return [];
    }
}
