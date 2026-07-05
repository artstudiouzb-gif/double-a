<?php

use App\Core\Csrf;

$pageTitle = 'Команда';
$activeNav = 'team';
require __DIR__ . '/../layout/header.php';

/** @var array $items */
?>
<div class="page-toolbar">
    <a href="/admin/team/create" class="btn btn--primary">+ Добавить сотрудника</a>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>Имя</th>
            <th>Должность</th>
            <th>Статус</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($items)): ?>
            <tr><td colspan="4" class="data-table__empty">Сотрудников пока нет.</td></tr>
        <?php endif; ?>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['name'], ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars($item['position'] ?? '', ENT_QUOTES) ?></td>
                <td>
                    <span class="badge badge--<?= $item['status'] ?>">
                        <?= $item['status'] === 'published' ? 'Опубликовано' : 'Черновик' ?>
                    </span>
                </td>
                <td class="data-table__actions">
                    <a class="btn btn--small" href="/admin/team/<?= (int) $item['id'] ?>/edit">Редактировать</a>
                    <form method="post" action="/admin/team/<?= (int) $item['id'] ?>/delete" data-confirm="Удалить сотрудника «<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>»?">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn btn--small btn--danger">Удалить</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require __DIR__ . '/../layout/footer.php'; ?>
