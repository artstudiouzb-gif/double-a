<?php

declare(strict_types=1);

namespace App\Controllers\Repo;

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\RepoAuth;
use App\Core\TOTP;
use App\Core\View;
use App\Models\RepoFile;
use App\Models\RepoUser;

/**
 * Портал файлового хранилища: общий список файлов (все авторизованные видят
 * все файлы), поиск, фильтр по категории, защищённое скачивание и
 * самостоятельное управление 2FA.
 */
final class PortalController
{
    public function index(): void
    {
        RepoAuth::requireLogin();

        $query = trim((string) ($_GET['q'] ?? ''));
        $category = trim((string) ($_GET['category'] ?? ''));

        View::render('repo/index', [
            'files' => RepoFile::all($query, $category),
            'categories' => RepoFile::categories(),
            'query' => $query,
            'category' => $category,
            'repoUser' => RepoAuth::user(),
        ]);
    }

    public function download(array $params): void
    {
        RepoAuth::requireLogin();

        $id = (int) ($params['id'] ?? 0);
        $file = RepoFile::findById($id);
        if ($file === null) {
            http_response_code(404);
            exit('Файл не найден.');
        }

        // Мягкий лимит на частоту скачиваний с одной сессии/IP (анти-выкачивание).
        RateLimiter::throttle('repo_download', (string) RepoAuth::id(), 120, 5);

        $expectedBase = realpath(RepoFile::basePath());
        $fullPath = $expectedBase !== false ? realpath($expectedBase . '/' . $file['stored_name']) : false;

        if ($fullPath === false || $expectedBase === false || !str_starts_with($fullPath, $expectedBase) || !is_file($fullPath)) {
            http_response_code(404);
            exit('Файл не найден.');
        }

        RepoFile::incrementDownload($id);
        Logger::security('Скачивание файла из репозитория', [
            'file_id' => $id,
            'repo_user' => (string) ($_SESSION['repo_username'] ?? ''),
        ]);

        $mime = $file['mime_type'] !== '' ? $file['mime_type'] : 'application/octet-stream';
        $downloadName = $file['original_name'] !== '' ? basename((string) $file['original_name']) : ('file-' . $id);

        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
        header('Content-Length: ' . (string) filesize($fullPath));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header("Content-Security-Policy: default-src 'none'; sandbox");

        readfile($fullPath);
        exit;
    }

    public function security(): void
    {
        RepoAuth::requireLogin();

        $user = RepoAuth::user();
        $setupSecret = null;
        $otpauthUri = null;

        if ((int) ($user['totp_enabled'] ?? 0) !== 1) {
            if (empty($_SESSION['repo_totp_setup_secret'])) {
                $_SESSION['repo_totp_setup_secret'] = TOTP::generateSecret();
            }
            $setupSecret = $_SESSION['repo_totp_setup_secret'];
            $issuer = (string) (Config::get('app.url') ?: 'ArtStudio');
            $otpauthUri = TOTP::provisioningUri($setupSecret, (string) $user['username'], 'Файловый портал');
        }

        View::render('repo/security', [
            'repoUser' => $user,
            'setupSecret' => $setupSecret,
            'otpauthUri' => $otpauthUri,
            'error' => null,
        ]);
    }

    public function enableTotp(): void
    {
        RepoAuth::requireLogin();
        Csrf::verifyRequest();

        $user = RepoAuth::user();
        $secret = $_SESSION['repo_totp_setup_secret'] ?? null;
        $code = preg_replace('/\s+/', '', (string) ($_POST['code'] ?? ''));

        if (!$secret || !TOTP::verify((string) $secret, (string) $code)) {
            $issuer = (string) (Config::get('app.url') ?: 'ArtStudio');
            View::render('repo/security', [
                'repoUser' => $user,
                'setupSecret' => $secret,
                'otpauthUri' => $secret ? TOTP::provisioningUri((string) $secret, (string) $user['username'], 'Файловый портал') : null,
                'error' => 'Неверный код. Убедитесь, что время на устройстве синхронизировано.',
            ]);
            return;
        }

        RepoUser::enableTotp((int) $user['id'], (string) $secret);
        unset($_SESSION['repo_totp_setup_secret']);
        Flash::success('Двухфакторная аутентификация включена.');
        header('Location: /repo/security');
        exit;
    }

    public function disableTotp(): void
    {
        RepoAuth::requireLogin();
        Csrf::verifyRequest();

        $user = RepoAuth::user();
        RepoUser::disableTotp((int) $user['id']);
        Flash::success('Двухфакторная аутентификация отключена.');
        header('Location: /repo/security');
        exit;
    }
}
