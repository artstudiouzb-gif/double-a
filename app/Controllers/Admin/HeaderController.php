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

        HeaderConfig::save([
            'logo_position' => $_POST['logo_position'] ?? 'left',
            'menu_position' => $_POST['menu_position'] ?? 'right',
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
