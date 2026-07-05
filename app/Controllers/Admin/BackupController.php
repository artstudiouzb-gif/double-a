<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Backup;
use App\Core\Csrf;
use App\Core\Flash;

final class BackupController
{
    public function create(): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        try {
            $path = Backup::create();
        } catch (\Throwable $e) {
            Flash::error('Не удалось создать бэкап: ' . $e->getMessage());
            header('Location: /admin/settings');
            exit;
        }

        // Отдаём архив на скачивание и удаляем его с диска после отправки,
        // чтобы бэкапы не копились в storage без контроля.
        $filename = basename($path);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('X-Content-Type-Options: nosniff');

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        readfile($path);
        @unlink($path);
        exit;
    }
}
