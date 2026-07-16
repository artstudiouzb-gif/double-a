<?php

declare(strict_types=1);

use App\Core\View;

test('Хлебные крошки публичных шаблонов переводят Главную', function () {
    $views = [
        'news_show.php',
        'project_show.php',
        'content_show.php',
        'content_index.php',
        'calendar.php',
    ];

    foreach ($views as $view) {
        $source = file_get_contents(APP_ROOT . '/app/Views/site/' . $view);
        assert_true($source !== false, 'шаблон доступен: ' . $view);
        assert_contains("t('Главная')", (string) $source);
        assert_not_contains("['label' => 'Главная'", (string) $source);
    }
});

test('Ведущая новость на главной использует полноширинное вертикальное построение', function () {
    $css = file_get_contents(APP_ROOT . '/public/assets/css/gov-theme.css');
    assert_true($css !== false, 'CSS гос-темы доступен');
    assert_contains('.newsfeat-lead { display: flex; flex-direction: column;', (string) $css);
    assert_contains('.newsfeat-lead__media { display: block; width: 100%; aspect-ratio: 16/10;', (string) $css);
});

test('Публичные календарь и альбомы не содержат непереведённых основных подписей', function () {
    $calendar = (string) file_get_contents(APP_ROOT . '/app/Views/site/calendar.php');
    $albums = (string) file_get_contents(APP_ROOT . '/app/Views/site/albums.php');
    assert_contains("t('Календарь мероприятий')", $calendar);
    assert_contains("t('Предыдущий месяц')", $calendar);
    assert_contains("t('Фотоальбомы')", $albums);
    assert_not_contains('>Главная</a>', $albums);
});

test('Хлебные крошки: рендерит навигацию со ссылками, последний — текст', function () {
    $html = View::renderPartial('site/_crumbs', [
        'crumbs' => [
            ['label' => 'Home', 'url' => '/'],
            ['label' => 'Section', 'url' => '/section'],
            ['label' => 'Current'],
        ],
    ]);
    assert_contains('content-crumbs', $html);
    assert_contains('href="/"', $html);
    assert_contains('href="/section"', $html);
    assert_contains('<span>Current</span>', $html);
    // Текущий элемент не должен быть ссылкой.
    assert_not_contains('href="/current"', $html);
    // SEO: та же навигация в разметке Schema.org BreadcrumbList.
    assert_contains('application/ld+json', $html);
    assert_contains('BreadcrumbList', $html);
    assert_contains('"position":3', $html);
});

test('Хлебные крошки: скрываются при менее чем двух уровнях', function () {
    $html = View::renderPartial('site/_crumbs', ['crumbs' => [['label' => 'Only']]]);
    assert_not_contains('content-crumbs', $html);
    $empty = View::renderPartial('site/_crumbs', ['crumbs' => []]);
    assert_not_contains('content-crumbs', $empty);
});
