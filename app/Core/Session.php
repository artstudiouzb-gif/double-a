<?php

declare(strict_types=1);

namespace App\Core;

/** Централизованный ленивый запуск защищённой PHP-сессии. */
final class Session
{
    public static function hasCookie(): bool
    {
        $name = (string) Config::get('session.name', 'asc_session');
        return isset($_COOKIE[$name]) && is_string($_COOKIE[$name]) && $_COOKIE[$name] !== '';
    }

    public static function start(): void
    {
        if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Файлы сессий храним в собственной папке приложения. Системный
        // save_path PHP (напр. /var/lib/php/session/N/N/) на shared-хостинге
        // (Plesk/cPanel) часто не существует или недоступен для записи —
        // session_start() падает с "No such file or directory".
        $sessionPath = (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2)) . '/storage/sessions';
        if (!is_dir($sessionPath)) {
            @mkdir($sessionPath, 0770, true);
        }
        if (is_dir($sessionPath) && is_writable($sessionPath)) {
            session_save_path($sessionPath);
            ini_set('session.save_path', $sessionPath);
            ini_set('session.gc_probability', '1');
            ini_set('session.gc_divisor', '100');
        }

        $lifetime = (int) Config::get('session.lifetime', 7200);
        session_name((string) Config::get('session.name', 'asc_session'));
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'domain' => '',
            'secure' => RequestUrl::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
        if (!empty($_SESSION['last_activity']) && time() - (int) $_SESSION['last_activity'] > $lifetime) {
            $_SESSION = [];
            session_destroy();
            session_start();
        }
        $_SESSION['last_activity'] = time();
    }
}
