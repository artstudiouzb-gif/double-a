<?php

declare(strict_types=1);

use App\Core\BlockRenderer;

test('Блок hero: титул, подзаголовок, фон-фото, безопасная кнопка', function () {
    $out = BlockRenderer::render(['id' => 20, 'type' => 'hero', 'custom_css' => null, 'data' => json_encode([
        'title' => 'Пресс-центр', 'subtitle' => 'Оперативная информация',
        'image' => '/uploads/public/x.jpg', 'button_text' => 'Все новости', 'button_url' => '/news',
    ])])['html'];
    assert_contains('cms-block cms-block--hero', $out);
    assert_contains('block-hero--image', $out);
    assert_contains('Пресс-центр', $out);
    assert_contains("url('/uploads/public/x.jpg')", $out);
    assert_contains('href="/news"', $out);

    // Небезопасная ссылка кнопки не выводится.
    $bad = BlockRenderer::render(['id' => 21, 'type' => 'hero', 'custom_css' => null, 'data' => json_encode([
        'title' => 'T', 'button_text' => 'X', 'button_url' => 'javascript:alert(1)',
    ])])['html'];
    assert_true(!str_contains($bad, 'block-hero__button'), 'javascript: кнопка не рендерится');
});

test('Блок categories_grid: плитки, первая активна', function () {
    $out = BlockRenderer::render(['id' => 22, 'type' => 'categories_grid', 'custom_css' => null, 'data' => json_encode([
        'title' => 'Категории', 'items' => [
            ['icon_svg' => '<svg><rect/></svg>', 'label' => 'Новости', 'url' => '/news'],
            ['icon_svg' => '', 'label' => 'Видео', 'url' => ''],
        ],
    ])])['html'];
    assert_contains('cms-block--categories_grid', $out);
    assert_contains('cat-tile is-active', $out);
    assert_contains('href="/news"', $out);
    // Пункт без URL — span, не ссылка.
    assert_contains('<span class="cat-tile"', $out);
});

test('Блок media_materials: элементы с действием и заглушка', function () {
    $out = BlockRenderer::render(['id' => 23, 'type' => 'media_materials', 'custom_css' => null, 'data' => json_encode([
        'title' => 'Медиа', 'items' => [['icon_svg' => '', 'label' => 'Фото', 'action' => 'Смотреть', 'url' => '/albums']],
    ])])['html'];
    assert_contains('cms-block--media_materials', $out);
    assert_contains('media-item__action', $out);
    assert_contains('Смотреть', $out);

    $empty = BlockRenderer::render(['id' => 24, 'type' => 'media_materials', 'custom_css' => null, 'data' => json_encode(['title' => '', 'items' => []])])['html'];
    assert_contains('block-media__empty', $empty);
});
