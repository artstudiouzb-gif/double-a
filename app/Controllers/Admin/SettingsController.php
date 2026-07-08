<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\ImageField;
use App\Core\SettingsValidator;
use App\Core\View;
use App\Models\Page;
use App\Models\Setting;

final class SettingsController
{
    private const TEXT_KEYS = [
        'site_name', 'color_primary', 'color_accent', 'font_family',
        'contact_phone', 'contact_email', 'contact_address',
        'font_url', 'font_face_name', 'default_meta_description',
        'telegram_gateway_token', 'telegram_bot_token',
    ];

    public function index(): void
    {
        Auth::requireSuperAdmin();
        View::render('admin/settings/index', [
            'settings' => Setting::all(),
            'pages' => Page::all(),
        ]);
    }

    public function update(): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        foreach (self::TEXT_KEYS as $key) {
            Setting::set($key, trim((string) ($_POST[$key] ?? '')));
        }

        $logoUrl = ImageField::resolve('logo_file', 'logo_url', Setting::get('logo_url'), Auth::id());
        Setting::set('logo_url', $logoUrl ?? '');

        // --- Веб-аналитика: строгие ID вместо сырого JS (задача 116) ---
        Setting::set('analytics_ga_id', SettingsValidator::gaId((string) ($_POST['analytics_ga_id'] ?? '')));
        Setting::set('analytics_ym_id', SettingsValidator::ymId((string) ($_POST['analytics_ym_id'] ?? '')));

        // --- Приватность / GDPR ---
        Setting::set('cookie_consent_enabled', !empty($_POST['cookie_consent_enabled']) ? '1' : '0');
        $privacyPageId = (int) ($_POST['privacy_policy_page_id'] ?? 0);
        Setting::set('privacy_policy_page_id', $privacyPageId > 0 ? (string) $privacyPageId : '');
        Setting::set('pii_retention_days', (string) SettingsValidator::nonNegativeInt((string) ($_POST['pii_retention_days'] ?? ''), 0));

        // --- Favicon / PWA / Theme Color ---
        $favicon = ImageField::resolve('favicon_file', 'favicon_url', Setting::get('favicon_url'), Auth::id());
        Setting::set('favicon_url', $favicon ?? '');
        Setting::set('pwa_short_name', SettingsValidator::shortName((string) ($_POST['pwa_short_name'] ?? '')));
        Setting::set('theme_color', SettingsValidator::hexColor((string) ($_POST['theme_color'] ?? ''), '#1a1a1a'));

        // --- Глобальное SEO / соцсети ---
        $ogImage = ImageField::resolve('default_og_image_file', 'default_og_image', Setting::get('default_og_image'), Auth::id());
        Setting::set('default_og_image', $ogImage ?? '');

        // Тема оформления.
        $theme = in_array($_POST['default_theme'] ?? 'light', ['light', 'dark', 'auto'], true)
            ? $_POST['default_theme'] : 'light';
        Setting::set('default_theme', $theme);

        // Режим обслуживания.
        Setting::set('maintenance_mode', !empty($_POST['maintenance_mode']) ? '1' : '0');
        Setting::set('maintenance_message', trim((string) ($_POST['maintenance_message'] ?? '')));

        // Глобальный произвольный CSS/JS вне блоков (группа 6). Доступ уже
        // ограничен супер-администратором (requireSuperAdmin выше) — хранится
        // как есть (доверенный источник), выводится на фронте один раз.
        Setting::set('custom_css_global', (string) ($_POST['custom_css_global'] ?? ''));
        Setting::set('custom_js_global', (string) ($_POST['custom_js_global'] ?? ''));

        Flash::success('Настройки сохранены.');
        header('Location: /admin/settings');
        exit;
    }
}
