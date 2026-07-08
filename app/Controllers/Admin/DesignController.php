<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Csrf;
use App\Core\DesignSettings;
use App\Core\Flash;
use App\Core\View;
use App\Models\Setting;

/**
 * Управление дизайном сайта: готовые конфигурации + точная настройка
 * (визуальные опции карточками, как в тема-билдере). Только супер-админ.
 */
final class DesignController
{
    public function index(): void
    {
        Auth::requireSuperAdmin();
        View::render('admin/design/index', [
            'options' => DesignSettings::OPTIONS,
            'presets' => DesignSettings::PRESETS,
            'userPresets' => DesignSettings::userPresets(),
            'values' => DesignSettings::current(),
            'activePreset' => Setting::get('design_preset', ''),
        ]);
    }

    /**
     * Живое превью: рендерит главную страницу сайта с «примеренными»
     * значениями дизайна из query-строки, ничего не сохраняя. Открывается
     * в iframe панели «Дизайн»; доступно только супер-администратору.
     */
    public function preview(): void
    {
        Auth::requireSuperAdmin();

        // Примеряем каждую валидную опцию поверх сохранённых значений.
        foreach (DesignSettings::OPTIONS as $key => $opt) {
            if (!isset($_GET[$key])) {
                continue;
            }
            $val = DesignSettings::sanitize($key, (string) $_GET[$key]);
            if ($val !== null) {
                Setting::overrideInMemory('design_' . $key, $val);
            }
        }

        // Палитра/шрифт материализуются тоже только в памяти.
        $palette = Setting::get('design_palette', 'custom');
        if ($palette !== 'custom' && isset(DesignSettings::PALETTES[$palette])) {
            Setting::overrideInMemory('color_primary', DesignSettings::PALETTES[$palette][1]);
            Setting::overrideInMemory('color_accent', DesignSettings::PALETTES[$palette][2]);
        }
        $font = Setting::get('design_font_style', 'custom');
        if ($font !== 'custom' && isset(DesignSettings::FONTS[$font])) {
            Setting::overrideInMemory('font_family', DesignSettings::FONTS[$font][1]);
        }

        (new \App\Controllers\Site\PageController())->home();
    }

    /** Сохранить текущие настройки как собственную конфигурацию. */
    public function savePreset(): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        $slug = DesignSettings::saveUserPreset((string) ($_POST['name'] ?? ''));
        if ($slug === null) {
            Flash::error('Не удалось сохранить: укажите название (до 40 символов); максимум 10 конфигураций.');
        } else {
            Flash::success('Конфигурация сохранена. Теперь её можно применить в один клик.');
        }
        header('Location: /admin/design');
        exit;
    }

    /** Удалить собственную конфигурацию. */
    public function deletePreset(): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        if (DesignSettings::deleteUserPreset((string) ($_POST['slug'] ?? ''))) {
            Flash::success('Конфигурация удалена.');
        } else {
            Flash::error('Конфигурация не найдена.');
        }
        header('Location: /admin/design');
        exit;
    }

    public function update(): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        DesignSettings::save($_POST);
        // Ручная правка снимает метку пресета (значения могли разойтись).
        Setting::set('design_preset', '');
        Cache::forgetPrefix('page:');
        Flash::success('Настройки дизайна сохранены.');
        header('Location: /admin/design');
        exit;
    }

    public function applyPreset(): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        $preset = (string) ($_POST['preset'] ?? '');
        if (DesignSettings::applyPreset($preset)) {
            Cache::forgetPrefix('page:');
            $label = DesignSettings::PRESETS[$preset]['label']
                ?? (DesignSettings::userPresets()[substr($preset, 5)]['label'] ?? $preset);
            Flash::success('Конфигурация «' . $label . '» применена.');
        } else {
            Flash::error('Неизвестная конфигурация.');
        }
        header('Location: /admin/design');
        exit;
    }
}
