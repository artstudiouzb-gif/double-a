<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\RepoUser;

/**
 * Авторизация портала защищённого файлового хранилища. Полностью независима
 * от админ-панели (App\Core\Auth): использует собственные ключи сессии с
 * префиксом repo_*, свою таблицу repo_users и свой rate-limit namespace.
 * Один и тот же браузер может быть залогинен и в админку, и в портал
 * одновременно, не мешая друг другу.
 */
final class RepoAuth
{
    /**
     * @return array{status: string, retry_after?: int}
     */
    public static function attemptLogin(string $username, string $password): array
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $identifier = 'repo|' . $ip . '|' . mb_strtolower($username);

        if (RateLimiter::tooManyAttempts($identifier)) {
            return ['status' => 'locked', 'retry_after' => RateLimiter::secondsUntilRetry($identifier)];
        }

        $user = RepoUser::findByUsername($username);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            RateLimiter::recordAttempt($identifier, false);
            return ['status' => 'invalid'];
        }

        if ((int) $user['is_active'] !== 1) {
            RateLimiter::recordAttempt($identifier, false);
            return ['status' => 'disabled'];
        }

        RateLimiter::clearAttempts($identifier);

        session_regenerate_id(true);
        $_SESSION['repo_pending_user_id'] = (int) $user['id'];
        $_SESSION['repo_pending_since'] = time();

        if ((int) $user['totp_enabled'] === 1) {
            return ['status' => 'needs_2fa'];
        }

        self::establishSession($user);

        return ['status' => 'ok'];
    }

    public static function completeTwoFactor(string $code): bool
    {
        $userId = $_SESSION['repo_pending_user_id'] ?? null;
        if (!$userId || (time() - (int) ($_SESSION['repo_pending_since'] ?? 0)) > 300) {
            self::clearPending();
            return false;
        }

        $user = RepoUser::findById((int) $userId);
        if (!$user || empty($user['totp_secret']) || (int) $user['is_active'] !== 1) {
            self::clearPending();
            return false;
        }

        $identifier = 'repo2fa|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '|' . mb_strtolower($user['username']);
        if (RateLimiter::tooManyAttempts($identifier)) {
            return false;
        }

        if (!TOTP::verify($user['totp_secret'], $code)) {
            RateLimiter::recordAttempt($identifier, false);
            return false;
        }

        RateLimiter::clearAttempts($identifier);
        self::clearPending();
        self::establishSession($user);

        return true;
    }

    public static function pendingUserId(): ?int
    {
        $id = $_SESSION['repo_pending_user_id'] ?? null;

        return $id ? (int) $id : null;
    }

    private static function establishSession(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['repo_user_id'] = (int) $user['id'];
        $_SESSION['repo_username'] = $user['username'];
        $_SESSION['repo_authenticated_at'] = time();
        $_SESSION['repo_fingerprint'] = self::fingerprint();

        RepoUser::touchLastLogin((int) $user['id']);

        Logger::security('Успешный вход в файловый портал', [
            'user' => (string) $user['username'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
    }

    private static function fingerprint(): string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $subnet = '';
        if (str_contains($ip, '.')) {
            $octets = explode('.', $ip);
            $subnet = ($octets[0] ?? '') . '.' . ($octets[1] ?? '');
        } elseif (str_contains($ip, ':')) {
            $parts = explode(':', $ip);
            $subnet = ($parts[0] ?? '') . ':' . ($parts[1] ?? '');
        }

        return hash('sha256', 'repo|' . $ua . '|' . $subnet);
    }

    private static function clearPending(): void
    {
        unset($_SESSION['repo_pending_user_id'], $_SESSION['repo_pending_since']);
    }

    public static function check(): bool
    {
        if (empty($_SESSION['repo_user_id'])) {
            return false;
        }

        if (!isset($_SESSION['repo_fingerprint']) || !hash_equals($_SESSION['repo_fingerprint'], self::fingerprint())) {
            self::logout();
            return false;
        }

        // Отзыв доступа администратором: деактивированный аккаунт немедленно
        // теряет сессию при следующем запросе.
        try {
            $user = RepoUser::findById((int) $_SESSION['repo_user_id']);
            if ($user === null || (int) $user['is_active'] !== 1) {
                self::logout();
                return false;
            }
        } catch (\Throwable $e) {
            Logger::error('RepoAuth check failed: ' . $e->getMessage());
        }

        return true;
    }

    public static function id(): ?int
    {
        return isset($_SESSION['repo_user_id']) ? (int) $_SESSION['repo_user_id'] : null;
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        return RepoUser::findById((int) $_SESSION['repo_user_id']);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: /repo/login');
            exit;
        }
    }

    public static function logout(): void
    {
        unset(
            $_SESSION['repo_user_id'],
            $_SESSION['repo_username'],
            $_SESSION['repo_authenticated_at'],
            $_SESSION['repo_fingerprint'],
            $_SESSION['repo_pending_user_id'],
            $_SESSION['repo_pending_since'],
            $_SESSION['repo_totp_setup_secret']
        );
    }
}
