<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\PasswordPolicy;
use App\Core\View;
use App\Models\BackupCode;
use App\Models\SessionRegistry;
use App\Models\User;

/**
 * Профиль администратора: смена пароля, управление активными сессиями,
 * регенерация backup-кодов 2FA.
 */
final class ProfileController
{
    public function index(): void
    {
        Auth::requireLogin();

        $userId = (int) Auth::id();
        $currentHash = SessionRegistry::hash(session_id());

        // Одноразовый показ свежесгенерированных backup-кодов (после setup/регена).
        $freshCodes = $_SESSION['fresh_backup_codes'] ?? null;
        unset($_SESSION['fresh_backup_codes']);

        View::render('admin/profile/index', [
            'sessions' => SessionRegistry::forUser($userId),
            'currentHash' => $currentHash,
            'backupRemaining' => BackupCode::remainingCount($userId),
            'freshCodes' => $freshCodes,
            'error' => null,
        ]);
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
            Flash::error($error);
            header('Location: /admin/profile');
            exit;
        }

        User::updatePassword($userId, $new);
        // Завершаем все прочие сессии; текущую оставляем активной.
        SessionRegistry::revokeAllExcept($userId, session_id());

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
        Flash::success('Все другие сессии завершены.');
        header('Location: /admin/profile');
        exit;
    }

    public function regenerateBackupCodes(): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        $userId = (int) Auth::id();
        $user = User::findById($userId);

        // Подтверждение паролем — как требует ТЗ (задача 47).
        if (!$user || !password_verify((string) ($_POST['password'] ?? ''), $user['password_hash'])) {
            Flash::error('Неверный пароль. Коды не изменены.');
            header('Location: /admin/profile');
            exit;
        }

        $_SESSION['fresh_backup_codes'] = BackupCode::regenerate($userId);
        Flash::success('Резервные коды перевыпущены. Старые коды больше не действуют.');
        header('Location: /admin/profile');
        exit;
    }
}
