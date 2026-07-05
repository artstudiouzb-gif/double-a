<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\FileEntry;

/**
 * Универсальная обработка полей "изображение" в формах админки: если
 * загружен файл — сохраняем его через Uploader и используем публичную
 * ссылку, иначе используем вручную вставленный URL (внешний адрес или
 * уже существующее значение при редактировании).
 */
final class ImageField
{
    public static function resolve(string $fileInputName, string $urlInputName, ?string $existingUrl, ?int $uploadedBy): ?string
    {
        if (!empty($_FILES[$fileInputName]) && ($_FILES[$fileInputName]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $file = Uploader::store($_FILES[$fileInputName], 'public', $uploadedBy);
            return FileEntry::publicUrl($file);
        }

        $url = trim((string) ($_POST[$urlInputName] ?? ''));

        return $url !== '' ? $url : $existingUrl;
    }
}
