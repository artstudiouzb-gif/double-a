<?php

declare(strict_types=1);

test('public editorial surfaces use the shared rich content class', function (): void {
    $root = dirname(__DIR__, 2);
    $files = [
        '/templates/blocks/text.php',
        '/templates/blocks/faq.php',
        '/app/Views/site/news_show.php',
        '/app/Views/site/project_show.php',
        '/app/Views/site/content_show.php',
    ];

    foreach ($files as $file) {
        $view = file_get_contents($root . $file);
        assert_true(is_string($view));
        assert_contains('rich-content', $view);
    }
});

test('rich content stylesheet covers editorial elements and responsive tables', function (): void {
    $root = dirname(__DIR__, 2);
    $css = file_get_contents($root . '/public/assets/css/rich-content.css');
    $header = file_get_contents($root . '/app/Views/site/_header.php');

    assert_true(is_string($css));
    assert_true(is_string($header));
    assert_contains(':where(.rich-content) blockquote {', $css);
    assert_contains(':where(.rich-content) table {', $css);
    assert_contains(':where(.rich-content) pre {', $css);
    assert_contains('@media (max-width: 720px)', $css);
    assert_contains('overflow-x: auto;', $css);
    assert_contains("Asset::url('/assets/css/rich-content.css')", $header);
});

test('детальный проект использует всю ширину контейнера для rich-content', function (): void {
    $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/gov-theme.css');

    assert_contains('.projdetail__content { width: 100%; max-width: none; }', $css);
    assert_false(str_contains($css, '.projdetail__content { max-width: 76ch; }'));
});

test('TinyMCE: стандартный вид без узкой колонки и без редакционных стилей', function (): void {
    $editor = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/vendor/editor.js');

    assert_true(is_string($editor));
    assert_false(str_contains($editor, 'rich-content'), 'редакционные стили убраны из редактора');
    assert_contains('max-width: none', $editor);
});

test('TinyMCE: самохостинг без внешнего CDN', function (): void {
    $root = dirname(__DIR__, 2);
    $editor = (string) file_get_contents($root . '/public/assets/js/vendor/editor.js');

    assert_false(str_contains($editor, 'cdn.jsdelivr.net'), 'нет ссылок на внешний CDN');
    assert_contains("script.src = '/assets/js/vendor/tinymce/tinymce.min.js'", $editor);
    assert_contains("base_url: '/assets/js/vendor/tinymce'", $editor);
    // Локальный движок и язык физически присутствуют.
    assert_true(is_file($root . '/public/assets/js/vendor/tinymce/tinymce.min.js'), 'движок на месте');
    assert_true(is_file($root . '/public/assets/js/vendor/tinymce/langs/ru.js'), 'русский язык на месте');
    assert_true(is_file($root . '/public/assets/js/vendor/tinymce/themes/silver/theme.min.js'), 'тема на месте');
});
