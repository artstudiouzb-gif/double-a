<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\FileEntry;
use RuntimeException;

/**
 * Общая точка загрузки файлов для файлового менеджера и полей изображений
 * (обложка проекта, фото сотрудника, логотип и т.п.). Работает только с
 * $_FILES-подобным массивом, генерирует случайное имя на диске и проверяет
 * реальный MIME-тип содержимого (а не расширение из имени файла).
 */
final class Uploader
{
    private const MAX_SIZE_BYTES = 20 * 1024 * 1024; // 20 МБ

    // Disk Space Guard: минимум свободного места, ниже которого загрузки
    // блокируются (максимум из 500 МБ и 5% от общего объёма диска).
    private const MIN_FREE_BYTES = 500 * 1024 * 1024;
    private const MIN_FREE_RATIO = 0.05;

    private const ALLOWED = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'zip' => 'application/zip',
        'txt' => 'text/plain',
        'woff2' => 'font/woff2',
        'woff' => 'font/woff',
        'mp4' => 'video/mp4',
    ];

    // Типы, для которых finfo часто возвращает application/octet-stream —
    // строгую проверку MIME по расширению для них не применяем.
    private const LENIENT_MIME = ['svg', 'woff2', 'woff'];

    /**
     * @param array $fileInput один элемент $_FILES, например $_FILES['file']
     * @return array файл-запись из таблицы files (с id)
     */
    public static function store(array $fileInput, string $accessType, ?int $uploadedBy): array
    {
        if (($fileInput['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Ошибка загрузки файла.');
        }

        if (!is_uploaded_file($fileInput['tmp_name'])) {
            throw new RuntimeException('Некорректный файл.');
        }

        if ((int) $fileInput['size'] > self::MAX_SIZE_BYTES) {
            throw new RuntimeException('Файл превышает максимальный размер 20 МБ.');
        }

        return self::storeFromPath(
            (string) $fileInput['tmp_name'],
            (string) $fileInput['name'],
            (int) $fileInput['size'],
            $accessType,
            $uploadedBy,
            true
        );
    }

    /**
     * Сохраняет файл из произвольного пути (используется загрузкой из $_FILES
     * и сборкой чанковой загрузки). Общая валидация, санитизация, оптимизация
     * и запись в БД.
     *
     * @param bool $isUploadedFile true если источник — временный файл PHP-загрузки
     * @return array файл-запись из таблицы files
     */
    public static function storeFromPath(
        string $sourcePath,
        string $originalName,
        int $size,
        string $accessType,
        ?int $uploadedBy,
        bool $isUploadedFile,
        int $maxSize = self::MAX_SIZE_BYTES
    ): array {
        if (!is_file($sourcePath)) {
            throw new RuntimeException('Файл не найден.');
        }
        if ($size > $maxSize) {
            throw new RuntimeException('Файл превышает максимальный размер ' . (int) round($maxSize / (1024 * 1024)) . ' МБ.');
        }

        self::assertDiskSpace($accessType);

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED[$extension])) {
            throw new RuntimeException('Недопустимый тип файла: .' . $extension);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = (string) $finfo->file($sourcePath);
        $expectedMime = self::ALLOWED[$extension];
        if (!self::mimeMatches($extension, $detectedMime, $sourcePath)) {
            throw new RuntimeException('Содержимое файла не соответствует расширению.');
        }

        $accessType = $accessType === 'protected' ? 'protected' : 'public';
        $storedName = bin2hex(random_bytes(16)) . '.' . $extension;

        $basePath = $accessType === 'protected'
            ? Config::get('paths.protected_uploads')
            : Config::get('paths.public_uploads');

        if (!is_dir($basePath) && !mkdir($basePath, 0755, true) && !is_dir($basePath)) {
            throw new RuntimeException('Не удалось создать директорию для загрузки.');
        }

        $destination = rtrim((string) $basePath, '/') . '/' . $storedName;

        $moved = $isUploadedFile
            ? move_uploaded_file($sourcePath, $destination)
            : rename($sourcePath, $destination);
        if (!$moved) {
            throw new RuntimeException('Не удалось сохранить файл на диске.');
        }

        if ($extension === 'svg') {
            self::sanitizeSvgFile($destination);
        }
        if (in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            self::optimizeImage($destination);
        }

        $accessToken = $accessType === 'protected' ? bin2hex(random_bytes(32)) : null;

        $id = FileEntry::create([
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'mime_type' => $expectedMime,
            'size' => $size,
            'access_type' => $accessType,
            'access_token' => $accessToken,
            'uploaded_by' => $uploadedBy,
        ]);

        return FileEntry::findById($id);
    }

    /**
     * Проверяет фактический MIME. Для MP4 разные версии libmagic возвращают
     * video/mp4, application/mp4 или octet-stream, поэтому дополнительно
     * проверяем сигнатуру ISO Base Media (box `ftyp`).
     */
    public static function mimeMatches(string $extension, string $detectedMime, string $path): bool
    {
        $extension = strtolower($extension);
        if (!isset(self::ALLOWED[$extension])) {
            return false;
        }
        if (in_array($extension, self::LENIENT_MIME, true)) {
            return true;
        }
        if ($extension !== 'mp4') {
            return $detectedMime === self::ALLOWED[$extension];
        }
        if (!in_array($detectedMime, ['video/mp4', 'application/mp4', 'application/octet-stream'], true)) {
            return false;
        }

        $header = @file_get_contents($path, false, null, 0, 12);

        return is_string($header) && strlen($header) >= 12 && substr($header, 4, 4) === 'ftyp';
    }

    /**
     * Санитизация SVG: удаляет опасные элементы и атрибуты (скрипты,
     * обработчики событий, javascript:-URI, внешние ссылки). Работает через
     * DOMDocument (нативное расширение, без сторонних зависимостей).
     */
    /**
     * Disk Space Guard: блокирует загрузку при нехватке свободного места на
     * диске, защищая сервер и БД от падения. Порог — максимум из 500 МБ и 5%
     * от общего объёма диска.
     */
    public static function assertDiskSpace(string $accessType): void
    {
        $dir = $accessType === 'protected'
            ? (string) Config::get('paths.protected_uploads')
            : (string) Config::get('paths.public_uploads');

        // Берём существующий родительский каталог для замера.
        $probe = $dir;
        while ($probe !== '' && !is_dir($probe)) {
            $parent = dirname($probe);
            if ($parent === $probe) {
                break;
            }
            $probe = $parent;
        }

        $free = @disk_free_space($probe ?: '/');
        $total = @disk_total_space($probe ?: '/');
        if ($free === false || $total === false) {
            return; // не удалось определить — не блокируем
        }

        $threshold = max(self::MIN_FREE_BYTES, (int) ($total * self::MIN_FREE_RATIO));
        if ($free < $threshold) {
            Logger::warning(
                sprintf('Disk Space Guard: свободно %d Б при пороге %d Б — загрузка заблокирована.', $free, $threshold),
                ['free' => $free, 'threshold' => $threshold]
            );
            throw new RuntimeException('Недостаточно свободного места на сервере. Загрузка временно недоступна — освободите место.');
        }
    }

    /**
     * Создаёт WebP-версию и адаптивные разрешения (desktop/mobile) для
     * растрового изображения через нативный GD. Best-effort: любые ошибки
     * не мешают основной загрузке. Побочные файлы кладутся рядом:
     *   name.jpg -> name.webp (полный размер),
     *              name-1600.webp (desktop, если исходник шире),
     *              name-800.webp  (mobile).
     */
    /** Жёсткий предел разрешения для декодирования GD (~40 Мп). */
    private const MAX_IMAGE_PIXELS = 40_000_000;

    /** Запас памяти, который оставляем процессу после декодирования (байт). */
    private const MEMORY_HEADROOM = 16 * 1024 * 1024;

    /**
     * Переводит ini-значение (например «256M», «1G», «-1») в байты.
     * Чистая функция для Memory Limit Guard.
     */
    public static function parseIniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return PHP_INT_MAX; // без лимита
        }
        $unit = strtolower(substr($value, -1));
        $num = (int) $value;

        return match ($unit) {
            'g' => $num * 1024 * 1024 * 1024,
            'm' => $num * 1024 * 1024,
            'k' => $num * 1024,
            default => (int) $value,
        };
    }

    /**
     * Оценка памяти на декодирование картинки в GD: ~5 байт на пиксель
     * (truecolor RGBA + накладные расходы структуры).
     */
    public static function estimateImageMemory(int $width, int $height): int
    {
        return $width * $height * 5;
    }

    /**
     * Memory Limit Guard (getimagesize до декодирования): можно ли безопасно
     * создать GD-ресурс такого разрешения при заданных лимите и текущем
     * потреблении. Отдельные аргументы — для тестируемости без ini_set.
     */
    public static function imageDecodable(int $width, int $height, ?int $memoryLimit = null, ?int $memoryUsed = null): bool
    {
        if ($width < 1 || $height < 1 || $width * $height > self::MAX_IMAGE_PIXELS) {
            return false;
        }
        $limit = $memoryLimit ?? self::parseIniBytes((string) ini_get('memory_limit'));
        $used = $memoryUsed ?? memory_get_usage(true);

        return self::estimateImageMemory($width, $height) <= $limit - $used - self::MEMORY_HEADROOM;
    }

    /** Качество WebP из настроек производительности (40–95, по умолчанию 82). */
    private static function webpQuality(): int
    {
        try {
            $q = (int) \App\Models\Setting::get('perf_webp_quality', '82');
        } catch (\Throwable) {
            $q = 82;
        }

        return max(40, min(95, $q));
    }

    /** Макс. ширина хранимого оригинала (px) из настроек, по умолчанию 2560. */
    public static function originalMaxWidth(): int
    {
        try {
            $w = (int) \App\Models\Setting::get('perf_image_max_width', '2560');
        } catch (\Throwable) {
            $w = 2560;
        }

        return max(1200, min(4000, $w));
    }

    /** $downscaleOriginal=false используется для безопасной миграции старых файлов. */
    public static function optimizeImage(string $path, bool $downscaleOriginal = true): void
    {
        if (!extension_loaded('gd')) {
            return;
        }

        try {
            $info = @getimagesize($path);
            if ($info === false) {
                return;
            }
            [$width, $height] = $info;
            $type = $info[2];

            // Memory Limit Guard: фото экстремального разрешения (например,
            // 8000×6000 прямо с камеры) не декодируем — иначе Fatal Error
            // уронит запрос. Оригинал остаётся как есть, без WebP-версий.
            if (!self::imageDecodable((int) $width, (int) $height)) {
                Logger::warning('Image optimize пропущен: разрешение слишком велико для памяти', [
                    'file' => basename($path),
                    'width' => $width,
                    'height' => $height,
                    'memory_limit' => (string) ini_get('memory_limit'),
                ]);
                return;
            }

            $src = match ($type) {
                IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
                IMAGETYPE_PNG => @imagecreatefrompng($path),
                default => null,
            };
            if (!$src) {
                return;
            }

            if ($type === IMAGETYPE_PNG) {
                imagepalettetotruecolor($src);
                imagealphablending($src, true);
                imagesavealpha($src, true);
            }

            $base = preg_replace('/\.[^.]+$/', '', $path) ?? $path;
            $quality = self::webpQuality();

            // WebP полного размера.
            @imagewebp($src, $base . '.webp', $quality);

            // Адаптивные размеры.
            foreach ([1600, 800] as $targetWidth) {
                if ($width <= $targetWidth) {
                    continue;
                }
                $targetHeight = (int) round($height * ($targetWidth / $width));
                $resized = imagecreatetruecolor($targetWidth, $targetHeight);
                if ($type === IMAGETYPE_PNG) {
                    imagealphablending($resized, false);
                    imagesavealpha($resized, true);
                }
                imagecopyresampled($resized, $src, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
                @imagewebp($resized, $base . '-' . $targetWidth . '.webp', $quality);
                imagedestroy($resized);
            }

            // Даунскейл самого оригинала, если он неоправданно большой (фото
            // прямо с телефона — 4000–6000px). WebP-варианты уже созданы выше;
            // уменьшённый оригинал экономит вес страниц и место на диске, при
            // этом работает и там, где картинка задаётся через CSS background.
            $maxW = self::originalMaxWidth();
            if ($downscaleOriginal && $width > $maxW) {
                $newH = (int) round($height * ($maxW / $width));
                $down = imagecreatetruecolor($maxW, $newH);
                if ($type === IMAGETYPE_PNG) {
                    imagealphablending($down, false);
                    imagesavealpha($down, true);
                }
                imagecopyresampled($down, $src, 0, 0, 0, 0, $maxW, $newH, $width, $height);
                if ($type === IMAGETYPE_JPEG) {
                    @imagejpeg($down, $path, 85);
                } else {
                    @imagepng($down, $path);
                }
                imagedestroy($down);
            }

            imagedestroy($src);
        } catch (\Throwable $e) {
            Logger::error('Image optimize failed: ' . $e->getMessage());
        }
    }

    public static function sanitizeSvgFile(string $path): void
    {
        $content = file_get_contents($path);
        if ($content === false || trim($content) === '') {
            return;
        }

        $sanitized = self::sanitizeSvgString($content);
        file_put_contents($path, $sanitized);
    }

    public static function sanitizeSvgString(string $svg): string
    {
        // Быстрая грубая очистка на случай, если DOM не сможет разобрать документ.
        $svg = preg_replace('#<\?php.*?\?>#is', '', $svg) ?? $svg;

        // DOCTYPE вырезаем целиком: объявления <!ENTITY ... SYSTEM "file://...">
        // — это XXE (чтение локальных файлов сервера), а рекурсивные внутренние
        // сущности — DoS («billion laughs»). Легитимному SVG DOCTYPE не нужен.
        // Сущности не разворачиваем (LIBXML_NOENT недопустим — он включает
        // подстановку внешних сущностей); осиротевшие &ref; после удаления
        // DOCTYPE валят loadXML, и документ заменяется безопасной заглушкой.
        $svg = preg_replace('/<!DOCTYPE\s[^>[]*(\[[^\]]*\])?[^>]*>/is', '', $svg) ?? $svg;

        $dangerousTags = ['script', 'foreignObject', 'iframe', 'embed', 'object', 'audio', 'video', 'animate', 'set', 'handler', 'listener'];

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        // LIBXML_NONET запрещает сетевые обращения при разборе.
        $loaded = $dom->loadXML($svg, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded || $dom->documentElement === null) {
            // Не удалось разобрать как XML — возвращаем безопасную заглушку.
            return '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"></svg>';
        }

        $xpath = new \DOMXPath($dom);

        // 1. Удаляем опасные элементы.
        foreach ($dangerousTags as $tag) {
            $nodes = iterator_to_array($dom->getElementsByTagName($tag));
            foreach ($nodes as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        // 2. Чистим атрибуты у всех элементов.
        $allNodes = $xpath->query('//*');
        if ($allNodes !== false) {
            foreach ($allNodes as $node) {
                if (!$node instanceof \DOMElement) {
                    continue;
                }
                $attrs = iterator_to_array($node->attributes ?? []);
                foreach ($attrs as $attr) {
                    $name = strtolower($attr->nodeName);
                    $value = $attr->nodeValue ?? '';

                    // Обработчики событий on* — удаляем целиком.
                    if (str_starts_with($name, 'on')) {
                        $node->removeAttribute($attr->nodeName);
                        continue;
                    }

                    // href / xlink:href / src с javascript:/data:text/html — удаляем.
                    if (in_array($name, ['href', 'xlink:href', 'src'], true)) {
                        $normalized = strtolower(preg_replace('/\s+/', '', $value) ?? '');
                        if (str_starts_with($normalized, 'javascript:')
                            || str_starts_with($normalized, 'data:text/html')
                            || str_starts_with($normalized, 'vbscript:')) {
                            $node->removeAttribute($attr->nodeName);
                            continue;
                        }
                    }

                    // Любое значение атрибута с javascript: внутри (например, style).
                    if (stripos($value, 'javascript:') !== false || stripos($value, 'vbscript:') !== false) {
                        $node->removeAttribute($attr->nodeName);
                    }
                }
            }
        }

        $result = $dom->saveXML($dom->documentElement);

        return $result !== false ? $result : '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"></svg>';
    }
}
