<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Uploader;
use App\Core\View;
use App\Models\FileEntry;

final class FileController
{
    public function index(): void
    {
        Auth::requireLogin();
        View::render('admin/files/index', ['items' => FileEntry::all()]);
    }

    public function upload(): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        $accessType = ($_POST['access_type'] ?? 'public') === 'protected' ? 'protected' : 'public';

        if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            Flash::error('Выберите файл для загрузки.');
            header('Location: /admin/files');
            exit;
        }

        try {
            Uploader::store($_FILES['file'], $accessType, Auth::id());
            Flash::success('Файл загружен.');
        } catch (\RuntimeException $e) {
            Flash::error($e->getMessage());
        }

        header('Location: /admin/files');
        exit;
    }

    public function destroy(array $params): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        $file = FileEntry::findById((int) $params['id']);
        if ($file) {
            $basePath = $file['access_type'] === 'protected'
                ? Config::get('paths.protected_uploads')
                : Config::get('paths.public_uploads');
            $path = rtrim((string) $basePath, '/') . '/' . $file['stored_name'];
            if (is_file($path)) {
                unlink($path);
            }
            FileEntry::delete((int) $file['id']);
            Flash::success('Файл удалён.');
        }

        header('Location: /admin/files');
        exit;
    }

    public function regenerateToken(array $params): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        FileEntry::regenerateToken((int) $params['id']);
        Flash::success('Токен доступа обновлён.');
        header('Location: /admin/files');
        exit;
    }
}
