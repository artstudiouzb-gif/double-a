<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\PasswordPolicy;
use App\Core\View;
use App\Models\RepoFile;
use App\Models\RepoUser;

/**
 * Управление защищённым файловым хранилищем из админ-панели: загрузка/удаление
 * файлов и управление учётными записями портала. Только супер-администратор.
 */
final class RepositoryController
{
    public function files(): void
    {
        Auth::requireSuperAdmin();

        $query = trim((string) ($_GET['q'] ?? ''));
        View::render('admin/repository/files', [
            'files' => RepoFile::all($query),
            'query' => $query,
            'categories' => RepoFile::categories(),
        ]);
    }

    public function upload(): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? ''));
        $file = $_FILES['file'] ?? null;

        if ($title === '') {
            Flash::error('Укажите название файла.');
            header('Location: /admin/repository');
            exit;
        }
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            Flash::error('Выберите файл для загрузки.');
            header('Location: /admin/repository');
            exit;
        }

        try {
            RepoFile::store($file, $title, $description, $category, Auth::id());
            Flash::success('Файл загружен в хранилище.');
        } catch (\Throwable $e) {
            Flash::error('Не удалось загрузить файл: ' . $e->getMessage());
        }

        header('Location: /admin/repository');
        exit;
    }

    public function destroyFile(array $params): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        RepoFile::delete((int) ($params['id'] ?? 0));
        Flash::success('Файл удалён из хранилища.');
        header('Location: /admin/repository');
        exit;
    }

    // --- Учётные записи портала ---

    public function users(): void
    {
        Auth::requireSuperAdmin();
        View::render('admin/repository/users', ['users' => RepoUser::all(), 'error' => null]);
    }

    public function storeUser(): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        $username = trim((string) ($_POST['username'] ?? ''));
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $error = null;
        if ($username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Заполните логин и корректный email.';
        } elseif (($pwErrors = PasswordPolicy::validate($password, [$username, $email])) !== []) {
            $error = implode(' ', $pwErrors);
        } elseif (RepoUser::usernameExists($username)) {
            $error = 'Пользователь с таким логином уже существует.';
        } elseif (RepoUser::emailExists($email)) {
            $error = 'Пользователь с таким email уже существует.';
        }

        if ($error !== null) {
            View::render('admin/repository/users', ['users' => RepoUser::all(), 'error' => $error]);
            return;
        }

        RepoUser::create($username, $fullName, $email, $password);
        Flash::success('Учётная запись портала создана. 2FA пользователь может включить сам после входа.');
        header('Location: /admin/repository/users');
        exit;
    }

    public function toggleUser(array $params): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        $id = (int) ($params['id'] ?? 0);
        $user = RepoUser::findById($id);
        if ($user !== null) {
            RepoUser::setActive($id, (int) $user['is_active'] !== 1);
            Flash::success((int) $user['is_active'] === 1 ? 'Доступ отключён.' : 'Доступ включён.');
        }
        header('Location: /admin/repository/users');
        exit;
    }

    public function resetUserPassword(array $params): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        $id = (int) ($params['id'] ?? 0);
        $user = RepoUser::findById($id);
        $password = (string) ($_POST['password'] ?? '');

        if ($user === null) {
            Flash::error('Пользователь не найден.');
        } elseif (($pwErrors = PasswordPolicy::validate($password, [(string) $user['username'], (string) $user['email']])) !== []) {
            Flash::error(implode(' ', $pwErrors));
        } else {
            RepoUser::updatePassword($id, $password);
            RepoUser::disableTotp($id);
            Flash::success('Пароль сброшен. 2FA сброшена — пользователь сможет настроить её заново.');
        }
        header('Location: /admin/repository/users');
        exit;
    }

    public function destroyUser(array $params): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        RepoUser::delete((int) ($params['id'] ?? 0));
        Flash::success('Учётная запись портала удалена.');
        header('Location: /admin/repository/users');
        exit;
    }
}
