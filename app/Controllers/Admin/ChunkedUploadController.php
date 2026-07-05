<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\RateLimiter;
use App\Core\Uploader;

/**
 * Приём файлов по частям (chunked upload) через нативный JS File API. Позволяет
 * загружать большие файлы (видео, PDF-презентации) в обход ограничений
 * upload_max_filesize / post_max_size на дешёвых хостингах: каждый чанк —
 * небольшой отдельный запрос, сервер дописывает их в один файл и собирает.
 */
final class ChunkedUploadController
{
    private const MAX_ASSEMBLED_BYTES = 200 * 1024 * 1024; // 200 МБ

    public function chunk(): void
    {
        Auth::requireLogin();

        header('Content-Type: application/json; charset=UTF-8');

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            $this->json(['ok' => false, 'error' => 'CSRF token mismatch'], 419);
        }

        // Ограничение частоты чанков: до 600 чанков / 5 минут на пользователя
        // (хватает на несколько параллельных 200-МБ загрузок, но отсекает
        // злоупотребление записью на диск).
        if (!RateLimiter::throttle('chunk', 'user:' . (int) Auth::id(), 600, 5)) {
            $this->json(['ok' => false, 'error' => 'Слишком много запросов, повторите позже.'], 429);
        }

        $uploadId = preg_replace('/[^a-f0-9]/', '', strtolower((string) ($_POST['upload_id'] ?? '')));
        $index = (int) ($_POST['index'] ?? -1);
        $total = (int) ($_POST['total'] ?? 0);
        $name = (string) ($_POST['name'] ?? '');
        $accessType = ($_POST['access_type'] ?? 'public') === 'protected' ? 'protected' : 'public';

        if (strlen($uploadId) < 8 || $index < 0 || $total < 1 || $index >= $total || $name === '') {
            $this->json(['ok' => false, 'error' => 'Некорректные параметры чанка'], 400);
        }

        if (empty($_FILES['chunk']) || ($_FILES['chunk']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || !is_uploaded_file($_FILES['chunk']['tmp_name'])) {
            $this->json(['ok' => false, 'error' => 'Чанк не получен'], 400);
        }

        $dir = APP_ROOT . '/storage/cache/chunks';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $this->json(['ok' => false, 'error' => 'Нет каталога для сборки'], 500);
        }
        $partPath = $dir . '/' . $uploadId . '.part';

        // Первый чанк начинает файл заново.
        if ($index === 0 && is_file($partPath)) {
            @unlink($partPath);
        }

        // Дописываем чанк в общий файл.
        $in = fopen($_FILES['chunk']['tmp_name'], 'rb');
        $out = fopen($partPath, 'ab');
        if ($in === false || $out === false) {
            $this->json(['ok' => false, 'error' => 'Ошибка записи чанка'], 500);
        }
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);

        // Ограничение суммарного размера на лету (защита от переполнения).
        if (filesize($partPath) > self::MAX_ASSEMBLED_BYTES) {
            @unlink($partPath);
            $this->json(['ok' => false, 'error' => 'Файл превышает максимальный размер 200 МБ'], 413);
        }

        // Не последний чанк — подтверждаем приём и ждём следующий.
        if ($index < $total - 1) {
            $this->json(['ok' => true, 'received' => $index]);
        }

        // Последний чанк — финализируем.
        try {
            $file = Uploader::storeFromPath(
                $partPath,
                $name,
                (int) filesize($partPath),
                $accessType,
                Auth::id(),
                false,
                self::MAX_ASSEMBLED_BYTES
            );
        } catch (\Throwable $e) {
            @unlink($partPath);
            $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
        }

        // storeFromPath переименовывает part-файл в итоговый; подчистим, если остался.
        if (is_file($partPath)) {
            @unlink($partPath);
        }

        $this->json(['ok' => true, 'done' => true, 'file_id' => (int) $file['id'], 'name' => $file['original_name']]);
    }

    private function json(array $payload, int $code = 200): never
    {
        http_response_code($code);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
