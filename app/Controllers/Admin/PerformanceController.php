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
        Setting::set('perf_lazy_load', !empty($_POST['perf_lazy_load']) ? '1' : '0');

        $quality = SettingsValidator::nonNegativeInt((string) ($_POST['perf_webp_quality'] ?? ''), 82);
        Setting::set('perf_webp_quality', (string) max(40, min(95, $quality)));

        // CDN base: только http(s)-URL без хвостового слэша, иначе пусто.
        $cdn = trim((string) ($_POST['perf_cdn_url'] ?? ''));
        if ($cdn !== '' && preg_match('#^https?://[^\s]+$#i', $cdn) !== 1) {
            $cdn = '';
        }
        Setting::set('perf_cdn_url', rtrim($cdn, '/'));

        Flash::success('Настройки производительности сохранены.');
        header('Location: /admin/performance');
        exit;
    }

    public function clearCache(): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        Cache::flush();
        Flash::success('Кэш очищен.');
        header('Location: /admin/performance');
        exit;
    }
}
