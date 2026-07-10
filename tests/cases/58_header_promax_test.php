<?php

declare(strict_types=1);

use App\Core\HeaderConfig;

test('HeaderConfig Pro Max: секции top/bottom нормализуются, мусор отброшен', function () {
    $cfg = HeaderConfig::normalize([
        'topbar' => [
            'enabled' => '1', 'style' => 'evil', 'show_mobile' => 1,
            'zones' => ['left' => ['phone', 'email', 'hack'], 'right' => ['language', 'divider', 'divider']],
        ],
        'bottombar' => ['zones' => ['right' => ['search', 'search']]],
        'contacts' => ['phone' => ' +998 71 203 10 00 ', 'email' => 'info@strategy.uz'],
        'snippet' => '<b>Работаем</b><script>alert(1)</script>',
    ]);
    assert_true($cfg['topbar']['enabled'], 'topbar включён');
    assert_same('navy', $cfg['topbar']['style'], 'недопустимый стиль -> navy');
    assert_true($cfg['topbar']['show_mobile']);
    assert_same(['phone', 'email'], $cfg['topbar']['zones']['left'], 'неизвестный элемент отброшен');
    assert_same(['language', 'divider', 'divider'], $cfg['topbar']['zones']['right'], 'divider повторяем');
    assert_same(['search'], $cfg['bottombar']['zones']['right'], 'дубль неповторяемого убран');
    assert_same('+998 71 203 10 00', $cfg['contacts']['phone']);
    assert_contains('<b>Работаем</b>', $cfg['snippet']);
    assert_true(!str_contains($cfg['snippet'], '<script'), 'script вырезан санитайзером');
});

test('HeaderConfig Pro Max: элемент может повторяться в разных секциях', function () {
    $cfg = HeaderConfig::normalize([
        'topbar' => ['enabled' => 1, 'zones' => ['right' => ['language']]],
        'elements' => ['right' => ['language', 'search']],
    ]);
    assert_same(['language'], $cfg['topbar']['zones']['right']);
    assert_same(['language', 'search'], $cfg['elements']['right'], 'уникальность — в пределах секции');
});

test('HeaderConfig: sticky и transparent нормализуются в булевы', function () {
    $cfg = HeaderConfig::normalize(['sticky' => '1', 'transparent' => 'yes']);
    assert_true($cfg['sticky'] === true && $cfg['transparent'] === true);
    $off = HeaderConfig::normalize([]);
    assert_true($off['sticky'] === false && $off['transparent'] === false);
});

test('HeaderConfig: высоты секций и режим линий нормализуются', function () {
    $cfg = HeaderConfig::normalize([
        'topbar' => ['height' => 'tall'],
        'middlebar' => ['height' => 'huge'],
        'bottombar' => ['height' => 'slim'],
        'borders' => 'container',
    ]);
    assert_same('tall', $cfg['topbar']['height']);
    assert_same('normal', $cfg['middlebar']['height'], 'неизвестная высота -> normal');
    assert_same('slim', $cfg['bottombar']['height']);
    assert_same('container', $cfg['borders']);
    assert_same('full', HeaderConfig::normalize(['borders' => 'evil'])['borders']);
});

test('Блок hero: классы ширины и высоты секции', function () {
    $out = \App\Core\BlockRenderer::render(['id' => 60, 'type' => 'hero', 'custom_css' => null, 'data' => json_encode([
        'title' => 'T', 'image' => '/uploads/public/x.jpg', 'width' => 'standard', 'height' => 'full',
    ])])['html'];
    assert_contains('block-hero--w-standard', $out);
    assert_contains('block-hero--h-full', $out);
    $def = \App\Core\BlockRenderer::render(['id' => 61, 'type' => 'hero', 'custom_css' => null, 'data' => json_encode(['title' => 'T'])])['html'];
    assert_contains('block-hero--w-full', $def);
    assert_contains('block-hero--h-regular', $def);
});
