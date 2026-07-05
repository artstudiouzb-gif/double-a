<?php

declare(strict_types=1);

use App\Core\HtmlSanitizer;

// ACL роли editor: сырой HTML editor'а всегда проходит через HtmlSanitizer,
// поэтому <script>/on*/javascript: не могут быть внедрены.
test('HtmlSanitizer: вырезает <script>', function () {
    $out = HtmlSanitizer::sanitize('<p>Привет</p><script>alert(1)</script>');
    assert_not_contains('<script', $out);
    assert_contains('Привет', $out);
});

test('HtmlSanitizer: удаляет обработчики on*', function () {
    $out = HtmlSanitizer::sanitize('<a href="/x" onclick="steal()">клик</a>');
    assert_not_contains('onclick', $out);
    assert_contains('клик', $out);
});

test('HtmlSanitizer: убирает javascript:-ссылки', function () {
    $out = HtmlSanitizer::sanitize('<a href="javascript:alert(1)">x</a>');
    assert_not_contains('javascript:', $out);
});

test('HtmlSanitizer: сохраняет разрешённое форматирование', function () {
    $out = HtmlSanitizer::sanitize('<p><strong>жирный</strong> и <em>курсив</em></p>');
    assert_contains('<strong>', $out);
    assert_contains('<em>', $out);
});

test('HtmlSanitizer: разворачивает запрещённые теги, сохраняя текст', function () {
    $out = HtmlSanitizer::sanitize('<iframe src="//evil">видимый текст</iframe>');
    assert_not_contains('<iframe', $out);
    assert_contains('видимый текст', $out);
});

test('HtmlSanitizer: target=_blank получает rel=noopener', function () {
    $out = HtmlSanitizer::sanitize('<a href="https://example.com" target="_blank">x</a>');
    assert_contains('noopener', $out);
});
