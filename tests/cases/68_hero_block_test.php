<?php

declare(strict_types=1);

use App\Core\BlockRenderer;

// Hero: фон видео/YouTube/фото, overlay с цветом и прозрачностью, позиция
// текста и подложка под текстом.

function render_hero(array $data): string
{
    return BlockRenderer::render(['id' => 1, 'type' => 'hero', 'data' => json_encode($data), 'custom_css' => null])['html'];
}

test('Hero: YouTube-фон рендерит iframe с nocookie-доменом и id', function () {
    $html = render_hero([
        'title' => 'Заголовок',
        'bg_type' => 'youtube',
        'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);
    assert_true(str_contains($html, 'youtube-nocookie.com/embed/dQw4w9WgXcQ'), 'iframe YouTube с id');
    assert_true(str_contains($html, 'block-hero--video'), 'класс видео-героя');
    assert_true(str_contains($html, 'autoplay=1&mute=1&loop=1'), 'автозапуск без звука, цикл');
});

test('Hero: overlay использует заданный цвет и прозрачность', function () {
    $html = render_hero([
        'title' => 'X', 'bg_type' => 'image', 'image' => '/uploads/public/x.jpg',
        'overlay_color' => '#123456', 'overlay_opacity' => 80,
    ]);
    // #123456 = rgb(18,52,86), 80% => 0.8
    assert_true(str_contains($html, 'rgba(18,52,86, 0.8)'), 'overlay rgba из цвета и прозрачности');
});

test('Hero: позиция текста и подложка отражаются в разметке', function () {
    $html = render_hero([
        'title' => 'X', 'bg_type' => 'image', 'image' => '/uploads/public/x.jpg',
        'text_position' => 'center',
        'panel_enabled' => true, 'panel_color' => '#000000', 'panel_opacity' => 50,
    ]);
    assert_true(str_contains($html, 'block-hero--pos-center'), 'класс позиции текста');
    assert_true(str_contains($html, 'block-hero__text--panel'), 'класс подложки');
    assert_true(str_contains($html, 'rgba(0,0,0, 0.5)'), 'подложка rgba из цвета и прозрачности');
});

test('Hero: цвет текста и кнопок отдаются CSS-переменными', function () {
    $html = render_hero([
        'title' => 'X', 'bg_type' => 'image', 'image' => '/x.jpg',
        'text_color' => '#112233', 'button_color' => '#aabbcc',
        'button_text' => 'Кнопка', 'button_url' => '/o-nas',
    ]);
    assert_true(str_contains($html, '--hero-text:#112233'), 'переменная цвета текста');
    assert_true(str_contains($html, '--hero-btn:#aabbcc'), 'переменная цвета кнопок');
});

test('Hero: свой цвет фона под текстом — не зависящий от темы градиент', function () {
    $html = render_hero(['title' => 'X', 'bg_type' => 'none', 'bg_color' => '#123a6b', 'text_position' => 'left']);
    assert_true(str_contains($html, 'block-hero--bgcolor'), 'класс цветного фона');
    assert_true(str_contains($html, 'linear-gradient(90deg, rgba(18,58,107'), 'градиент выбранного цвета');
});

test('Hero: без bg_type определяет тип по заполненным полям (обратная совместимость)', function () {
    $html = render_hero(['title' => 'X', 'image' => '/uploads/public/x.jpg']);
    assert_true(str_contains($html, 'block-hero--media'), 'старый блок с картинкой = медиа-герой');
    assert_true(!str_contains($html, 'block-hero--video'), 'без видео нет video-класса');
});
