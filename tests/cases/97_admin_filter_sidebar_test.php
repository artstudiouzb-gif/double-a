<?php

declare(strict_types=1);

test('admin filters wrap inside their panel and sidebar uses a light accessible palette', function (): void {
    $css = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/admin.css');

    assert_true(is_string($css));
    assert_contains('--admin-sidebar-bg: #f6f7f7', $css);
    assert_contains('--admin-sidebar-text: #2c3338', $css);
    assert_contains('--admin-sidebar-active: #dcecf7', $css);
    assert_contains('.list-filters--panel { display: flex; flex-wrap: wrap;', $css);
    assert_contains('.list-filters__actions { display: flex; flex: 0 0 auto;', $css);
    assert_contains('.list-filters__actions { width: 100%; margin-left: 0; }', $css);
});

test('header settings group behavior controls into spacious responsive cards', function (): void {
    $view = file_get_contents(dirname(__DIR__, 2) . '/app/Views/admin/header/index.php');
    $css = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/admin.css');

    assert_true(is_string($view));
    assert_true(is_string($css));
    assert_contains('class="hb-behavior__options"', $view);
    assert_contains('class="hb-behavior-card"', $view);
    assert_contains('class="hb-behavior__media"', $view);
    assert_contains('.hb-behavior__options { display: grid;', $css);
    assert_contains('@media (max-width: 720px)', $css);
});

test('long admin editors expose a fixed save bar without covering their content', function (): void {
    $css = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/admin.css');
    $layout = file_get_contents(dirname(__DIR__, 2) . '/app/Views/admin/layout/footer.php');
    $header = file_get_contents(dirname(__DIR__, 2) . '/app/Views/admin/header/index.php');
    $block = file_get_contents(dirname(__DIR__, 2) . '/app/Views/admin/pages/block_form.php');

    assert_true(is_string($css));
    assert_true(is_string($layout));
    assert_true(is_string($header));
    assert_true(is_string($block));
    assert_contains('.form-actions--sticky {', $css);
    assert_contains('position: fixed; left: calc(var(--admin-sidebar-w) + 28px);', $css);
    assert_contains('padding-bottom: 108px', $css);
    assert_contains("document.body.classList.add('has-sticky-actions')", $layout);
    assert_contains("actions.classList.toggle('is-context-hidden', !inContext)", $layout);
    assert_contains('form-actions form-actions--sticky', $header);
    assert_contains('form-actions form-actions--sticky', $block);
});

test('installer buttons have consistent states and prevent duplicate submission', function (): void {
    $header = file_get_contents(dirname(__DIR__, 2) . '/app/Views/install/_header.php');
    $footer = file_get_contents(dirname(__DIR__, 2) . '/app/Views/install/_footer.php');

    assert_true(is_string($header));
    assert_true(is_string($footer));
    assert_contains('install-card', $header);
    assert_contains('.install-card .btn--primary:hover', $header);
    assert_contains("button.setAttribute('aria-busy', 'true')", $footer);
    assert_contains("button.textContent = 'Подождите…'", $footer);
});

test('repository source does not contain the retired external product name', function (): void {
    $root = dirname(__DIR__, 2);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $path = $file->getPathname();
        if (str_contains($path, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR)
            || str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
            continue;
        }
        $contents = file_get_contents($path);
        if (!is_string($contents)) {
            continue;
        }
        assert_true(stripos($contents, 'word' . 'press') === false, 'Найдено запрещённое название: ' . $path);
    }
});
