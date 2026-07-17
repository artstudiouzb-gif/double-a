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

test('Page::availableLangsForIds: батч без N+1 — перевод и стек блоков', function () {
    ensure_test_db();
    $pdo = Database::pdo();
    $default = \App\Models\Language::defaultCode();

    $pdo->exec("INSERT INTO pages (title, slug, status) VALUES ('Батч-1', 'batch-langs-1', 'published')");
    $p1 = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO pages (title, slug, status) VALUES ('Батч-2', 'batch-langs-2', 'published')");
    $p2 = (int) $pdo->lastInsertId();

    // p1: узбекский перевод; p2: свой стек блоков на en; пустой перевод не в счёт.
    $pdo->prepare('INSERT INTO page_translations (page_id, lang, title) VALUES (?, ?, ?)')
        ->execute([$p1, 'uz', 'Sahifa']);
    $pdo->prepare('INSERT INTO page_translations (page_id, lang, title) VALUES (?, ?, ?)')
        ->execute([$p2, 'uz', '  ']);
    $pdo->prepare("INSERT INTO blocks (page_id, lang, type, data, sort_order) VALUES (?, 'en', 'text', '{}', 1)")
        ->execute([$p2]);

    $map = Page::availableLangsForIds([$p1, $p2]);
    assert_true(in_array($default, $map[$p1], true) && in_array('uz', $map[$p1], true), 'p1: дефолт + узбекский');
    assert_true(!in_array('en', $map[$p1], true), 'p1: чужих языков нет');
    assert_true(in_array('en', $map[$p2], true), 'p2: язык со стеком блоков');
    assert_true(!in_array('uz', $map[$p2], true), 'p2: пустой перевод не в счёт');

    // Неизвестный id получает хотя бы дефолтный язык, без падения.
    $missing = Page::availableLangsForIds([999999]);
    assert_same([$default], $missing[999999]);

    $pdo->exec("DELETE FROM pages WHERE id IN ({$p1}, {$p2})");
});

test('Вью списка страниц: колонка «Языки» и батч-запрос языков', function () {
    $view = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Views/admin/pages/index.php');
    assert_contains('<th>Языки</th>', $view);
    assert_contains('availableLangsForIds', $view);
    assert_contains('admin/layout/lang_badges', $view);
});

test('Project::availableLangsForIds + localize: перевод title/description с фолбэком (БД)', function () {
    ensure_test_db();
    $pdo = Database::pdo();
    $default = \App\Models\Language::defaultCode();

    $pdo->exec("INSERT INTO projects (title, slug, description, status) VALUES ('Проект RU', 'proj-langs-1', 'Описание RU', 'published')");
    $p1 = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO projects (title, slug, description, status) VALUES ('Без перевода', 'proj-langs-2', 'Только RU', 'published')");
    $p2 = (int) $pdo->lastInsertId();

    // p1: узбекский перевод только заголовка (описание пустое → фолбэк).
    \App\Models\ProjectTranslation::upsert($p1, 'uz', ['title' => 'Loyiha UZ', 'description' => '']);

    $map = \App\Models\Project::availableLangsForIds([$p1, $p2]);
    assert_true(in_array('uz', $map[$p1], true), 'p1: узбекский доступен');
    assert_same([$default], $map[$p2], 'p2: только основной');

    // Локализация: заголовок из перевода, описание — фолбэк к основному.
    $row = \App\Models\Project::findPublishedBySlug('proj-langs-1', 'uz');
    assert_same('Loyiha UZ', $row['title']);
    assert_same('Описание RU', $row['description'], 'пустой перевод описания откатывается к основному');

    // Основной язык не трогает строку.
    $base = \App\Models\Project::findPublishedBySlug('proj-langs-1', $default);
    assert_same('Проект RU', $base['title']);

    $pdo->exec("DELETE FROM projects WHERE id IN ({$p1}, {$p2})");
});

test('Вью списка проектов: колонка «Языки» и батч-запрос языков', function () {
    $view = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Views/admin/projects/index.php');
    assert_contains('<th>Языки</th>', $view);
    assert_contains('availableLangsForIds', $view);
    assert_contains('admin/layout/lang_badges', $view);
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
