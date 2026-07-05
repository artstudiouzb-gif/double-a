<?php

use App\Core\Csrf;

$pageTitle = 'Проекты';
$activeNav = 'projects';
require __DIR__ . '/../layout/header.php';

/** @var array $items */
/** @var string $filterStatus */
$filterStatus = $filterStatus ?? '';
?>
<div class="page-toolbar">
    <a href="/admin/projects/create" class="btn btn--primary">+ Добавить проект</a>
</div>

<form method="get" action="/admin/projects" class="list-filters">
    <select name="status" onchange="this.form.submit()">
        <option value="">Все статусы</option>
        <option value="published" <?= $filterStatus === 'published' ? 'selected' : '' ?>>Опубликованные</option>
        <option value="draft" <?= $filterStatus === 'draft' ? 'selected' : '' ?>>Черновики</option>
    </select>
    <noscript><button type="submit" class="btn btn--small">Фильтр</button></noscript>
</form>

<form id="bulkform" method="post" action="/admin/bulk/projects" class="bulk-bar" data-bulk-form>
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
            <th>Название</th>
            <th>Статус</th>
            <th>Порядок</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($items)): ?>
            <tr><td colspan="5" class="data-table__empty">Проектов не найдено.</td></tr>
        <?php endif; ?>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><input type="checkbox" name="ids[]" value="<?= (int) $item['id'] ?>" form="bulkform" data-bulk-item></td>
                <td><?= htmlspecialchars($item['title'], ENT_QUOTES) ?></td>
                <td>
                    <span class="badge badge--<?= $item['status'] ?>">
                        <?= $item['status'] === 'published' ? 'Опубликовано' : 'Черновик' ?>
                    </span>
                </td>
                <td><?= (int) $item['sort_order'] ?></td>
                <td class="data-table__actions">
                    <a class="btn btn--small" href="/admin/projects/<?= (int) $item['id'] ?>/edit">Редактировать</a>
                    <form method="post" action="/admin/projects/<?= (int) $item['id'] ?>/duplicate">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn btn--small">Дублировать</button>
                    </form>
                    <form method="post" action="/admin/projects/<?= (int) $item['id'] ?>/delete" data-confirm="Удалить проект «<?= htmlspecialchars($item['title'], ENT_QUOTES) ?>»?">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn btn--small btn--danger">Удалить</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require __DIR__ . '/../layout/footer.php'; ?>
