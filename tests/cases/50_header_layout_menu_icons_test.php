<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\HeaderConfig;
use App\Models\MenuItem;

test('HeaderConfig: макет валидируется, мусор → stacked', function () {
    $cfg = HeaderConfig::normalize(['layout' => 'drawer']);
    assert_same('drawer', $cfg['layout']);

    $cfg = HeaderConfig::normalize(['layout' => 'нечто']);
    assert_same('stacked', $cfg['layout'], 'недопустимый макет откатывается к stacked');

    // Все 4 макета допустимы.
    foreach (['stacked', 'inline', 'centered', 'drawer'] as $l) {
        assert_same($l, HeaderConfig::normalize(['layout' => $l])['layout']);
    }
});

test('HeaderConfig: конструктор зон — мусор отброшен, дубли уникальны, разделитель повторяем', function () {
    $cfg = HeaderConfig::normalize(['elements' => [
        'left' => ['search', 'нечто', 'divider'],
        'center' => ['search', 'divider'],          // повторный search выкидывается, divider остаётся
        'right' => ['language', 'theme', 'a11y', 'language'],
    ]]);

    assert_same(['search', 'divider'], $cfg['elements']['left'], 'мусор убран, search+divider на месте');
    assert_same(['divider'], $cfg['elements']['center'], 'повторный search убран, divider повторяем');
    assert_same(['language', 'theme', 'a11y'], $cfg['elements']['right'], 'повторный language убран');

    // Пустой конфиг → дефолтная раскладка.
    $def = HeaderConfig::normalize([]);
    assert_same(['search', 'language', 'theme', 'a11y'], $def['elements']['right']);
});

test('MenuItem: SVG-иконка санируется при сохранении, разделитель сохраняется (БД)', function () {
    ensure_test_db();
    $pdo = Database::pdo();
    $pdo->exec('DELETE FROM menu_items');

    // Иконка со скриптом — script вырезается, безопасная разметка остаётся.
    $dirtyIcon = '<svg viewBox="0 0 24 24"><script>alert(1)</script><path d="M3 3h18"/></svg>';
    $id = MenuItem::create([
        'title' => 'Раздел', 'lang' => '', 'url_type' => 'custom', 'url_value' => '/x',
        'is_active' => 1, 'icon_svg' => $dirtyIcon,
    ]);
    $row = MenuItem::findById($id);
    assert_true(!str_contains((string) $row['icon_svg'], '<script'), 'скрипт вырезан из иконки');
    assert_contains('<path', (string) $row['icon_svg'], 'безопасная разметка иконки сохранена');

    // Не-SVG в поле иконки → null.
    $id2 = MenuItem::create([
        'title' => 'Без иконки', 'lang' => '', 'url_type' => 'custom', 'url_value' => '/y',
        'is_active' => 1, 'icon_svg' => 'просто текст',
    ]);
    assert_same(null, MenuItem::findById($id2)['icon_svg']);

    // Разделитель.
    $id3 = MenuItem::create([
        'title' => '—', 'lang' => '', 'url_type' => 'custom', 'url_value' => null,
        'is_active' => 1, 'is_divider' => true,
    ]);
    assert_same(1, (int) MenuItem::findById($id3)['is_divider'], 'разделитель сохранён');

    // Обновление снимает разделитель и меняет иконку.
    MenuItem::update($id3, [
        'title' => 'Теперь ссылка', 'lang' => '', 'url_type' => 'custom', 'url_value' => '/z',
        'is_active' => 1, 'is_divider' => false, 'icon_svg' => '<svg><circle cx="5" cy="5" r="4"/></svg>',
    ]);
    $upd = MenuItem::findById($id3);
    assert_same(0, (int) $upd['is_divider']);
    assert_contains('<circle', (string) $upd['icon_svg']);
});
