<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Locale;
use App\Models\News;
use App\Models\Page;

// Roadmap 1.2: переключатель языков и hreflang показывают только языки,
// на которых сущность реально наполнена.

test('Locale::contentLangs: null по умолчанию, set/get, дедупликация', function () {
    Locale::setContentLangs(null);
    assert_true(Locale::contentLangs() === null, 'общий маршрут — без ограничений');
    Locale::setContentLangs(['ru', 'uz', 'ru']);
    assert_same(['ru', 'uz'], Locale::contentLangs());
    Locale::setContentLangs(null);
});

test('Page::availableLangs: дефолтный язык + перевод + свой стек блоков', function () {
    ensure_test_db();
    $pdo = Database::pdo();
    $pdo->exec("INSERT INTO pages (title, slug, status) VALUES ('Тест-языки', 'test-avail-langs', 'published')");
    $pageId = (int) $pdo->lastInsertId();

    // Без переводов — только язык по умолчанию.
    $langs = Page::availableLangs($pageId);
    assert_same([\App\Models\Language::defaultCode()], $langs);

    // Перевод с пустым title не считается наполненным.
    $pdo->prepare('INSERT INTO page_translations (page_id, lang, title) VALUES (?, ?, ?)')
        ->execute([$pageId, 'uz', '  ']);
    assert_true(!in_array('uz', Page::availableLangs($pageId), true), 'пустой перевод не в счёт');

    // Заголовок появился — язык доступен.
    $pdo->prepare('UPDATE page_translations SET title = ? WHERE page_id = ? AND lang = ?')
        ->execute(['Sahifa', $pageId, 'uz']);
    assert_true(in_array('uz', Page::availableLangs($pageId), true));

    // Свой стек блоков тоже делает язык доступным (без строки перевода).
    $pdo->prepare("INSERT INTO blocks (page_id, lang, type, data, sort_order) VALUES (?, 'en', 'text', '{}', 1)")
        ->execute([$pageId]);
    assert_true(in_array('en', Page::availableLangs($pageId), true), 'язык со стеком блоков доступен');

    $pdo->exec("DELETE FROM pages WHERE id = {$pageId}");
});

test('News::availableLangs: перевод заголовка или текста', function () {
    ensure_test_db();
    $pdo = Database::pdo();
    $pdo->exec("INSERT INTO news (title, slug, status, published_at) VALUES ('Тест', 'test-avail-news', 'published', NOW())");
    $newsId = (int) $pdo->lastInsertId();

    assert_same([\App\Models\Language::defaultCode()], News::availableLangs($newsId));

    $pdo->prepare('INSERT INTO news_translations (news_id, lang, title, content) VALUES (?, ?, ?, ?)')
        ->execute([$newsId, 'en', '', 'Translated body']);
    assert_true(in_array('en', News::availableLangs($newsId), true), 'перевод текста достаточен');

    $pdo->exec("DELETE FROM news WHERE id = {$newsId}");
});
