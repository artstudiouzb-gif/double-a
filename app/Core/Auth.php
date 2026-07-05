<?php

declare(strict_types=1);

namespace App\Core;

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

        if (!TOTP::verify($user['totp_secret'], $code)) {
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

        User::touchLastLogin((int) $user['id']);
    }

    private static function clearPending(): void
    {
        unset($_SESSION['pending_user_id'], $_SESSION['pending_since'], $_SESSION['pending_totp_secret']);
    }

    public static function check(): bool
    {
        return !empty($_SESSION['user_id']);
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

    public static function logout(): void
    {
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
