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

test('DesignSettings: масштаб заголовков — статичный режим даёт класс, плавающий нет', function () {
    assert_same('static', DesignSettings::sanitize('type_scale', 'static'));
    assert_same('fluid', DesignSettings::sanitize('type_scale', 'bogus')); // default

    $base = DesignSettings::PRESETS['classic']['values'];
    assert_not_contains('design-type-static', DesignSettings::bodyClasses($base)); // без ключа — плавающие
    assert_contains('design-type-static', DesignSettings::bodyClasses(['type_scale' => 'static'] + $base));
    assert_not_contains('design-type-static', DesignSettings::bodyClasses(['type_scale' => 'fluid'] + $base));
});

test('DesignSettings: кнопка «Наверх» — тумблер даёт/убирает класс design-scrolltop', function () {
    assert_same('on', DesignSettings::sanitize('scroll_top', 'on'));
    assert_same('on', DesignSettings::sanitize('scroll_top', 'bogus')); // default — включена

    $base = DesignSettings::PRESETS['classic']['values'];
    assert_contains('design-scrolltop', DesignSettings::bodyClasses($base)); // в пресетах включена
    assert_contains('design-scrolltop', DesignSettings::bodyClasses(['scroll_top' => 'on'] + $base));
    assert_not_contains('design-scrolltop', DesignSettings::bodyClasses(['scroll_top' => 'off'] + $base));
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
    assert_same('#173a63', \App\Models\Setting::get('color_primary'));
    assert_same('#17999b', \App\Models\Setting::get('color_accent'));
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

test('Пользовательские конфигурации: сохранить/применить/удалить + снапшот своих цветов (БД)', function () {
    ensure_test_db();
    \App\Models\Setting::set('design_user_presets', '');

    // Текущее состояние: палитра custom с ручными цветами.
    DesignSettings::save(['palette' => 'custom', 'font_style' => 'custom', 'container' => 'narrow']);
    \App\Models\Setting::set('color_primary', '#123456');
    \App\Models\Setting::set('color_accent', '#654321');

    $slug = DesignSettings::saveUserPreset('Моя тема');
    assert_true($slug !== null, 'пресет сохранён');
    assert_true(isset(DesignSettings::userPresets()[$slug]), 'в списке');

    // Меняем всё, затем применяем пресет — опции и ручные цвета вернулись.
    DesignSettings::save(['palette' => 'gov_blue', 'container' => 'wide']);
    assert_same('#173a63', \App\Models\Setting::get('color_primary'));

    assert_true(DesignSettings::applyPreset('user:' . $slug));
    $cur = DesignSettings::current();
    assert_same('narrow', $cur['container']);
    assert_same('custom', $cur['palette']);
    assert_same('#123456', \App\Models\Setting::get('color_primary'));
    assert_same('#654321', \App\Models\Setting::get('color_accent'));

    // Пустое имя — отказ; удаление работает.
    assert_true(DesignSettings::saveUserPreset('  ') === null);
    assert_true(DesignSettings::deleteUserPreset($slug));
    assert_false(isset(DesignSettings::userPresets()[$slug]));
    assert_false(DesignSettings::applyPreset('user:' . $slug));
});

test('Setting::overrideInMemory меняет значение только в памяти, БД не трогает (БД)', function () {
    ensure_test_db();
    \App\Models\Setting::set('design_header_style', 'light');

    \App\Models\Setting::overrideInMemory('design_header_style', 'dark');
    assert_same('dark', \App\Models\Setting::get('design_header_style'));

    // В БД осталось сохранённое значение.
    $stmt = \App\Core\Database::pdo()->prepare('SELECT `value` FROM settings WHERE `key` = :k');
    $stmt->execute([':k' => 'design_header_style']);
    assert_same('light', (string) $stmt->fetchColumn());

    // После сброса кэша (любой set) возвращается значение из БД.
    \App\Models\Setting::set('site_name_probe', 'x');
    assert_same('light', \App\Models\Setting::get('design_header_style'));
});

test('Google-шрифты: выбранный шрифт имеет приоритет, пусто возвращает базовый стиль', function () {
    ensure_test_db();

    DesignSettings::save([
        'font_style' => 'serif',
        'font_google_heading' => 'playfair',
        'font_google_body' => 'inter',
    ]);
    assert_contains('Playfair Display', (string) \App\Models\Setting::get('font_heading', ''));
    assert_contains('Inter', (string) \App\Models\Setting::get('font_family', ''));

    $href = DesignSettings::googleFontsHref();
    assert_true($href !== null, 'ссылка на css2 построена');
    assert_contains('fonts.googleapis.com/css2', (string) $href);
    assert_contains('Playfair+Display', (string) $href);
    assert_contains('Inter', (string) $href);
    assert_contains('display=swap', (string) $href);

    // Сброс: заголовки возвращаются к PT, текст — к карточке serif выше.
    DesignSettings::save([
        'font_style' => 'serif',
        'font_google_heading' => '',
        'font_google_body' => '',
    ]);
    assert_contains('PT Serif', (string) \App\Models\Setting::get('font_heading', ''));
    assert_contains('Georgia', (string) \App\Models\Setting::get('font_family', ''));
    assert_true(DesignSettings::googleFontsHref() === null, 'без выбора ссылки нет');
});

test('Google-шрифты: неизвестный slug игнорируется', function () {
    ensure_test_db();
    DesignSettings::save(['font_google_heading' => 'evil-font']);
    assert_same('', (string) \App\Models\Setting::get('design_font_google_heading', ''));
});
