<?php

declare(strict_types=1);

use App\Core\View;

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
});

test('Хлебные крошки: скрываются при менее чем двух уровнях', function () {
    $html = View::renderPartial('site/_crumbs', ['crumbs' => [['label' => 'Only']]]);
    assert_not_contains('content-crumbs', $html);
    $empty = View::renderPartial('site/_crumbs', ['crumbs' => []]);
    assert_not_contains('content-crumbs', $empty);
});
