<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\HeaderConfig;
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
        // заполняемые drag-and-drop в админке).
        $elements = [];
        foreach (['left', 'center', 'right'] as $zone) {
            $raw = (string) ($_POST['elements'][$zone] ?? '');
            $elements[$zone] = array_values(array_filter(array_map('trim', explode(',', $raw))));
        }

        HeaderConfig::save([
            'layout' => $_POST['layout'] ?? 'stacked',
            'logo_position' => $_POST['logo_position'] ?? 'left',
            'menu_position' => $_POST['menu_position'] ?? 'right',
            'elements' => $elements,
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
        ]);

        Flash::success('Настройки шапки сохранены.');
        header('Location: /admin/header');
        exit;
    }
}
