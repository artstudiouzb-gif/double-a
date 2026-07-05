<?php

use App\Core\Csrf;
use App\Models\Language;

$pageTitle = 'Страницы';
$activeNav = 'pages';
require __DIR__ . '/../layout/header.php';

/** @var array $items */
/** @var string $filterStatus */
/** @var string $filterLang */
$filterStatus = $filterStatus ?? '';
$filterLang = $filterLang ?? '';
$langs = Language::active();
?>
<div class="page-toolbar">
    <a href="/admin/pages/create" class="btn btn--primary">+ Добавить страницу</a>
</div>

<form method="get" action="/admin/pages" class="list-filters">
    <select name="status" onchange="this.form.submit()">
        <option value="">Все статусы</option>
        <option value="published" <?= $filterStatus === 'published' ? 'selected' : '' ?>>Опубликованные</option>
        <option value="draft" <?= $filterStatus === 'draft' ? 'selected' : '' ?>>Черновики</option>
    </select>
    <select name="lang" onchange="this.form.submit()">
        <option value="">Все языки</option>
        <?php foreach ($langs as $l): ?>
            <option value="<?= htmlspecialchars($l['code'], ENT_QUOTES) ?>" <?= $filterLang === $l['code'] ? 'selected' : '' ?>>
                Есть перевод: <?= htmlspecialchars($l['name'], ENT_QUOTES) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <noscript><button type="submit" class="btn btn--small">Фильтр</button></noscript>
</form>

<form id="bulkform" method="post" action="/admin/bulk/pages" class="bulk-bar" data-bulk-form>
    <?= Csrf::field() ?>
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
                <td><?= htmlspecialchars($item['title'], ENT_QUOTES) ?></td>
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
                        <button type="submit" class="btn btn--small btn--danger">Удалить</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require __DIR__ . '/../layout/footer.php'; ?>
