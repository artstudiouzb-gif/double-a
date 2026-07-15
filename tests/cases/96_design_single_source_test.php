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
    assert_contains('name="bg_primary"', $designView);
    assert_contains('name="bg_surface"', $designView);
    assert_contains('name="text_main"', $designView);
    assert_contains('name="text_muted"', $designView);
    assert_contains('name="border_color"', $designView);
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
        'bg_primary' => '#fafafa',
        'bg_surface' => '#f0f2f5',
        'text_main' => '#202124',
        'text_muted' => '#62666d',
        'border_color' => '#d8dce2',
    ]);
    assert_same('#102030', \App\Models\Setting::get('color_primary'));
    assert_same('#40a0b0', \App\Models\Setting::get('color_accent'));
    assert_contains('Brand Sans', (string) \App\Models\Setting::get('font_family'));
    assert_same('auto', \App\Models\Setting::get('default_theme'));
    assert_same('#fafafa', DesignSettings::semanticColors()['bg_primary']);
    assert_same('#f0f2f5', DesignSettings::semanticColors()['bg_surface']);
    assert_same('#202124', DesignSettings::semanticColors()['text_main']);
    assert_same('#62666d', DesignSettings::semanticColors()['text_muted']);
    assert_same('#d8dce2', DesignSettings::semanticColors()['border_color']);

    DesignSettings::save(['palette' => 'gov_blue', 'font_style' => 'system']);
    assert_same('#173a63', \App\Models\Setting::get('color_primary'));

    DesignSettings::save(['palette' => 'custom', 'font_style' => 'custom']);
    assert_same('#102030', \App\Models\Setting::get('color_primary'));
    assert_same('#40a0b0', \App\Models\Setting::get('color_accent'));
    assert_contains('Brand Sans', (string) \App\Models\Setting::get('font_family'));
    assert_same('#fafafa', DesignSettings::semanticColors()['bg_primary']);
});

test('семантические цвета выводятся как переменные общей и государственной темы', function (): void {
    $header = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Views/site/_header.php');
    foreach (['--bg-primary:', '--bg-surface:', '--text-main:', '--text-muted:', '--border-color:'] as $variable) {
        assert_contains($variable, $header);
    }
    assert_contains('--gov-bg: var(--bg-primary)', $header);
    assert_contains('--gov-surface: var(--bg-surface)', $header);
    assert_contains('--gov-ink: var(--text-main)', $header);
});
