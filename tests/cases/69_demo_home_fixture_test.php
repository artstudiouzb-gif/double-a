<?php

declare(strict_types=1);

// Демо-главная: фикстура блоков и бандл-изображения должны быть на месте и
// валидны, иначе «Загрузить демо-контент» даст сломанную главную.

test('Демо: фикстура главной валидна и содержит ожидаемые блоки', function () {
    $path = APP_ROOT . '/database/demo_assets/home_blocks.json';
    assert_true(is_file($path), 'файл фикстуры существует');

    $blocks = json_decode((string) file_get_contents($path), true);
    assert_true(is_array($blocks) && count($blocks) >= 6, 'минимум 6 блоков главной');

    $types = array_map(static fn ($b) => $b['type'] ?? '', $blocks);
    foreach (['hero', 'counters', 'cards_grid', 'image_cards', 'news_feature', 'media_gallery'] as $need) {
        assert_true(in_array($need, $types, true), "есть блок $need");
    }
});

test('Демо: все изображения фикстуры бандлятся в demo_assets', function () {
    $dir = APP_ROOT . '/database/demo_assets';
    $blocks = json_decode((string) file_get_contents($dir . '/home_blocks.json'), true);

    $images = [];
    array_walk_recursive($blocks, static function ($v, $k) use (&$images) {
        if ($k === 'image' && is_string($v) && $v !== '') {
            $images[] = $v;
        }
    });
    assert_true($images !== [], 'в фикстуре есть изображения');

    foreach (array_unique($images) as $url) {
        $name = basename($url);
        assert_true(is_file($dir . '/' . $name), "изображение $name лежит в demo_assets");
    }
});

test('Демо: прототипные госстраницы собраны из зарегистрированных блоков', function () {
    $path = APP_ROOT . '/database/demo_assets/prototype_pages.json';
    assert_true(is_file($path), 'файл прототипных страниц существует');
    $pages = json_decode((string) file_get_contents($path), true);
    assert_true(is_array($pages), 'фикстура страниц валидна');

    foreach (['napravleniya', 'ustoychivyy-ekonomicheskiy-rost', 'analitika', 'press-centr', 'meropriyatiya', 'karera', 'kontakty', 'strategiya-2030'] as $slug) {
        assert_true(isset($pages[$slug]['ru']['blocks']), "есть страница $slug");
    }
    foreach ($pages as $page) {
        foreach ($page['ru']['blocks'] ?? [] as $block) {
            $type = (string) ($block[0] ?? '');
            assert_true(\App\Core\BlockRenderer::defaultsFor($type) !== [], "тип блока $type зарегистрирован");
        }
    }
});

test('Демо: повторный запуск не удаляет страницы и не дополняет настроенное меню', function () {
    $seeder = (string) file_get_contents(APP_ROOT . '/app/Core/DemoSeeder.php');
    assert_not_contains('DELETE FROM pages', $seeder);
    assert_not_contains('DELETE FROM page_translations', $seeder);
    assert_contains("SELECT COUNT(*) FROM menu_items", $seeder);
    assert_contains("prototype_pages.json", $seeder);
    assert_contains('isUntouchedStarterHome', $seeder);
});

test('Демо: запуск доступен только в настройках и требует код подтверждения', function () {
    $dashboard = (string) file_get_contents(APP_ROOT . '/app/Views/admin/dashboard.php');
    $settings = (string) file_get_contents(APP_ROOT . '/app/Views/admin/settings/index.php');
    $controller = (string) file_get_contents(APP_ROOT . '/app/Controllers/Admin/SettingsController.php');
    $routes = (string) file_get_contents(APP_ROOT . '/public/index.php');

    assert_not_contains('/admin/demo-content', $dashboard);
    assert_contains('/admin/settings/demo-content', $settings);
    assert_contains('demo_confirm_code', $settings);
    assert_contains("DEMO_CONFIRM_CODE = 'DEMO'", $controller);
    assert_contains('/admin/settings/demo-content', $routes);
    assert_not_contains("[DashboardController::class, 'seedDemo']", $routes);
});
