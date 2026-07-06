<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;
use App\Core\Uploader;
use RuntimeException;

/**
 * Файл защищённого репозитория. Хранится в storage/protected_uploads/repo/
 * (вне webroot) под случайным именем; отдаётся стримом только после проверки
 * сессии портала (RepoAuth). Загружает только администратор.
 */
final class RepoFile
{
    private const MAX_SIZE_BYTES = 100 * 1024 * 1024; // 100 МБ — документы/архивы

    // Разрешённые расширения репозитория (документы, таблицы, презентации,
    // архивы, изображения). Ключ — расширение, значение — ожидаемый MIME.
    private const ALLOWED = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'odt' => 'application/vnd.oasis.opendocument.text',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        'odp' => 'application/vnd.oasis.opendocument.presentation',
        'txt' => 'text/plain',
        'csv' => 'text/csv',
        'rtf' => 'application/rtf',
        'zip' => 'application/zip',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];

    public static function basePath(): string
    {
        return rtrim((string) Config::get('paths.protected_uploads'), '/') . '/repo';
    }

    /**
     * @param string $query поиск по заголовку/описанию/имени файла
     * @param string $category фильтр по категории ('' — все)
     */
    public static function all(string $query = '', string $category = ''): array
    {
        $sql = 'SELECT * FROM repo_files WHERE 1 = 1';
        $params = [];

        if ($query !== '') {
            $sql .= ' AND (title LIKE :q1 OR description LIKE :q2 OR original_name LIKE :q3 OR category LIKE :q4)';
            $like = '%' . $query . '%';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
            $params[':q4'] = $like;
        }
        if ($category !== '') {
            $sql .= ' AND category = :cat';
            $params[':cat'] = $category;
        }
        $sql .= ' ORDER BY created_at DESC, id DESC';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function count(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM repo_files')->fetchColumn();
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM repo_files WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /** @return list<string> уникальные непустые категории */
    public static function categories(): array
    {
        $rows = Database::pdo()->query(
            "SELECT DISTINCT category FROM repo_files WHERE category <> '' ORDER BY category ASC"
        )->fetchAll(\PDO::FETCH_COLUMN);

        return array_values(array_map('strval', $rows));
    }

    public static function incrementDownload(int $id): void
    {
        $stmt = Database::pdo()->prepare('UPDATE repo_files SET download_count = download_count + 1 WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /**
     * Валидирует и сохраняет загруженный администратором файл, затем создаёт
     * запись. Возвращает id новой записи.
     *
     * @param array $fileInput элемент $_FILES
     */
    public static function store(array $fileInput, string $title, string $description, string $category, ?int $uploadedBy): int
    {
        if (($fileInput['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Ошибка загрузки файла.');
        }
        if (!is_uploaded_file($fileInput['tmp_name'])) {
            throw new RuntimeException('Некорректный файл.');
        }
        $size = (int) $fileInput['size'];
        if ($size > self::MAX_SIZE_BYTES) {
            throw new RuntimeException('Файл превышает максимальный размер 100 МБ.');
        }

        $originalName = (string) $fileInput['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED[$extension])) {
            throw new RuntimeException('Недопустимый тип файла: .' . $extension);
        }

        // Disk Space Guard (переиспользуем проверку из Uploader по protected-пути).
        Uploader::assertDiskSpace('protected');

        $base = self::basePath();
        if (!is_dir($base) && !mkdir($base, 0755, true) && !is_dir($base)) {
            throw new RuntimeException('Не удалось создать директорию хранилища.');
        }

        $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = $base . '/' . $storedName;

        if (!move_uploaded_file((string) $fileInput['tmp_name'], $destination)) {
            throw new RuntimeException('Не удалось сохранить файл на диске.');
        }

        // Определяем реальный MIME содержимого (изображения санитизируем через
        // общий SVG-путь не требуется — SVG в репозиторий не принимаем).
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($destination);
        if ($mime === '') {
            $mime = self::ALLOWED[$extension];
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO repo_files (title, description, category, stored_name, original_name, mime_type, size, uploaded_by, created_at)
             VALUES (:title, :descr, :cat, :stored, :orig, :mime, :size, :by, NOW())'
        );
        $stmt->execute([
            ':title' => $title,
            ':descr' => $description !== '' ? $description : null,
            ':cat' => $category,
            ':stored' => $storedName,
            ':orig' => $originalName,
            ':mime' => $mime,
            ':size' => $size,
            ':by' => $uploadedBy,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public static function delete(int $id): void
    {
        $file = self::findById($id);
        if ($file === null) {
            return;
        }

        // Удаляем файл с диска строго внутри базовой директории (защита от
        // path traversal, аналогично download.php).
        $expectedBase = realpath(self::basePath());
        if ($expectedBase !== false) {
            $full = realpath($expectedBase . '/' . $file['stored_name']);
            if ($full !== false && str_starts_with($full, $expectedBase) && is_file($full)) {
                @unlink($full);
            }
        }

        $stmt = Database::pdo()->prepare('DELETE FROM repo_files WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}
