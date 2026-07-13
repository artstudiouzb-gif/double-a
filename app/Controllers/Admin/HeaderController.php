<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\HeaderConfig;
use App\Core\ImageField;
use App\Core\View;

final class HeaderController
{
    public function index(): void
    {
        Auth::requireSuperAdmin();
        View::render('admin/header/index', ['config' => HeaderConfig::get()]);
    }

    public function update(): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        $social = [];
        foreach ((array) ($_POST['social'] ?? []) as $btn) {
            $social[] = [
                'network' => trim((string) ($btn['network'] ?? '')),
                'url' => trim((string) ($btn['url'] ?? '')),
            ];
        }

        // Конструктор: порядок элементов по зонам (скрытые поля с CSV,
        // заполняемые drag-and-drop в админке) — отдельно для десктопа и мобильного.
        $parseZones = static function (string $key): array {
            $out = [];
            foreach (['left', 'center', 'right'] as $zone) {
                $raw = (string) ($_POST[$key][$zone] ?? '');
                $out[$zone] = array_values(array_filter(array_map('trim', explode(',', $raw))));
            }
            return $out;
        };

        // Светлый логотип: URL из поля, загрузка файла или ранее сохранённое.
        $current = HeaderConfig::get();
        $logoLight = ImageField::resolve(
            'logo_light_file',
            'logo_light',
            (string) ($current['logo_light'] ?? ''),
            Auth::id()
        );

        // Логотипы для каждого языка (основной + светлый), по активным языкам.
        $logoByLang = [];
        $logoLightByLang = [];
        foreach (\App\Models\Language::active() as $lng) {
            $code = (string) $lng['code'];
            $main = ImageField::resolve(
                'logo_lang_' . $code . '_file',
                'logo_lang_' . $code,
                (string) ($current['logo_by_lang'][$code] ?? ''),
                Auth::id()
            );
            if (trim((string) $main) !== '') {
                $logoByLang[$code] = trim((string) $main);
            }
            $light = ImageField::resolve(
                'logo_light_lang_' . $code . '_file',
                'logo_light_lang_' . $code,
                (string) ($current['logo_light_by_lang'][$code] ?? ''),
                Auth::id()
            );
            if (trim((string) $light) !== '') {
                $logoLightByLang[$code] = trim((string) $light);
            }
        }

        HeaderConfig::save([
            'layout' => $_POST['layout'] ?? 'stacked',
            'logo_position' => $_POST['logo_position'] ?? 'left',
            'logo_width' => $_POST['logo_width'] ?? HeaderConfig::DEFAULTS['logo_width'],
            'logo_height' => $_POST['logo_height'] ?? HeaderConfig::DEFAULTS['logo_height'],
            'menu_position' => $_POST['menu_position'] ?? 'right',
            'sticky' => !empty($_POST['header_sticky']),
            'transparent' => !empty($_POST['header_transparent']),
            'logo_light' => $logoLight ?? '',
            'logo_by_lang' => $logoByLang,
            'logo_light_by_lang' => $logoLightByLang,
            'elements' => $parseZones('elements'),
            'elements_mobile' => $parseZones('elements_mobile'),
            'language_switcher' => [
                'enabled' => !empty($_POST['ls_enabled']),
                'format' => $_POST['ls_format'] ?? 'code',
            ],
            'social_buttons' => $social,
            'cta' => [
                'enabled' => !empty($_POST['cta_enabled']),
                'text' => $_POST['cta_text'] ?? '',
                'url' => $_POST['cta_url'] ?? '',
                'style' => $_POST['cta_style'] ?? 'filled',
            ],
            // Pro Max: секции top/bottom, контакты и сниппет.
            'topbar' => [
                'enabled' => !empty($_POST['topbar_enabled']),
                'style' => $_POST['topbar_style'] ?? 'navy',
                'show_mobile' => !empty($_POST['topbar_mobile']),
                'height' => $_POST['topbar_height'] ?? 'normal',
                'zones' => $parseZones('topbar_zones'),
            ],
            'middlebar' => ['height' => $_POST['middlebar_height'] ?? 'normal'],
            'bottombar' => [
                'height' => $_POST['bottombar_height'] ?? 'normal',
                'zones' => $parseZones('bottombar_zones'),
            ],
            'borders' => $_POST['borders'] ?? 'full',
            'contacts' => [
                'phone' => $_POST['contact_phone'] ?? '',
                'email' => $_POST['contact_email'] ?? '',
            ],
            'snippet' => (string) ($_POST['snippet'] ?? ''),
        ]);

        Flash::success('Настройки шапки сохранены.');
        header('Location: /admin/header');
        exit;
    }
}
