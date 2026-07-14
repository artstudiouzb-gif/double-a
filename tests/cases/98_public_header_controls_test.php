<?php

declare(strict_types=1);

test('accessibility controls stay above the header and reflow without overlap', function (): void {
    $css = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/a11y.css');
    $header = file_get_contents(dirname(__DIR__, 2) . '/app/Views/site/_header.php');
    $js = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/a11y.js');

    assert_true(is_string($css));
    assert_true(is_string($header));
    assert_true(is_string($js));
    assert_contains('z-index: 600', $css);
    assert_contains('position: sticky; top: 0;', $css);
    assert_contains('grid-template-columns: repeat(3, max-content)', $css);
    assert_contains('@media (max-width: 560px)', $css);
    assert_contains('id="a11y-panel"', $header);
    assert_contains('aria-controls="a11y-panel"', $header);
    assert_contains("toggle.setAttribute('aria-expanded'", $js);
    assert_contains("e.key === 'Escape'", $js);
});

test('dropdown search is anchored, constrained and restores focus', function (): void {
    $css = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/frontend.css');
    $themeCss = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/gov-theme.css');
    $header = file_get_contents(dirname(__DIR__, 2) . '/app/Views/site/_header.php');
    $js = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/frontend.js');

    assert_true(is_string($css));
    assert_true(is_string($themeCss));
    assert_true(is_string($header));
    assert_true(is_string($js));
    assert_contains('.site-search-overlay { position: fixed; inset: 0; z-index: 700;', $css);
    assert_contains('width: min(620px, calc(100vw - 32px))', $css);
    assert_contains('body.design-search-inline .site-header .site-search { display: inline-flex; }', $themeCss);
    assert_contains('body.design-search-overlay .site-header .site-search-toggle { display: grid; }', $themeCss);
    assert_contains('id="site-search-popover"', $header);
    assert_contains('minlength="2" required', $header);
    assert_contains('var positionSearch = function (toggle)', $js);
    assert_contains('focusTarget.focus()', $js);
    assert_contains("e.key === 'Tab'", $js);
    assert_contains("document.body.classList.add('site-search-open')", $js);
    assert_contains("hdr.style.setProperty('--hdr-panel-height'", $js);
});
