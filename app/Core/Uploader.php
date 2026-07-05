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
    ];

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

        $originalName = (string) $fileInput['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!isset(self::ALLOWED[$extension])) {
            throw new RuntimeException('Недопустимый тип файла: .' . $extension);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = (string) $finfo->file($fileInput['tmp_name']);

        // SVG определяется finfo как text/plain или image/svg+xml в зависимости от системы,
        // остальные типы должны строго совпадать с ожидаемым MIME по расширению.
        $expectedMime = self::ALLOWED[$extension];
        if ($extension !== 'svg' && $detectedMime !== $expectedMime) {
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

        $destination = rtrim($basePath, '/') . '/' . $storedName;

        if (!move_uploaded_file($fileInput['tmp_name'], $destination)) {
            throw new RuntimeException('Не удалось сохранить файл на диске.');
        }

        $accessToken = $accessType === 'protected' ? bin2hex(random_bytes(32)) : null;

        $id = FileEntry::create([
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'mime_type' => $expectedMime,
            'size' => (int) $fileInput['size'],
            'access_type' => $accessType,
            'access_token' => $accessToken,
            'uploaded_by' => $uploadedBy,
        ]);

        return FileEntry::findById($id);
    }
}
