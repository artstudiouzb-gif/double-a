<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Единая точка выставления заголовков безопасности для ВСЕХ HTTP-ответов
 * (включая 404/500/503 и брендированный fail-safe в bootstrap). Вызывается
 * максимально рано, до какого-либо вывода.
 *
 * CSP включена и на публичной части, и в панели. Инлайн-скрипты разрешаются
 * только по одноразовому nonce (self::nonce()), 'unsafe-inline' для скриптов
 * не используется нигде. Для стилей 'unsafe-inline' оставлен осознанно:
 * тема-билдер и блоки используют style-атрибуты, которые nonce не покрывает.
 */
final class SecurityHeaders
{
    private static ?string $nonce = null;

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
        // может «залочить» разработку на localhost). preload — осознанная
        // опция config: security.hsts_preload (включать после месяца
        // стабильного HTTPS, см. hstspreload.org).
        if (self::isHttps()) {
            header('Strict-Transport-Security: ' . self::hstsValue());
        }

        $isAdmin = str_starts_with($path, '/admin') || str_starts_with($path, '/install');
        header('Content-Security-Policy: ' . ($isAdmin
            ? self::adminCsp(self::nonce())
            : self::publicCsp(self::nonce(), self::publicCspOptions())));
    }

    /**
     * Одноразовый nonce текущего запроса для инлайн-скриптов
     * (<script nonce="...">). Генерируется лениво один раз на запрос.
     */
    public static function nonce(): string
    {
        return self::$nonce ??= rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    }

    /** Значение Strict-Transport-Security с учётом опции preload. */
    public static function hstsValue(?bool $preload = null): string
    {
        $preload ??= (bool) Config::get('security.hsts_preload', false);

        // Требования hstspreload.org: max-age ≥ 1 год, includeSubDomains, preload.
        return $preload
            ? 'max-age=63072000; includeSubDomains; preload'
            : 'max-age=31536000; includeSubDomains';
    }

    /**
     * CSP панели управления и установщика: инлайн-скрипты только по nonce.
     */
    public static function adminCsp(string $nonce): string
    {
        return "default-src 'self'; "
            . "img-src 'self' data:; "
            . "style-src 'self' 'unsafe-inline'; "
            . "script-src 'self' 'nonce-{$nonce}'; "
            . "font-src 'self' data:; "
            . "connect-src 'self'; "
            . "object-src 'none'; "
            . "base-uri 'self'; "
            . "form-action 'self'; "
            . "frame-ancestors 'self'";
    }

    /**
     * CSP публичной части. Инлайн-скрипты (анти-FOUC, счётчики, глобальный JS)
     * — только по nonce; хосты Google Fonts и счётчиков добавляются по
     * фактически включённым настройкам.
     *
     * @param array{google_fonts?: bool, ga?: bool, ym?: bool} $opts
     */
    public static function publicCsp(string $nonce, array $opts = []): string
    {
        $script = ["'self'", "'nonce-{$nonce}'"];
        $connect = ["'self'"];
        $style = ["'self'", "'unsafe-inline'"];
        $font = ["'self'", 'data:'];

        if (!empty($opts['google_fonts'])) {
            $style[] = 'https://fonts.googleapis.com';
            $font[] = 'https://fonts.gstatic.com';
        }
        if (!empty($opts['ga'])) {
            $script[] = 'https://www.googletagmanager.com';
            $connect[] = 'https://*.google-analytics.com';
            $connect[] = 'https://*.analytics.google.com';
            $connect[] = 'https://www.googletagmanager.com';
        }
        if (!empty($opts['ym'])) {
            $script[] = 'https://mc.yandex.ru';
            $connect[] = 'https://mc.yandex.ru';
        }

        return "default-src 'self'; "
            // Блоки могут ссылаться на внешние картинки (URL проверяется UrlGuard);
            // пиксели счётчиков тоже сюда попадают.
            . "img-src 'self' data: https:; "
            . 'style-src ' . implode(' ', $style) . '; '
            . 'script-src ' . implode(' ', $script) . '; '
            . 'font-src ' . implode(' ', $font) . '; '
            . 'connect-src ' . implode(' ', $connect) . '; '
            // Видео-hero может указывать на внешний файл.
            . "media-src 'self' https:; "
            // YouTube-эмбеды новостей и карты блока map_point (URL задаёт админ).
            . "frame-src https:; "
            // Service worker webpush-уведомлений.
            . "worker-src 'self'; "
            . "object-src 'none'; "
            . "base-uri 'self'; "
            . "form-action 'self'; "
            . "frame-ancestors 'self'";
    }

    /**
     * Опции публичной CSP из настроек. БД может быть недоступна (503 в
     * bootstrap) — тогда консервативный набор без внешних хостов.
     *
     * @return array{google_fonts: bool, ga: bool, ym: bool}
     */
    private static function publicCspOptions(): array
    {
        try {
            return [
                'google_fonts' => DesignSettings::googleFontsHref() !== null,
                'ga' => Analytics::gaId() !== '',
                'ym' => Analytics::ymId() !== '',
            ];
        } catch (\Throwable) {
            return ['google_fonts' => false, 'ga' => false, 'ym' => false];
        }
    }

    /**
     * Добавляет nonce всем <script>-тегам без него в готовом HTML. Нужен для
     * закэшированных блоков страниц: HTML в кэше общий, а nonce — на запрос.
     * Контент доверенный (сырые <script> в блоках может сохранять только
     * супер-админ; для editor всё режет HtmlSanitizer).
     */
    public static function injectScriptNonce(string $html, ?string $nonce = null): string
    {
        if (!str_contains($html, '<script')) {
            return $html;
        }
        $nonce ??= self::nonce();

        return (string) preg_replace(
            '/<script\b(?![^>]*\bnonce=)/i',
            '<script nonce="' . $nonce . '"',
            $html
        );
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    }
}
