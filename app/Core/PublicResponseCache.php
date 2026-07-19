<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Setting;

/** HTTP/CDN-кеширование только общих публичных ответов без сессии. */
final class PublicResponseCache
{
    /** @var list<string> */
    private const PRIVATE_PATHS = [
        '/admin', '/repo', '/install', '/search', '/captcha.png', '/push',
        '/unsubscribe', '/health', '/opendata', '/download.php',
    ];

    /** @var list<string> */
    private const CONTENT_ADMIN_PATHS = [
        '/admin/pages', '/admin/blocks', '/admin/snippets', '/admin/news',
        '/admin/projects', '/admin/team', '/admin/albums', '/admin/videos',
        '/admin/content', '/admin/content-types', '/admin/menu', '/admin/header',
        '/admin/footer', '/admin/widgets', '/admin/design', '/admin/settings',
        '/admin/performance', '/admin/trash', '/admin/bulk',
        '/admin/settings/demo-content', '/admin/redirects', '/admin/languages',
    ];

    public static function apply(string $template): void
    {
        if (!str_starts_with($template, 'site/')) {
            return;
        }

        // Ответ, устанавливающий языковое cookie, не должен попасть в общий CDN-кеш.
        if (LocalePreference::changedThisRequest()) {
            header('Cache-Control: private, no-store');
            return;
        }

        $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
        $status = http_response_code();
        $status = is_int($status) && $status > 0 ? $status : 200;
        if (!self::isCacheableRequest(
            $path,
            (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            session_status() === PHP_SESSION_ACTIVE,
            $status,
            isset($_SERVER['HTTP_AUTHORIZATION'])
        )) {
            return;
        }

        $browserTtl = max(0, min(3600, (int) Setting::get('perf_public_cache_ttl', '60')));
        $sharedTtl = max(0, min(86400, (int) Setting::get('perf_shared_cache_ttl', '300')));
        if ($browserTtl === 0 && $sharedTtl === 0) {
            return;
        }

        header(sprintf(
            'Cache-Control: public, max-age=%d, s-maxage=%d, stale-while-revalidate=30',
            $browserTtl,
            $sharedTtl
        ));
        // Один и тот же URL может быть запрошен с разным сохранённым языком.
        // Без Vary браузер повторно показывает ранее закешированную языковую
        // копию и запрос не доходит до редиректа в Router::resolveLocale().
        header('Vary: Accept-Encoding, Cookie');
    }

    public static function isCacheableRequest(
        string $path,
        string $method,
        bool $sessionActive,
        int $status,
        bool $hasAuthorization = false
    ): bool {
        if (!in_array(strtoupper($method), ['GET', 'HEAD'], true)
            || $sessionActive
            || $hasAuthorization
            || $status !== 200) {
            return false;
        }

        if (self::isPrivatePath($path)) {
            return false;
        }

        // Публичные маршруты могут иметь языковой префикс (/uz/search).
        $localizedPath = preg_replace('#^/[a-zA-Z]{2,8}(?=/|$)#', '', $path) ?: '/';
        return !self::isPrivatePath($localizedPath);
    }

    private static function isPrivatePath(string $path): bool
    {
        foreach (self::PRIVATE_PATHS as $privatePath) {
            if ($path === $privatePath || str_starts_with($path, $privatePath . '/')) {
                return true;
            }
        }
        return false;
    }

    /** Сброс общего кеша после успешной мутации публичного контента. */
    public static function registerContentInvalidation(): void
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }
        $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
        $matches = false;
        foreach (self::CONTENT_ADMIN_PATHS as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                $matches = true;
                break;
            }
        }
        if (!$matches) {
            return;
        }

        register_shutdown_function(static function (): void {
            $status = http_response_code();
            if (!is_int($status) || $status < 400) {
                Cache::forgetPrefix('page:');
            }
        });
    }
}
