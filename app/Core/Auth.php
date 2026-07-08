<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\SessionRegistry;
use App\Models\User;

final class Auth
{
    /** Срок жизни кода подтверждения входа, секунд. */
    private const CODE_TTL = 300;

    /**
     * Вход по паролю с подтверждением одноразовым кодом через Telegram
     * (официальный канал Verification Codes, Telegram Gateway API).
     * Другие методы 2FA (TOTP, backup-коды) для админки отключены.
     *
     * Статусы: needs_code — код отправлен, ждём подтверждения;
     * ok — вход выполнен (шлюз не настроен или у пользователя нет телефона);
     * send_failed — шлюз не принял сообщение; invalid/locked — как раньше.
     *
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

        if (!self::hasCodeChannel($user)) {
            // Ни бот, ни шлюз не доступны этому пользователю — входим по
            // паролю. Фиксируем в security-логе, чтобы это было видно.
            Logger::security('Вход без кода подтверждения (Telegram-доставка не настроена)', [
                'user' => (string) $user['username'],
                'ip' => $ip,
            ]);
            self::establishSession($user);

            return ['status' => 'ok'];
        }

        $_SESSION['pending_user_id'] = (int) $user['id'];
        $_SESSION['pending_since'] = time();

        if (!self::sendLoginCode($user)) {
            self::clearPending();

            return ['status' => 'send_failed'];
        }

        return ['status' => 'needs_code'];
    }

    /**
     * Доступен ли пользователю хоть один канал доставки кода: бесплатный
     * бот (telegram_chat_id) или платный шлюз Verification Codes (телефон).
     */
    private static function hasCodeChannel(array $user): bool
    {
        if (TelegramBot::isConfigured() && (int) ($user['telegram_chat_id'] ?? 0) > 0) {
            return true;
        }

        return TelegramGateway::isConfigured() && trim((string) ($user['phone'] ?? '')) !== '';
    }

    /**
     * Генерирует одноразовый код, сохраняет его хэш в сессии и отправляет в
     * Telegram. Приоритет — бесплатный бот; иначе платный шлюз (канал
     * Verification Codes). Используется при входе и при повторной отправке.
     */
    private static function sendLoginCode(array $user): bool
    {
        $code = (string) random_int(100000, 999999);
        $_SESSION['pending_code_hash'] = hash('sha256', $code);
        $_SESSION['pending_code_expires'] = time() + self::CODE_TTL;

        $chatId = (int) ($user['telegram_chat_id'] ?? 0);
        if (TelegramBot::isConfigured() && $chatId > 0) {
            return TelegramBot::sendLoginCode($chatId, $code);
        }

        return TelegramGateway::sendCode((string) $user['phone'], $code);
    }

    /**
     * Повторная отправка кода (по кнопке на странице подтверждения).
     * Ограничена: не чаще 3 раз за 5 минут с одного IP.
     */
    public static function resendCode(): bool
    {
        $userId = $_SESSION['pending_user_id'] ?? null;
        if (!$userId) {
            return false;
        }
        if (!RateLimiter::throttle('2fa_resend', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 3, 5)) {
            return false;
        }

        $user = User::findById((int) $userId);
        if (!$user || !self::hasCodeChannel($user)) {
            return false;
        }

        return self::sendLoginCode($user);
    }

    /**
     * Проверка кода из Telegram: hash_equals с хэшем из сессии, срок жизни
     * 5 минут, перебор ограничен RateLimiter.
     */
    public static function completeTwoFactor(string $code): bool
    {
        $userId = $_SESSION['pending_user_id'] ?? null;
        if (!$userId || (time() - (int) ($_SESSION['pending_since'] ?? 0)) > self::CODE_TTL) {
            self::clearPending();
            return false;
        }

        $user = User::findById((int) $userId);
        $expectedHash = (string) ($_SESSION['pending_code_hash'] ?? '');
        if (!$user || $expectedHash === '' || time() > (int) ($_SESSION['pending_code_expires'] ?? 0)) {
            self::clearPending();
            return false;
        }

        $identifier = ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '|2fa|' . mb_strtolower($user['username']);
        if (RateLimiter::tooManyAttempts($identifier)) {
            return false;
        }

        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (!hash_equals($expectedHash, hash('sha256', $code))) {
            RateLimiter::recordAttempt($identifier, false);
            return false;
        }

        RateLimiter::clearAttempts($identifier);
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

        Logger::security('Успешный вход в панель управления', [
            'user' => (string) $user['username'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);

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
        unset(
            $_SESSION['pending_user_id'],
            $_SESSION['pending_since'],
            $_SESSION['pending_code_hash'],
            $_SESSION['pending_code_expires']
        );
    }

    public static function check(): bool
    {
        if (empty($_SESSION['user_id'])) {
            return false;
        }

        // Защита от перехвата сессии: фингерпринт должен совпадать.
        if (!isset($_SESSION['fingerprint']) || !hash_equals($_SESSION['fingerprint'], self::fingerprint())) {
            Logger::security('Несовпадение фингерпринта сессии — принудительный выход', [
                'user' => (string) ($_SESSION['username'] ?? ''),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
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
