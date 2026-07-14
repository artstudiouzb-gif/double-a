<?php

declare(strict_types=1);

use App\Core\DesignSettings;

test('единый выбор основного шрифта нормализует базовые, Google и собственный варианты', function (): void {
    assert_same(
        ['font_style' => 'serif', 'font_google_body' => ''],
        DesignSettings::normalizeBodyFontChoice('style:serif')
    );
    assert_same(
        ['font_style' => 'system', 'font_google_body' => 'inter'],
        DesignSettings::normalizeBodyFontChoice('google:inter')
    );
    assert_same(
        ['font_style' => 'custom', 'font_google_body' => ''],
        DesignSettings::normalizeBodyFontChoice('google:unknown')
    );
});
test('точные размеры принимают только безопасные значения в заданных диапазонах', function (): void {
    assert_same('16.5px', DesignSettings::normalizeFontSize('16,5'));
    assert_same('24px', DesignSettings::normalizeFontSize('24px'));
    assert_same('', DesignSettings::normalizeFontSize('11'));
    assert_same('', DesignSettings::normalizeFontSize('25'));
    assert_same('0px', DesignSettings::normalizeRadius('0'));
    assert_same('12.5px', DesignSettings::normalizeRadius('12.5'));
    assert_same('', DesignSettings::normalizeRadius('49'));
    assert_same('', DesignSettings::normalizeRadius('calc(1px)'));
});

test('форма дизайна объединяет источники шрифта и показывает точные размеры', function (): void {
    $view = file_get_contents(dirname(__DIR__, 2) . '/app/Views/admin/design/index.php');
    assert_true(is_string($view));
    assert_contains('name="font_body_choice"', $view);
    assert_contains('optgroup label="Google Fonts"', $view);
    assert_contains('data-custom-font-fields', $view);
    assert_contains('name="font_size_custom"', $view);
    assert_contains('name="radius_custom"', $view);
    assert_not_contains('<h2 class="design-section__title">Google-шрифты</h2>', $view);
});

test('точные размеры сохраняются и переопределяют CSS-переменные (БД)', function (): void {
    ensure_test_db();
    DesignSettings::save([
        'font_body_choice' => 'google:inter',
        'font_size_custom' => '17.5',
        'radius_custom' => '13',
    ]);

    $css = DesignSettings::cssVariables(DesignSettings::current());
    assert_contains('--base-font-size:17.5px', $css);
    assert_contains('--radius:13px', $css);
    assert_contains('--btn-radius:13px', $css);
    assert_same('inter', (string) \App\Models\Setting::get('design_font_google_body', ''));
    assert_contains('Inter', (string) \App\Models\Setting::get('font_family', ''));
});
