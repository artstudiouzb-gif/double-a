<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\PasswordPolicy;
use App\Core\View;
use App\Models\SessionRegistry;
use App\Models\User;

/**
 * Профиль администратора: смена пароля, управление активными сессиями,
 * телефон для кода входа через Telegram (Verification Codes).
 */
final class ProfileController
{
    public function index(): void
    {
        Auth::requireLogin();

        $userId = (int) Auth::id();
        $currentHash = SessionRegistry::hash(session_id());
        $profileUser = User::findById($userId);

        // Одноразовый код привязки Telegram-бота: показываем, пока аккаунт
        // не привязан. Код живёт в сессии, боту его отправляет сам админ.
        $botConfigured = \App\Core\TelegramBot::isConfigured();
        $botLinked = (int) ($profileUser['telegram_chat_id'] ?? 0) > 0;
        $botUsername = null;
        if ($botConfigured && !$botLinked) {
            if (empty($_SESSION['tg_link_code'])) {
                $_SESSION['tg_link_code'] = 'link-' . bin2hex(random_bytes(4));
            }
            $me = \App\Core\TelegramBot::getMe();
            $botUsername = is_array($me) ? (string) ($me['username'] ?? '') : '';
        }

        View::render('admin/profile/index', [
            'sessions' => SessionRegistry::forUser($userId),
            'currentHash' => $currentHash,
            'profileUser' => $profileUser,
            'botConfigured' => $botConfigured,
            'botLinked' => $botLinked,
            'botUsername' => $botUsername,
            'linkCode' => $_SESSION['tg_link_code'] ?? null,
            'error' => null,
        ]);
    }

    /**
     * Проверка привязки Telegram: админ отправил боту одноразовый код —
     * находим его через getUpdates и сохраняем chat_id.
     */
    public function linkTelegram(): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        $code = (string) ($_SESSION['tg_link_code'] ?? '');
        if ($code === '' || !\App\Core\TelegramBot::isConfigured()) {
            Flash::error('Привязка недоступна: бот не настроен.');
            header('Location: /admin/profile');
            exit;
        }

        $chatId = \App\Core\TelegramBot::findChatIdByCode($code);
        if ($chatId === null) {
            Flash::error('Код не найден. Отправьте код боту в Telegram и нажмите «Проверить привязку» ещё раз.');
            header('Location: /admin/profile');
            exit;
        }

        User::updateTelegramChatId((int) Auth::id(), $chatId);
        unset($_SESSION['tg_link_code']);
        \App\Core\Logger::security('Привязан Telegram для кодов входа', [
            'user' => (string) ($_SESSION['username'] ?? ''),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
        Flash::success('Telegram привязан. Теперь коды входа будут приходить от бота — бесплатно.');
        header('Location: /admin/profile');
        exit;
    }

    /** Отвязка Telegram — с подтверждением паролем (ослабляет защиту входа). */
    public function unlinkTelegram(): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        $userId = (int) Auth::id();
        $user = User::findById($userId);
        if (!$user || !password_verify((string) ($_POST['password'] ?? ''), $user['password_hash'])) {
            Flash::error('Неверный пароль. Telegram не отвязан.');
            header('Location: /admin/profile');
            exit;
        }

        User::updateTelegramChatId($userId, null);
        \App\Core\Logger::security('Отвязан Telegram для кодов входа', [
            'user' => (string) ($_SESSION['username'] ?? ''),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
        Flash::success('Telegram отвязан.');
        header('Location: /admin/profile');
        exit;
    }

    public function changePassword(): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        $userId = (int) Auth::id();
        $user = User::findById($userId);

        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['new_password_confirm'] ?? '');

        $error = null;
        if (!$user || !password_verify($current, $user['password_hash'])) {
            $error = 'Текущий пароль указан неверно.';
        } elseif ($new !== $confirm) {
            $error = 'Новый пароль и подтверждение не совпадают.';
        } else {
            $errors = PasswordPolicy::validate($new, [(string) $user['username'], (string) $user['email']]);
            if ($errors !== []) {
                $error = implode(' ', $errors);
            }
        }

        if ($error !== null) {
            \App\Core\Logger::security('Отклонён слабый/некорректный пароль при смене', [
                'user' => (string) ($user['username'] ?? ''),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
            Flash::error($error);
            header('Location: /admin/profile');
            exit;
        }

        User::updatePassword($userId, $new);
        // Завершаем все прочие сессии; текущую оставляем активной.
        SessionRegistry::revokeAllExcept($userId, session_id());

        \App\Core\Logger::security('Пароль администратора изменён', [
            'user' => (string) ($user['username'] ?? ''),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
        Flash::success('Пароль изменён. Другие сессии завершены.');
        header('Location: /admin/profile');
        exit;
    }

    public function revokeSession(array $params): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        SessionRegistry::revoke((int) Auth::id(), (int) $params['id']);
        Flash::success('Сессия отозвана.');
        header('Location: /admin/profile');
        exit;
    }

    public function revokeOthers(): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        SessionRegistry::revokeAllExcept((int) Auth::id(), session_id());
        \App\Core\Logger::security('Отзыв всех прочих сессий администратора', [
            'user' => (string) ($_SESSION['username'] ?? ''),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
        Flash::success('Все другие сессии завершены.');
        header('Location: /admin/profile');
        exit;
    }

    /**
     * Телефон для кода входа через Telegram (E.164). Изменение подтверждается
     * текущим паролем — иначе угнанная сессия могла бы перевесить 2FA на себя.
     */
    public function updatePhone(): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        $userId = (int) Auth::id();
        $user = User::findById($userId);

        if (!$user || !password_verify((string) ($_POST['password'] ?? ''), $user['password_hash'])) {
            Flash::error('Неверный пароль. Телефон не изменён.');
            header('Location: /admin/profile');
            exit;
        }

        $raw = trim((string) ($_POST['phone'] ?? ''));
        if ($raw === '') {
            User::updatePhone($userId, null);
            Flash::success('Телефон удалён — вход будет выполняться без кода подтверждения.');
        } else {
            $phone = \App\Core\TelegramGateway::normalizePhone($raw);
            if ($phone === null) {
                Flash::error('Некорректный номер. Укажите телефон в международном формате, например +998901234567.');
                header('Location: /admin/profile');
                exit;
            }
            User::updatePhone($userId, $phone);
            Flash::success('Телефон сохранён. Коды входа будут приходить в Telegram (Verification Codes).');
        }

        header('Location: /admin/profile');
        exit;
    }
}
