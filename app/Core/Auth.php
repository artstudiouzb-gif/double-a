<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\BackupCode;
use App\Models\SessionRegistry;
use App\Models\User;

final class Auth
{
    /**
     * @return array{status: string, retry_after?: int}
     */
    public static function attemptLogin(string $username, string $password): array
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $identifier = $ip . '|' . mb_strtolower($username);

        if (RateLimiter::tooManyAttempts($identifier)) {
            return ['status' => 'locked', 'retry_after' => RateLimiter::secondsUntilRetry($identifier)];
        }

        $user = User::findByUsername($username);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            RateLimiter::recordAttempt($identifier, false);
            return ['status' => 'invalid'];
        }

        RateLimiter::clearAttempts($identifier);

        session_regenerate_id(true);
        $_SESSION['pending_user_id'] = (int) $user['id'];
        $_SESSION['pending_since'] = time();

        if ((int) $user['totp_enabled'] === 1) {
            return ['status' => 'needs_2fa'];
        }

        return ['status' => 'needs_2fa_setup'];
    }

    public static function completeTwoFactor(string $code): bool
    {
        $userId = $_SESSION['pending_user_id'] ?? null;
        if (!$userId || (time() - (int) ($_SESSION['pending_since'] ?? 0)) > 300) {
            self::clearPending();
            return false;
        }

        $user = User::findById((int) $userId);
        if (!$user || empty($user['totp_secret'])) {
            return false;
        }

        $identifier = ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '|2fa|' . mb_strtolower($user['username']);
        if (RateLimiter::tooManyAttempts($identifier)) {
            return false;
        }

        // Основной путь: код из приложения-аутентификатора (TOTP).
        // Альтернатива: одноразовый backup-код (если введён не 6-значный код).
        $ok = TOTP::verify($user['totp_secret'], $code)
            || BackupCode::consume((int) $userId, $code);

        if (!$ok) {
            RateLimiter::recordAttempt($identifier, false);
            return false;
        }

        RateLimiter::clearAttempts($identifier);
        self::clearPending();
        self::establishSession($user);

        return true;
    }

    /**
     * @return array{secret: string, uri: string}
     */
    public static function beginTwoFactorSetup(): array
    {
        $userId = $_SESSION['pending_user_id'] ?? null;
        if (!$userId) {
            throw new \RuntimeException('No pending login.');
        }

        $user = User::findById((int) $userId);

        if (empty($_SESSION['pending_totp_secret'])) {
            $_SESSION['pending_totp_secret'] = TOTP::generateSecret();
        }
        $secret = $_SESSION['pending_totp_secret'];

        return [
            'secret' => $secret,
            'uri' => TOTP::provisioningUri($secret, $user['username'], 'ArtStudio CMS'),
        ];
    }

    public static function confirmTwoFactorSetup(string $code): bool
    {
        $userId = $_SESSION['pending_user_id'] ?? null;
        $secret = $_SESSION['pending_totp_secret'] ?? null;

        if (!$userId || !$secret) {
            return false;
        }

        if (!TOTP::verify($secret, $code)) {
            return false;
        }

        $user = User::findById((int) $userId);
        User::enableTotp((int) $userId, $secret);

        // Генерируем пул backup-кодов и кладём в сессию для однократного показа
        // сразу после установления сессии (страница /admin показывает баннер).
        $_SESSION['fresh_backup_codes'] = BackupCode::regenerate((int) $userId);

        unset($_SESSION['pending_totp_secret']);
        self::clearPending();
        self::establishSession($user);

        return true;
    }

    private static function establishSession(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['authenticated_at'] = time();
        $_SESSION['fingerprint'] = self::fingerprint();

        User::touchLastLogin((int) $user['id']);

        // Регистрируем сессию в реестре: даёт список устройств и мгновенный
        // серверный отзыв (страница «Мои сессии»).
        try {
            SessionRegistry::register(
                (int) $user['id'],
                session_id(),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            );
        } catch (\Throwable $e) {
            Logger::error('SessionRegistry::register failed: ' . $e->getMessage());
        }

        // Вероятностная очистка старых записей брутфорса и ротация логов.
        RateLimiter::garbageCollect();
    }

    /**
     * Фингерпринт клиента: хэш от User-Agent и первых двух октетов IP
     * (подсеть /16). Привязывает сессию к устройству/сети, затрудняя
     * использование украденного cookie с другого клиента, но не ломает
     * сессию при смене последнего октета динамического IP.
     */
    private static function fingerprint(): string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $subnet = '';
        if (str_contains($ip, '.')) {
            $octets = explode('.', $ip);
            $subnet = ($octets[0] ?? '') . '.' . ($octets[1] ?? '');
        } elseif (str_contains($ip, ':')) {
            // IPv6: первые два хекстета.
            $parts = explode(':', $ip);
            $subnet = ($parts[0] ?? '') . ':' . ($parts[1] ?? '');
        }

        return hash('sha256', $ua . '|' . $subnet);
    }

    private static function clearPending(): void
    {
        unset($_SESSION['pending_user_id'], $_SESSION['pending_since'], $_SESSION['pending_totp_secret']);
    }

    public static function check(): bool
    {
        if (empty($_SESSION['user_id'])) {
            return false;
        }

        // Защита от перехвата сессии: фингерпринт должен совпадать.
        if (!isset($_SESSION['fingerprint']) || !hash_equals($_SESSION['fingerprint'], self::fingerprint())) {
            self::logout();
            return false;
        }

        // Мгновенный серверный отзыв: сессия действительна, только пока её
        // строка присутствует в реестре. Удаление строки («выйти на этом
        // устройстве»/«везде»/смена пароля) немедленно завершает сессию.
        try {
            if (!SessionRegistry::exists((int) $_SESSION['user_id'], session_id())) {
                self::logout();
                return false;
            }
            // Обновляем «последнюю активность» не чаще раза в минуту.
            if ((time() - (int) ($_SESSION['sid_seen_at'] ?? 0)) > 60) {
                SessionRegistry::touch((int) $_SESSION['user_id'], session_id());
                $_SESSION['sid_seen_at'] = time();
            }
        } catch (\Throwable $e) {
            // Транзиентная ошибка БД не должна разлогинивать всех — фингерпринт
            // уже проверен; логируем и пропускаем.
            Logger::error('SessionRegistry check failed: ' . $e->getMessage());
        }

        return true;
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        return User::findById((int) $_SESSION['user_id']);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: /admin/login');
            exit;
        }
    }

    public static function role(): string
    {
        return (string) ($_SESSION['role'] ?? 'editor');
    }

    /**
     * Супер-администратор имеет полный доступ. Роль 'editor' ограничена
     * только управлением контентом. Исторически роль называлась 'admin' —
     * она эквивалентна super_admin.
     */
    public static function isSuperAdmin(): bool
    {
        return in_array(self::role(), ['super_admin', 'admin'], true);
    }

    public static function requireSuperAdmin(): void
    {
        self::requireLogin();
        if (!self::isSuperAdmin()) {
            http_response_code(403);
            \App\Core\View::render('errors/403');
            exit;
        }
    }

    public static function logout(): void
    {
        // Снимаем сессию с реестра активных сессий.
        try {
            if (session_status() === PHP_SESSION_ACTIVE) {
                SessionRegistry::remove(session_id());
            }
        } catch (\Throwable $e) {
            Logger::error('SessionRegistry::remove failed: ' . $e->getMessage());
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }
}
