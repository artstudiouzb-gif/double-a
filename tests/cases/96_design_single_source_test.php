<?php

declare(strict_types=1);

use App\Core\DesignSettings;

test('цвета и шрифты редактируются только в разделе дизайна', function (): void {
    $settingsView = file_get_contents(dirname(__DIR__, 2) . '/app/Views/admin/settings/index.php');
    $designView = file_get_contents(dirname(__DIR__, 2) . '/app/Views/admin/design/index.php');

    assert_true(is_string($settingsView));
    assert_true(is_string($designView));
    assert_not_contains('name="color_primary"', $settingsView);
    assert_not_contains('name="color_accent"', $settingsView);
    assert_not_contains('name="font_family"', $settingsView);
    assert_not_contains('name="default_theme"', $settingsView);
    assert_contains('Открыть управление дизайном', $settingsView);

    assert_contains('name="color_primary"', $designView);
    assert_contains('name="color_accent"', $designView);
    assert_contains('name="font_family"', $designView);
    assert_contains('name="font_face_name"', $designView);
    assert_contains('name="font_url"', $designView);
    assert_contains('name="default_theme"', $designView);
});

test('ручное оформление сохраняется отдельно и возвращается после пресета', function (): void {
    ensure_test_db();

    DesignSettings::save([
        'palette' => 'custom',
        'font_style' => 'custom',
        'color_primary' => '#102030',
        'color_accent' => '#40a0b0',
        'font_family' => "'Brand Sans', system-ui, sans-serif",
        'default_theme' => 'auto',
    ]);
    assert_same('#102030', \App\Models\Setting::get('color_primary'));
    assert_same('#40a0b0', \App\Models\Setting::get('color_accent'));
    assert_contains('Brand Sans', (string) \App\Models\Setting::get('font_family'));
    assert_same('auto', \App\Models\Setting::get('default_theme'));

    DesignSettings::save(['palette' => 'gov_blue', 'font_style' => 'system']);
    assert_same('#173a63', \App\Models\Setting::get('color_primary'));

    DesignSettings::save(['palette' => 'custom', 'font_style' => 'custom']);
    assert_same('#102030', \App\Models\Setting::get('color_primary'));
    assert_same('#40a0b0', \App\Models\Setting::get('color_accent'));
    assert_contains('Brand Sans', (string) \App\Models\Setting::get('font_family'));
});
