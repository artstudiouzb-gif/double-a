<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;

final class AuthController
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            header('Location: /admin');
            exit;
        }

        View::render('admin/auth/login', ['error' => null]);
    }

    public function login(): void
    {
        Csrf::verifyRequest();

        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            View::render('admin/auth/login', ['error' => 'Введите логин и пароль.']);
            return;
        }

        $result = Auth::attemptLogin($username, $password);

        switch ($result['status']) {
            case 'needs_2fa':
                header('Location: /admin/login/2fa');
                exit;
            case 'needs_2fa_setup':
                header('Location: /admin/login/2fa-setup');
                exit;
            case 'locked':
                $minutes = (int) ceil(($result['retry_after'] ?? 0) / 60);
                View::render('admin/auth/login', [
                    'error' => "Слишком много попыток входа. Повторите через {$minutes} мин.",
                ]);
                return;
            default:
                View::render('admin/auth/login', ['error' => 'Неверный логин или пароль.']);
        }
    }

    public function showTwoFactor(): void
    {
        if (empty($_SESSION['pending_user_id'])) {
            header('Location: /admin/login');
            exit;
        }

        View::render('admin/auth/2fa', ['error' => null]);
    }

    public function verifyTwoFactor(): void
    {
        Csrf::verifyRequest();

        if (empty($_SESSION['pending_user_id'])) {
            header('Location: /admin/login');
            exit;
        }

        $code = trim((string) ($_POST['code'] ?? ''));

        if (Auth::completeTwoFactor($code)) {
            header('Location: /admin');
            exit;
        }

        View::render('admin/auth/2fa', ['error' => 'Неверный код. Попробуйте снова.']);
    }

    public function showTwoFactorSetup(): void
    {
        if (empty($_SESSION['pending_user_id'])) {
            header('Location: /admin/login');
            exit;
        }

        $setup = Auth::beginTwoFactorSetup();
        View::render('admin/auth/2fa-setup', [
            'error' => null,
            'secret' => $setup['secret'],
            'uri' => $setup['uri'],
        ]);
    }

    public function confirmTwoFactorSetup(): void
    {
        Csrf::verifyRequest();

        if (empty($_SESSION['pending_user_id'])) {
            header('Location: /admin/login');
            exit;
        }

        $code = trim((string) ($_POST['code'] ?? ''));

        if (Auth::confirmTwoFactorSetup($code)) {
            // Ведём на профиль: там сразу показываются свежие backup-коды.
            header('Location: /admin/profile');
            exit;
        }

        $setup = Auth::beginTwoFactorSetup();
        View::render('admin/auth/2fa-setup', [
            'error' => 'Неверный код подтверждения. Попробуйте снова.',
            'secret' => $setup['secret'],
            'uri' => $setup['uri'],
        ]);
    }

    public function logout(): void
    {
        Csrf::verifyRequest();
        Auth::logout();
        header('Location: /admin/login');
        exit;
    }
}
