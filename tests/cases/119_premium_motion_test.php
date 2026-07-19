<?php

declare(strict_types=1);

test('Главные CTA и преимущества используют доступные premium-анимации', function () {
    $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/frontend.css');

    assert_contains('@keyframes hero-button-sheen', $css);
    assert_contains('.block-hero__button:not(.block-hero__button--ghost)::after', $css);
    assert_contains('animation: hero-button-sheen 6s ease-in-out infinite;', $css);
    assert_false(str_contains($css, 'animation: hero-button-sheen 4.2s'));
    assert_contains('@keyframes advantages-icon-float', $css);
    assert_contains('.block-advantages__item:focus-within .block-advantages__icon', $css);
    assert_contains('@media (prefers-reduced-motion: reduce)', $css);
    assert_contains('.block-hero__button::after { content: none; animation: none; }', $css);
});

test('Карусель проектов не отключает анимации своих карточек', function (): void {
    $govCss = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/gov-theme.css');
    $frontendCss = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/frontend.css');

    assert_true(!str_contains($govCss, '[data-carousel-track] *'), 'Вложенные переходы карусели должны оставаться активными');
    assert_contains('transition: transform .7s cubic-bezier(.22, 1, .36, 1)', $govCss);
    assert_contains('transform: translateY(18px) scale(.99)', $frontendCss);
});

test('Счётчики без стекла и поворота, новости появляются мягко', function (): void {
    $govCss = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/gov-theme.css');
    $frontendCss = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/frontend.css');

    $countersStart = (int) strpos($govCss, '.block-counters {');
    $countersEnd = (int) strpos($govCss, '/* --- Секции:', $countersStart);
    $countersCss = substr($govCss, $countersStart, $countersEnd - $countersStart);
    assert_contains('background: var(--counters-bg, var(--gov-surface))', $countersCss);
    assert_true(!str_contains($countersCss, 'backdrop-filter'), 'У счётчиков не должно быть размытия стекла');

    $iconStart = (int) strpos($govCss, '.counter__icon {');
    $iconEnd = (int) strpos($govCss, '.counter__icon svg', $iconStart);
    $iconCss = substr($govCss, $iconStart, $iconEnd - $iconStart);
    assert_true(!str_contains($iconCss, 'rotate('), 'Иконки счётчиков не должны поворачиваться');

    assert_contains('.newsfeat-grid > .anim-card', $frontendCss);
    assert_contains('transform: translateY(8px) scale(.995)', $frontendCss);
    assert_contains(':where(.newsfeat-lead, .newsfeat-mini, .newsfeat-text):hover', $govCss);
    assert_contains('transform: translateY(-1px)', $govCss);
});
