<?php

declare(strict_types=1);

use App\Core\DesignSettings;

test('DesignSettings::sanitize отбрасывает неизвестные значения к дефолту', function () {
    assert_same('wide', DesignSettings::sanitize('container', 'wide'));
    assert_same('standard', DesignSettings::sanitize('container', 'bogus')); // default
    assert_true(DesignSettings::sanitize('nope', 'x') === null);
});

test('DesignSettings::cssVariables формирует корректные переменные', function () {
    $css = DesignSettings::cssVariables([
        'container' => 'wide', 'radius' => 'large', 'card_gap' => 'lg', 'density' => 'spacious',
        'button' => 'pill', 'catalog_layout' => 'cards_lg', 'header_style' => 'accent', 'header_sticky' => 'on',
    ]);
    assert_contains('--container-max:1360px', $css);
    assert_contains('--radius:22px', $css);
    assert_contains('--card-gap:32px', $css);
    assert_contains('--btn-radius:999px', $css);
});

test('DesignSettings::bodyClasses отражает макет каталога, шапку и фиксацию', function () {
    $on = DesignSettings::bodyClasses([
        'container' => 'standard', 'radius' => 'small', 'card_gap' => 'sm', 'density' => 'standard',
        'button' => 'rounded', 'catalog_layout' => 'list', 'header_style' => 'dark', 'header_sticky' => 'on',
    ]);
    assert_contains('design-catalog-list', $on);
    assert_contains('design-header-dark', $on);
    assert_contains('design-header-sticky', $on);

    $off = DesignSettings::bodyClasses([
        'container' => 'standard', 'radius' => 'small', 'card_gap' => 'sm', 'density' => 'standard',
        'button' => 'rounded', 'catalog_layout' => 'cards_sm', 'header_style' => 'light', 'header_sticky' => 'off',
    ]);
    assert_not_contains('design-header-sticky', $off);
    assert_contains('design-catalog-cards_sm', $off);
});

test('DesignSettings::bodyClasses включает тип поиска, шаблон детали и футер', function () {
    $cls = DesignSettings::bodyClasses(DesignSettings::PRESETS['modern']['values']);
    assert_contains('design-search-overlay', $cls);
    assert_contains('design-detail-sidebar', $cls);
    assert_contains('design-footer-columns', $cls);
    assert_contains('design-cards-elevated', $cls);
    assert_contains('design-sidebar-floating', $cls);

    $min = DesignSettings::bodyClasses(DesignSettings::PRESETS['minimal']['values']);
    assert_contains('design-search-overlay', $min);
    assert_contains('design-detail-plain', $min);
    assert_contains('design-footer-minimal', $min);
});

test('DesignSettings::cssVariables задаёт тень карточек по стилю', function () {
    $flat = DesignSettings::cssVariables(DesignSettings::PRESETS['minimal']['values']);
    assert_contains('--card-shadow:none', $flat);
    $elevated = DesignSettings::cssVariables(DesignSettings::PRESETS['modern']['values']);
    assert_contains('--card-shadow:0 10px 30px', $elevated);
});

test('DesignSettings пресеты покрывают все опции валидными значениями', function () {
    foreach (DesignSettings::PRESETS as $name => $preset) {
        foreach (DesignSettings::OPTIONS as $key => $opt) {
            assert_true(isset($preset['values'][$key]), "пресет {$name} задаёт опцию {$key}");
            assert_true(
                isset($opt['choices'][$preset['values'][$key]]),
                "пресет {$name}: значение {$key} допустимо"
            );
        }
    }
});

test('Палитра материализуется в color_primary/color_accent; custom не трогает (БД)', function () {
    ensure_test_db();
    \App\Models\Setting::set('color_primary', '#010101');
    \App\Models\Setting::set('color_accent', '#020202');

    // Применяем палитру gov_blue — цвета перезаписаны.
    DesignSettings::save(['palette' => 'gov_blue', 'font_style' => 'serif']);
    assert_same('#1f4b8e', \App\Models\Setting::get('color_primary'));
    assert_same('#0f766e', \App\Models\Setting::get('color_accent'));
    assert_contains('Georgia', \App\Models\Setting::get('font_family'));

    // Возврат на custom: ставим ручные значения — save их не перетирает.
    \App\Models\Setting::set('color_primary', '#0a0b0c');
    DesignSettings::save(['palette' => 'custom', 'font_style' => 'custom']);
    assert_same('#0a0b0c', \App\Models\Setting::get('color_primary'));
});

test('Каждая палитра пресетов существует и полна', function () {
    foreach (DesignSettings::PRESETS as $name => $preset) {
        $pal = $preset['values']['palette'] ?? null;
        assert_true(isset(DesignSettings::PALETTES[$pal]), "палитра пресета {$name}");
        $font = $preset['values']['font_style'] ?? null;
        assert_true(isset(DesignSettings::FONTS[$font]), "шрифт пресета {$name}");
    }
});
