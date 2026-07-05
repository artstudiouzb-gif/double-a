<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Единая точка выставления заголовков безопасности для ВСЕХ HTTP-ответов
 * (включая 404/500/503 и брендированный fail-safe в bootstrap). Вызывается
 * максимально рано, до какого-либо вывода.
 *
 * Для раздела /admin/* дополнительно включается Content-Security-Policy,
 * ограничивающая источники ресурсов панели управления.
 */
final class SecurityHeaders
{
    public static function send(?string $path = null): void
    {
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }

        $path ??= parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('X-Permitted-Cross-Domain-Policies: none');

        // HSTS только по HTTPS (иначе браузер проигнорирует, а на HTTP это
        // может «залочить» разработку на localhost).
        if (self::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        // CSP только для панели управления: фронтенд использует инлайновые
        // <style> с настройками темы/шрифтов, поэтому там политику не навязываем,
        // чтобы не ломать пользовательский дизайн блоков.
        if (str_starts_with($path, '/admin') || str_starts_with($path, '/install')) {
            header(
                "Content-Security-Policy: "
                . "default-src 'self'; "
                . "img-src 'self' data:; "
                . "style-src 'self' 'unsafe-inline'; "
                . "script-src 'self' 'unsafe-inline'; "
                . "font-src 'self' data:; "
                . "connect-src 'self'; "
                . "object-src 'none'; "
                . "base-uri 'self'; "
                . "form-action 'self'; "
                . "frame-ancestors 'self'"
            );
        }
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    }
}
