<?php

declare(strict_types=1);

use App\Models\News;

test('Предзагруженная обложка галереи не требует отдельного SQL-запроса', function () {
    assert_same('/uploads/public/news/cover.webp', News::getCoverImage([
        'id' => 42,
        'image' => '',
        'video_url' => '',
        'first_gallery_image' => '/uploads/public/news/cover.webp',
    ]));
});

test('Списки новостей загружают переводы одним пакетным запросом', function () {
    $news = (string) file_get_contents(APP_ROOT . '/app/Models/News.php');
    $translations = (string) file_get_contents(APP_ROOT . '/app/Models/NewsTranslation.php');

    assert_contains('self::localizeRows($rows, $lang)', $news);
    assert_contains('NewsTranslation::forNewsIds(', $news);
    assert_contains('WHERE news_id IN ({$placeholders}) AND lang = ?', $translations);
});

test('Списки новостей и меню повторно используются внутри одного запроса', function () {
    $news = (string) file_get_contents(APP_ROOT . '/app/Models/News.php');
    $menu = (string) file_get_contents(APP_ROOT . '/app/Models/MenuItem.php');

    assert_contains('$publishedRequestCache', $news);
    assert_contains('$activeRequestCache', $menu);
});

test('Страница не предзагружает все локальные шрифты одновременно', function () {
    $header = (string) file_get_contents(APP_ROOT . '/app/Views/site/_header.php');
    assert_contains('$fontPreloads', $header);
    assert_contains('array_keys($fontPreloads)', $header);
});

test('Сервер включает сжатие текста и откладывает тяжёлый hero media', function () {
    $htaccess = (string) file_get_contents(APP_ROOT . '/public/.htaccess');
    $hero = (string) file_get_contents(APP_ROOT . '/templates/blocks/hero.php');
    assert_contains('BROTLI_COMPRESS', $htaccess);
    assert_contains('DEFLATE', $htaccess);
    assert_contains('preload="metadata"', $hero);
    assert_contains('loading="lazy"', $hero);
});
