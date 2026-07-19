<?php

declare(strict_types=1);

test('Главные CTA и преимущества используют доступные premium-анимации', function () {
    $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/frontend.css');

    assert_contains('@keyframes hero-button-sheen', $css);
    assert_contains('.block-hero__button:not(.block-hero__button--ghost)::after', $css);
    assert_contains('@keyframes advantages-icon-float', $css);
    assert_contains('.block-advantages__item:focus-within .block-advantages__icon', $css);
    assert_contains('@media (prefers-reduced-motion: reduce)', $css);
    assert_contains('.block-hero__button::after { content: none; animation: none; }', $css);
});
