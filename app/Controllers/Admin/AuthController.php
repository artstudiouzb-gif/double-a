<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\View;

/**
 * Вход в админку: пароль + одноразовый код, который приходит в Telegram от
 * официального канала Verification Codes (Telegram Gateway API).
 */
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

        // Разрез аутентификации в «Журнале действий» (метод AUTH): успех,
        // ожидание 2FA, блокировка и неверные учётные данные — с IP.
        switch ($result['status']) {
            case 'ok':
                \App\Models\AuditLog::auth('login', (int) ($_SESSION['user_id'] ?? 0) ?: null, $username);
                header('Location: /admin');
                exit;
            case 'needs_code':
                \App\Models\AuditLog::auth('login.pending-2fa', (int) ($_SESSION['pending_user_id'] ?? 0) ?: null, $username);
                header('Location: /admin/login/2fa');
                exit;
            case 'send_failed':
                View::render('admin/auth/login', [
                    'error' => 'Не удалось отправить код в Telegram. Проверьте токен шлюза в настройках и телефон пользователя, либо повторите позже.',
                ]);
                return;
            case 'locked':
                \App\Models\AuditLog::auth('login.locked', null, $username);
                $minutes = (int) ceil(($result['retry_after'] ?? 0) / 60);
                View::render('admin/auth/login', [
                    'error' => "Слишком много попыток входа. Повторите через {$minutes} мин.",
                ]);
                return;
            default:
                \App\Models\AuditLog::auth('login.failed', null, $username);
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
        $pendingId = (int) ($_SESSION['pending_user_id'] ?? 0) ?: null;

        if (Auth::completeTwoFactor($code)) {
            \App\Models\AuditLog::auth('2fa', (int) ($_SESSION['user_id'] ?? 0) ?: null, (string) ($_SESSION['username'] ?? ''));
            header('Location: /admin');
            exit;
        }
        \App\Models\AuditLog::auth('2fa.failed', $pendingId, '');

        // Просроченный/сброшенный pending уводит на логин, неверный код — ошибка.
        if (empty($_SESSION['pending_user_id'])) {
            Flash::error('Код устарел. Войдите заново — мы отправим новый.');
            header('Location: /admin/login');
            exit;
        }

        View::render('admin/auth/2fa', ['error' => 'Неверный код. Попробуйте снова.']);
    }

    /** Повторная отправка кода в Telegram (лимит: 3 раза за 5 минут). */
    public function resendCode(): void
    {
        Csrf::verifyRequest();

        if (empty($_SESSION['pending_user_id'])) {
            header('Location: /admin/login');
            exit;
        }

        View::render('admin/auth/2fa', [
            'error' => null,
            'notice' => Auth::resendCode()
                ? 'Новый код отправлен в Telegram.'
                : 'Не удалось отправить код (превышен лимит или шлюз недоступен). Подождите и попробуйте снова.',
        ]);
    }

    public function logout(): void
    {
        Csrf::verifyRequest();
        \App\Models\AuditLog::auth('logout', (int) ($_SESSION['user_id'] ?? 0) ?: null, (string) ($_SESSION['username'] ?? ''));
        Auth::logout();
        header('Location: /admin/login');
        exit;
    }
}
