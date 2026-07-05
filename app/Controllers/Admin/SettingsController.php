<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\ImageField;
use App\Core\View;
use App\Models\Setting;

final class SettingsController
{
    private const TEXT_KEYS = [
        'site_name', 'color_primary', 'color_accent', 'font_family',
        'contact_phone', 'contact_email', 'contact_address',
    ];

    public function index(): void
    {
        Auth::requireSuperAdmin();
        View::render('admin/settings/index', ['settings' => Setting::all()]);
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

        Setting::set('counter_codes', (string) ($_POST['counter_codes'] ?? ''));

        Flash::success('Настройки сохранены.');
        header('Location: /admin/settings');
        exit;
    }
}
