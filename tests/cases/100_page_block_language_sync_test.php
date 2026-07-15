<?php

declare(strict_types=1);

test('page editor keeps page and block language tabs synchronized', function (): void {
    $view = file_get_contents(dirname(__DIR__, 2) . '/app/Views/admin/pages/form.php');
    $controller = file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Admin/PageController.php');
    $js = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/admin.js');

    assert_true(is_string($view));
    assert_true(is_string($controller));
    assert_true(is_string($js));
    assert_contains('name="block_lang" value="<?= htmlspecialchars($blockLang, ENT_QUOTES) ?>"', $view);
    assert_contains('data-sync-block-language', $view);
    assert_contains('$langCode === $blockLang', $view);
    assert_contains('$code === $blockLang', $view);
    assert_contains("\$_GET['block_lang'] ?? \$_POST['block_lang']", $controller);
    assert_contains("/edit?block_lang=' . urlencode(\$blockLang)", $controller);
    assert_contains("param = 'block_lang=' + encodeURIComponent(target)", $js);
    assert_contains("window.location.assign(window.location.pathname + newSearch + window.location.hash)", $js);
});
