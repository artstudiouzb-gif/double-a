<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\SettingsValidator;
use App\Core\View;
use App\Models\Setting;

/**
 * Настройки производительности: управление кэшем страниц, качеством WebP,
 * ленивой загрузкой изображений и CDN, плюс ручная очистка кэша.
 */
final class PerformanceController
{
    public function index(): void
    {
        Auth::requireSuperAdmin();
        View::render('admin/performance/index', [
            'settings' => Setting::all(),
        ]);
    }

    public function update(): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        Setting::set('perf_page_cache', !empty($_POST['perf_page_cache']) ? '1' : '0');
        Setting::set('perf_cache_ttl', (string) SettingsValidator::nonNegativeInt((string) ($_POST['perf_cache_ttl'] ?? ''), 0));
        Setting::set('perf_public_cache_ttl', (string) min(3600, SettingsValidator::nonNegativeInt((string) ($_POST['perf_public_cache_ttl'] ?? ''), 60)));
        Setting::set('perf_shared_cache_ttl', (string) min(86400, SettingsValidator::nonNegativeInt((string) ($_POST['perf_shared_cache_ttl'] ?? ''), 300)));
        Setting::set('perf_lazy_load', !empty($_POST['perf_lazy_load']) ? '1' : '0');

        $quality = SettingsValidator::nonNegativeInt((string) ($_POST['perf_webp_quality'] ?? ''), 82);
        Setting::set('perf_webp_quality', (string) max(40, min(95, $quality)));

        $imgMaxW = SettingsValidator::nonNegativeInt((string) ($_POST['perf_image_max_width'] ?? ''), 2560);
        Setting::set('perf_image_max_width', (string) max(1200, min(4000, $imgMaxW)));

        // CDN base: только http(s)-URL без хвостового слэша, иначе пусто.
        $cdn = trim((string) ($_POST['perf_cdn_url'] ?? ''));
        if ($cdn !== '' && preg_match('#^https?://[^\s]+$#i', $cdn) !== 1) {
            $cdn = '';
        }
        Setting::set('perf_cdn_url', rtrim($cdn, '/'));

        // Cloudflare (очистка кэша по API).
        Setting::set('cf_enabled', !empty($_POST['cf_enabled']) ? '1' : '0');
        Setting::set('cf_api_token', trim((string) ($_POST['cf_api_token'] ?? '')));
        $zone = trim((string) ($_POST['cf_zone_id'] ?? ''));
        Setting::set('cf_zone_id', preg_match('/^[a-f0-9]{0,64}$/i', $zone) === 1 ? $zone : '');

        Flash::success('Настройки производительности сохранены.');
        header('Location: /admin/performance');
        exit;
    }

    public function cloudflareVerify(): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        $r = \App\Core\Cloudflare::verify();
        $r['ok'] ? Flash::success($r['message']) : Flash::error($r['message']);
        header('Location: /admin/performance');
        exit;
    }

    public function cloudflarePurge(): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        if (!\App\Core\Cloudflare::enabled()) {
            Flash::error('Интеграция с Cloudflare выключена или не настроена.');
        } elseif (\App\Core\Cloudflare::purgeEverything()) {
            Flash::success('Кэш Cloudflare очищен.');
        } else {
            Flash::error('Не удалось очистить кэш Cloudflare — проверьте токен и Zone ID (подробности в логах).');
        }
        header('Location: /admin/performance');
        exit;
    }

    public function clearCache(): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        Cache::flush();
        \App\Core\Cloudflare::purgeSite();
        Flash::success('Кэш очищен.');

        // Возврат на страницу, откуда нажали (только локальные /admin-пути).
        $back = (string) ($_POST['redirect'] ?? '');
        if ($back === '' || $back[0] !== '/' || str_starts_with($back, '//') || !str_starts_with($back, '/admin')) {
            $back = '/admin/performance';
        }
        header('Location: ' . $back);
        exit;
    }
}
