<?php

use App\Core\Csrf;

$pageTitle = 'Проекты';
$activeNav = 'projects';
require __DIR__ . '/../layout/header.php';

/** @var array $items */
?>
<div class="page-toolbar">
    <a href="/admin/projects/create" class="btn btn--primary">+ Добавить проект</a>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>Название</th>
            <th>Статус</th>
            <th>Порядок</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($items)): ?>
            <tr><td colspan="4" class="data-table__empty">Проектов пока нет.</td></tr>
        <?php endif; ?>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['title'], ENT_QUOTES) ?></td>
                <td>
                    <span class="badge badge--<?= $item['status'] ?>">
                        <?= $item['status'] === 'published' ? 'Опубликовано' : 'Черновик' ?>
                    </span>
                </td>
                <td><?= (int) $item['sort_order'] ?></td>
                <td class="data-table__actions">
                    <a class="btn btn--small" href="/admin/projects/<?= (int) $item['id'] ?>/edit">Редактировать</a>
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
