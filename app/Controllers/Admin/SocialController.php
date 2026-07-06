<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\SocialPublisher;
use App\Core\SocialSettings;
use App\Core\View;
use App\Models\Setting;

/**
 * Настройки авто-публикации в соцсети (Facebook / LinkedIn / Instagram).
 * Токены — чувствительные данные, поэтому раздел доступен только
 * супер-администратору.
 */
final class SocialController
{
    public function index(): void
    {
        Auth::requireSuperAdmin();

        $config = [];
        foreach (SocialPublisher::NETWORKS as $net) {
            $config[$net] = [
                'enabled' => SocialSettings::isEnabled($net),
                'ready' => SocialSettings::isReady($net),
                'fields' => SocialSettings::configFor($net),
            ];
        }

        View::render('admin/settings/social', ['config' => $config]);
    }

    public function update(): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        foreach (SocialPublisher::NETWORKS as $net) {
            Setting::set('social_' . $net . '_enabled', !empty($_POST[$net]['enabled']) ? '1' : '0');
            foreach (SocialSettings::FIELDS[$net] as $field) {
                Setting::set('social_' . $net . '_' . $field, trim((string) ($_POST[$net][$field] ?? '')));
            }
        }

        Flash::success('Настройки соцсетей сохранены.');
        header('Location: /admin/social');
        exit;
    }
}
