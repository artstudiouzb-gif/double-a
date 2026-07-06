<?php

declare(strict_types=1);

use App\Core\BlockRenderer;
use App\Core\Database;
use App\Models\Block;
use App\Models\Page;

test('Блок columns рендерит вложенные блоки по колонкам, запрещая columns-в-columns (БД, группа 4.1)', function () {
    ensure_test_db();
    $pdo = Database::pdo();
    $pdo->exec('DELETE FROM blocks');
    $pdo->exec('DELETE FROM pages');

    $pageId = Page::create([
        'slug' => 'cols', 'title' => 'Cols', 'status' => 'published',
        'meta_title' => '', 'meta_description' => '', 'layout_type' => 'no_sidebar',
    ]);

    // Родитель columns с 2 колонками.
    $colBlock = Block::create($pageId, '', 'columns', 'Сетка', ['columns' => 2, 'gap' => 'large'], '');
    // По одному текстовому блоку в каждую колонку.
    Block::create($pageId, '', 'text', null, ['content' => 'ЛЕВАЯ_КОЛОНКА'], '', $colBlock, 0);
    Block::create($pageId, '', 'text', null, ['content' => 'ПРАВАЯ_КОЛОНКА'], '', $colBlock, 1);
    // Попытка вложить columns-в-columns — при рендере должна игнорироваться.
    Block::create($pageId, '', 'columns', 'Вложенная', ['columns' => 2], '', $colBlock, 0);

    // Верхний уровень: только сам columns-блок (дети исключены).
    $top = Block::forPageLocalized($pageId, '');
    assert_same(1, count($top), 'на верхнем уровне только columns-блок');

    $out = BlockRenderer::renderPage($top);
    $html = $out['html'];

    assert_contains('cms-columns cms-columns--2 cms-columns--gap-large', $html, 'сетка колонок отрисована');
    assert_contains('ЛЕВАЯ_КОЛОНКА', $html);
    assert_contains('ПРАВАЯ_КОЛОНКА', $html);
    // Ровно две колонки в разметке.
    assert_same(2, substr_count($html, 'cms-columns__col'));
    // Вложенный columns-блок не должен появиться внутри (без второй сетки).
    assert_same(1, substr_count($html, 'cms-columns--2'), 'columns-в-columns не рендерится');

    $pdo->exec('DELETE FROM pages');
});
