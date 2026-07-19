<?php

declare(strict_types=1);

test('Настройки детальной новости находятся в правой колонке без дубля бейджа', function (): void {
    $form = (string) file_get_contents(APP_ROOT . '/app/Views/admin/news/form.php');
    $sidebar = (string) file_get_contents(APP_ROOT . '/app/Views/admin/news/_detail_sidebar.php');

    $asidePos = strpos($form, '<aside class="entry-side">');
    $partialPos = strpos($form, "require __DIR__ . '/_detail_sidebar.php'");
    assert_true($asidePos !== false && $partialPos !== false && $partialPos > $asidePos, 'детальная карточка должна быть внутри правой колонки');

    assert_same(1, substr_count($form, 'name="badge"'), 'основной бейдж выводится ровно один раз');
    assert_contains('name="translations[<?= $code ?>][badge]"', $form, 'переводные бейджи сохраняются');
    assert_not_contains('name="badge"', $sidebar, 'в деталке нет второго поля бейджа');

    assert_not_contains('name="press_release_url"', $sidebar, 'отдельное поле пресс-релиза убрано');
    assert_contains('$legacyPressUrl', $sidebar, 'старый файл переносится в документы');

    foreach (['source_note', 'key_points', 'event_meta', 'docs'] as $field) {
        assert_contains($field, $sidebar, $field);
    }
});
