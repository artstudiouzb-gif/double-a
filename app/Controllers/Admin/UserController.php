<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\View;
use App\Models\User;

/**
 * Управление учётными записями — только для супер-администратора.
 */
final class UserController
{
    public function index(): void
    {
        Auth::requireSuperAdmin();
        View::render('admin/users/index', ['items' => User::all(), 'error' => null]);
    }

    public function store(): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        $username = trim((string) ($_POST['username'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $role = ($_POST['role'] ?? 'editor') === 'admin' ? 'admin' : 'editor';

        $error = null;
        if ($username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Заполните логин и корректный email.';
        } elseif (($pwErrors = \App\Core\PasswordPolicy::validate($password, [$username, $email])) !== []) {
            $error = implode(' ', $pwErrors);
        } elseif (User::findByUsername($username)) {
            $error = 'Пользователь с таким логином уже существует.';
        } elseif (User::emailExists($email)) {
            $error = 'Пользователь с таким email уже существует.';
        }

        if ($error !== null) {
            View::render('admin/users/index', ['items' => User::all(), 'error' => $error]);
            return;
        }

        User::create($username, $email, $password, $role);
        Flash::success('Пользователь создан. Двухфакторная аутентификация будет настроена при первом входе.');
        header('Location: /admin/users');
        exit;
    }

    public function destroy(array $params): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        $id = (int) $params['id'];

        // Нельзя удалить самого себя и последнего пользователя.
        if ($id === Auth::id()) {
            Flash::error('Нельзя удалить собственную учётную запись.');
            header('Location: /admin/users');
            exit;
        }
        if (User::count() <= 1) {
            Flash::error('Нельзя удалить последнего пользователя.');
            header('Location: /admin/users');
            exit;
        }

        User::delete($id);
        Flash::success('Пользователь удалён.');
        header('Location: /admin/users');
        exit;
    }
}
