<?php

use App\Core\Csrf;
use App\Models\Language;

$pageTitle = 'Страницы';
$activeNav = 'pages';
$pageActions = '<a href="/admin/pages/create" class="btn btn--primary">+ Добавить страницу</a>';
require __DIR__ . '/../layout/header.php';

/** @var array $items */
/** @var array $filters */
/** @var array $filterParams */
/** @var int $total */
/** @var int $pages */
$langs = Language::active();
?>

<form method="get" action="/admin/pages" class="list-filters list-filters--panel">
    <div class="list-filter list-filter--search"><label for="pages_q">Поиск</label><input type="search" id="pages_q" name="q" value="<?= htmlspecialchars($filters['q'], ENT_QUOTES) ?>" placeholder="Заголовок или slug"></div>
    <div class="list-filter"><label for="pages_status">Статус</label><select id="pages_status" name="status"><option value="">Все статусы</option><option value="published" <?= $filters['status'] === 'published' ? 'selected' : '' ?>>Опубликованные</option><option value="draft" <?= $filters['status'] === 'draft' ? 'selected' : '' ?>>Черновики</option></select></div>
    <div class="list-filter"><label for="pages_lang">Язык</label><select id="pages_lang" name="lang"><option value="">Все языки</option><?php foreach ($langs as $l): ?><option value="<?= htmlspecialchars($l['code'], ENT_QUOTES) ?>" <?= $filters['lang'] === $l['code'] ? 'selected' : '' ?>><?= $l['code'] === Language::defaultCode() ? 'Основной: ' : 'Есть перевод: ' ?><?= htmlspecialchars($l['name'], ENT_QUOTES) ?></option><?php endforeach; ?></select></div>
    <div class="list-filter"><label for="pages_sort">Сортировка</label><select id="pages_sort" name="sort"><option value="newest" <?= $filters['sort'] === 'newest' ? 'selected' : '' ?>>Сначала новые</option><option value="oldest" <?= $filters['sort'] === 'oldest' ? 'selected' : '' ?>>Сначала старые</option><option value="title_asc" <?= $filters['sort'] === 'title_asc' ? 'selected' : '' ?>>Название А–Я</option><option value="title_desc" <?= $filters['sort'] === 'title_desc' ? 'selected' : '' ?>>Название Я–А</option></select></div>
    <div class="list-filter list-filter--compact"><label for="pages_per_page">На странице</label><select id="pages_per_page" name="per_page"><?php foreach ([20, 50, 100] as $size): ?><option value="<?= $size ?>" <?= $filters['per_page'] === $size ? 'selected' : '' ?>><?= $size ?></option><?php endforeach; ?></select></div>
    <div class="list-filters__actions"><button type="submit" class="btn btn--primary">Применить</button><a href="/admin/pages" class="btn">Сбросить</a></div>
</form>

<p class="list-results">Найдено: <strong><?= (int) $total ?></strong></p>

<form id="bulkform" method="post" action="/admin/bulk/pages" class="bulk-bar" data-bulk-form>
    <?= Csrf::field() ?>
    <input type="hidden" name="return_query" value="<?= htmlspecialchars(http_build_query($filterParams), ENT_QUOTES) ?>">
    <select name="bulk_action" required>
        <option value="">С выбранными…</option>
        <option value="publish">Опубликовать</option>
        <option value="unpublish">Снять с публикации</option>
        <option value="duplicate">Дублировать</option>
        <option value="trash">В корзину</option>
    </select>
    <button type="submit" class="btn btn--small">Применить</button>
    <span class="bulk-bar__count" data-bulk-count>0 выбрано</span>
</form>

<table class="data-table">
    <thead>
        <tr>
            <th style="width:32px;"><input type="checkbox" data-select-all aria-label="Выбрать все"></th>
            <th>Заголовок</th>
            <th>URL</th>
            <th>Статус</th>
            <th>Главная</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($items)): ?>
            <tr><td colspan="6" class="data-table__empty">Страниц не найдено.</td></tr>
        <?php endif; ?>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><input type="checkbox" name="ids[]" value="<?= (int) $item['id'] ?>" form="bulkform" data-bulk-item></td>
                <td><a class="data-table__primary" href="/admin/pages/<?= (int) $item['id'] ?>/edit"><?= htmlspecialchars($item['title'], ENT_QUOTES) ?></a></td>
                <td>/<?= htmlspecialchars($item['slug'], ENT_QUOTES) ?></td>
                <td>
                    <span class="badge badge--<?= $item['status'] ?>">
                        <?= $item['status'] === 'published' ? 'Опубликовано' : 'Черновик' ?>
                    </span>
                </td>
                <td><?= $item['is_home'] ? '✓' : '' ?></td>
                <td class="data-table__actions">
                    <a class="btn btn--small" href="/admin/pages/<?= (int) $item['id'] ?>/edit">Редактировать</a>
                    <form method="post" action="/admin/pages/<?= (int) $item['id'] ?>/duplicate">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn btn--small">Дублировать</button>
                    </form>
                    <form method="post" action="/admin/pages/<?= (int) $item['id'] ?>/delete" data-confirm="Удалить страницу «<?= htmlspecialchars($item['title'], ENT_QUOTES) ?>» вместе со всеми блоками?">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="return_query" value="<?= htmlspecialchars(http_build_query($filterParams), ENT_QUOTES) ?>">
                        <button type="submit" class="btn btn--small btn--danger">Удалить</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?= \App\Core\View::renderPartial('admin/layout/pagination', ['paginationPath' => '/admin/pages', 'filterParams' => $filterParams, 'page' => $filters['page'], 'pages' => $pages, 'total' => $total]) ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
