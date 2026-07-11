<?php

declare(strict_types=1);

use App\Core\WordPressImporter;

// Импорт из WordPress: чистые преобразования (без сети/БД).

test('mapPost извлекает поля поста WP REST', function () {
    $post = [
        'slug' => 'moya-novost',
        'link' => 'https://old.example/2025/03/moya-novost/',
        'date' => '2025-03-14T10:30:00',
        'title' => ['rendered' => 'Заголовок &amp; тест'],
        'excerpt' => ['rendered' => '<p>Краткое   описание.</p>'],
        'content' => ['rendered' => '<p>Тело <img src="https://old.example/a.jpg"></p>'],
        '_embedded' => ['wp:featuredmedia' => [['source_url' => 'https://old.example/cover.jpg']]],
    ];
    $m = WordPressImporter::mapPost($post);
    assert_same('Заголовок & тест', $m['title'], 'HTML-сущности раскодированы, теги убраны');
    assert_same('moya-novost', $m['slug'], 'slug из поста');
    assert_same('Краткое описание.', $m['excerpt'], 'анонс очищен от тегов и лишних пробелов');
    assert_same('2025-03-14 10:30:00', $m['published_at'], 'дата приведена к формату БД');
    assert_same('https://old.example/cover.jpg', $m['featured_url'], 'обложка из featured media');
});

test('extractImageUrls находит все картинки, rewriteImages заменяет по карте', function () {
    $html = '<p><img src="https://o/a.jpg" alt="x"> текст <img src=\'https://o/b.png\'></p>';
    $urls = WordPressImporter::extractImageUrls($html);
    assert_same(2, count($urls), 'найдены обе картинки');
    assert_true(in_array('https://o/a.jpg', $urls, true), 'первая');

    $rewritten = WordPressImporter::rewriteImages($html, [
        'https://o/a.jpg' => '/uploads/public/a.jpg',
        'https://o/b.png' => '/uploads/public/b.png',
    ]);
    assert_true(str_contains($rewritten, '/uploads/public/a.jpg'), 'первая переписана');
    assert_true(!str_contains($rewritten, 'https://o/b.png'), 'вторая заменена');
});

test('normalizeImageUrl снимает Jetpack Photon и query, возвращая оригинал', function () {
    $src = 'https://i0.wp.com/asdr.gov.uz/wp-content/uploads/2026/07/2.jpg?resize=351%2C234&#038;ssl=1';
    assert_same('https://asdr.gov.uz/wp-content/uploads/2026/07/2.jpg', WordPressImporter::normalizeImageUrl($src), 'Photon-обёртка и query сняты');
    $clean = 'https://asdr.gov.uz/wp-content/uploads/2026/07/1-scaled.jpg';
    assert_same($clean, WordPressImporter::normalizeImageUrl($clean), 'чистый URL не изменяется');
});

test('stripResponsiveAttrs убирает srcset/sizes', function () {
    $html = '<img src="/a.jpg" srcset="a 300w, b 1024w" sizes="(max-width: 351px) 100vw, 351px">';
    $out = WordPressImporter::stripResponsiveAttrs($html);
    assert_true(!str_contains($out, 'srcset') && !str_contains($out, 'sizes'), 'srcset и sizes удалены');
    assert_true(str_contains($out, 'src="/a.jpg"'), 'основной src сохранён');
});

test('absoluteUrl абсолютизирует относительные и protocol-relative ссылки', function () {
    $base = 'https://old.example';
    assert_same('https://old.example/x/y.jpg', WordPressImporter::absoluteUrl('/x/y.jpg', $base), 'относительная от корня');
    assert_same('https://cdn/z.jpg', WordPressImporter::absoluteUrl('//cdn/z.jpg', $base), 'protocol-relative');
    assert_same('https://o/w.jpg', WordPressImporter::absoluteUrl('https://o/w.jpg', $base), 'уже абсолютная');
});
