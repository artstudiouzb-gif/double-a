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
use App\Models\RepoCategory;
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
        $category = (int) ($_GET['category'] ?? 0);

        $all = RepoFile::all();
        // Популярные и последние — для боковых колонок витрины.
        $popular = $all;
        usort($popular, static fn (array $a, array $b) => (int) $b['download_count'] <=> (int) $a['download_count']);

        View::render('repo/index', [
            'files' => RepoFile::all($query, $category),
            'categories' => RepoCategory::flatOptions(),
            'query' => $query,
            'category' => $category,
            'repoUser' => RepoAuth::user(),
            'totalCount' => count($all),
            'popular' => array_slice($popular, 0, 5),
            'latest' => array_slice($all, 0, 5),
        ]);
    }

    /** Загрузка файла пользователем портала: публикуется после одобрения админом. */
    public function upload(): void
    {
        RepoAuth::requireLogin();
        Csrf::verifyRequest();

        // Анти-флуд: не чаще 10 загрузок за 10 минут с одной учётки.
        if (!RateLimiter::throttle('repo_upload', (string) RepoAuth::id(), 600, 10)) {
            Flash::error('Слишком много загрузок. Повторите позже.');
            header('Location: /repo');
            exit;
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $categoryId = $categoryId > 0 && RepoCategory::findById($categoryId) !== null ? $categoryId : null;
        $file = $_FILES['file'] ?? null;

        if ($title === '') {
            Flash::error('Укажите название файла.');
        } elseif (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            Flash::error('Выберите файл для загрузки.');
        } else {
            try {
                RepoFile::store($file, $title, $description, $categoryId, null, RepoAuth::id(), 'pending');
                Logger::security('Файл отправлен на модерацию в репозиторий', [
                    'repo_user' => (string) ($_SESSION['repo_username'] ?? ''),
                ]);
                Flash::success('Файл отправлен. Он появится на портале после одобрения администратором.');
            } catch (\Throwable $e) {
                Flash::error('Не удалось загрузить файл: ' . $e->getMessage());
            }
        }
        header('Location: /repo');
        exit;
    }

    public function download(array $params): void
    {
        RepoAuth::requireLogin();

        $id = (int) ($params['id'] ?? 0);
        $file = RepoFile::findById($id);
        if ($file === null || (($file['status'] ?? 'approved') !== 'approved')) {
            http_response_code(404);
            exit('Файл не найден.');
        }

        // Мягкий лимит на частоту скачиваний с одной сессии/IP (анти-выкачивание).
        if (!RateLimiter::throttle('repo_download', (string) RepoAuth::id(), 120, 5)) {
            http_response_code(429);
            header('Retry-After: 300');
            exit('Слишком много скачиваний. Повторите позже.');
        }

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
            $otpauthUri = TOTP::provisioningUri($setupSecret, (string) $user['username'], self::totpIssuer());
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
            View::render('repo/security', [
                'repoUser' => $user,
                'setupSecret' => $secret,
                'otpauthUri' => $secret ? TOTP::provisioningUri((string) $secret, (string) $user['username'], self::totpIssuer()) : null,
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

    /**
     * Issuer для otpauth-URI. Только короткий ASCII (домен сайта): кириллица
     * раздувает URI percent-кодированием втрое и не помещается в компактный
     * QR-генератор (QrCode, максимум ~108 байт).
     */
    private static function totpIssuer(): string
    {
        $host = (string) (parse_url((string) Config::get('app.url'), PHP_URL_HOST) ?: '');

        return $host !== '' && preg_match('/^[\x21-\x7E]{1,40}$/', $host) ? $host : 'Portal';
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
